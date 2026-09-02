<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Services;

use App\Models\AdminReportExport;
use App\Models\AdminSavedReport;
use App\Models\AdminUser;
use App\Modules\Admin\AnalyticsOperations\Exceptions\AnalyticsOperationsException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ReportService
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function create(AdminUser $admin, array $data): AdminSavedReport
    {
        foreach ($data['metrics'] as $metric) {
            if (! in_array($metric, AnalyticsService::METRICS, true)) {
                throw new AnalyticsOperationsException('ANALYTICS_METRIC_UNSUPPORTED', 'One or more requested analytics metrics are unsupported.', 422);
            }
        }

        return AdminSavedReport::query()->create(['admin_user_id' => $admin->id, 'name' => $data['name'], 'metrics' => $data['metrics'], 'filters' => $data['filters'] ?? [], 'group_by' => $data['group_by'] ?? null, 'comparison' => $data['comparison'] ?? null, 'team_shared' => (bool) ($data['team_shared'] ?? false), 'schedule' => $data['schedule'] ?? null, 'next_run_at' => isset($data['schedule']) ? now()->addDay() : null]);
    }

    public function run(AdminSavedReport $report): array
    {
        $result = $this->analytics->run($report->metrics ?? [], $report->filters ?? []);
        $report->forceFill(['last_run_at' => now()])->save();

        return $result;
    }

    public function export(AdminUser $admin, AdminSavedReport $report, string $format = 'csv'): AdminReportExport
    {
        $result = $this->run($report);
        $format = in_array($format, ['csv', 'xlsx'], true) ? $format : 'csv';
        $id = (string) Str::uuid7();
        $path = 'admin-report-exports/'.$id.'.'.$format;
        if ($format === 'xlsx') {
            $payload = $this->xlsx($result['rows']);
        } else {
            $payload = "metric,value\n";
            foreach ($result['rows'] as $row) {
                $payload .= sprintf("%s,%d\n", str_replace(["\r", "\n", ','], '_', (string) $row['metric']), (int) $row['value']);
            }
        }
        Storage::disk(config('filesystems.default'))->put($path, $payload);

        return AdminReportExport::query()->create(['id' => $id, 'saved_report_id' => $report->id, 'admin_user_id' => $admin->id, 'format' => $format, 'status' => 'ready', 'storage_path' => $path, 'row_count' => count($result['rows']), 'expires_at' => now()->addDay()]);
    }

    private function xlsx(array $rows): string
    {
        $xml = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $sheet .= '<row r="1"><c r="A1" t="inlineStr"><is><t>metric</t></is></c><c r="B1" t="inlineStr"><is><t>value</t></is></c></row>';
        foreach ($rows as $index => $row) {
            $r = $index + 2;
            $sheet .= '<row r="'.$r.'"><c r="A'.$r.'" t="inlineStr"><is><t>'.$xml((string) $row['metric']).'</t></is></c><c r="B'.$r.'"><v>'.(int) $row['value'].'</v></c></row>';
        }
        $sheet .= '</sheetData></worksheet>';

        return $this->storedZip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Orbit Report" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?>'.$sheet,
        ]);
    }

    /** @param array<string, string> $files */
    private function storedZip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $entries = 0;

        foreach ($files as $name => $contents) {
            $nameBytes = (string) $name;
            $crc = crc32($contents);
            $size = strlen($contents);
            [$dosTime, $dosDate] = $this->dosTimestamp();

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034B50,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0,
            ).$nameBytes;

            $local .= $localHeader.$contents;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0,
                0,
                0,
                0,
                0,
                $offset,
            ).$nameBytes;

            $offset += strlen($localHeader) + $size;
            $entries++;
        }

        $end = pack(
            'VvvvvVVv',
            0x06054B50,
            0,
            0,
            $entries,
            $entries,
            strlen($central),
            strlen($local),
            0,
        );

        return $local.$central.$end;
    }

    /** @return array{0:int,1:int} */
    private function dosTimestamp(): array
    {
        $parts = getdate();
        $year = max(1980, (int) $parts['year']);
        $time = ((int) $parts['hours'] << 11)
            | ((int) $parts['minutes'] << 5)
            | (int) floor(((int) $parts['seconds']) / 2);
        $date = (($year - 1980) << 9)
            | ((int) $parts['mon'] << 5)
            | (int) $parts['mday'];

        return [$time, $date];
    }
}
