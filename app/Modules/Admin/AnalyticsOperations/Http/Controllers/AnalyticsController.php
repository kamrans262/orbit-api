<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Http\Controllers;

use App\Models\AdminReportExport;
use App\Models\AdminSavedReport;
use App\Modules\Admin\AnalyticsOperations\Services\AnalyticsService;
use App\Modules\Admin\AnalyticsOperations\Services\ReportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class AnalyticsController
{
    public function center(Request $r, AnalyticsService $s)
    {
        $v = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);

        return AdminApiResponse::success($r, $s->center($v['from'] ?? null, $v['to'] ?? null));
    }

    public function reports(Request $r)
    {
        $a = $r->user();
        $q = AdminSavedReport::query()->where(fn ($q) => $q->where('admin_user_id', $a->id)->orWhere('team_shared', true))->latest();

        return AdminApiResponse::success($r, ['items' => $q->paginate(min(100, max(1, (int) $r->integer('per_page', 25))))->items()]);
    }

    public function create(Request $r, ReportService $s)
    {
        $d = $r->validate(['name' => 'required|string|max:160', 'metrics' => 'required|array|min:1|max:30', 'metrics.*' => 'string|max:120', 'filters' => 'nullable|array', 'group_by' => 'nullable|string|max:60', 'comparison' => 'nullable|string|max:40', 'team_shared' => 'nullable|boolean', 'schedule' => 'nullable|in:daily,weekly,monthly']);

        return AdminApiResponse::success($r, $s->create($r->user(), $d), 201);
    }

    public function run(Request $r, string $id, ReportService $s)
    {
        $rep = AdminSavedReport::query()->findOrFail($id);
        $this->authorize($r, $rep);

        return AdminApiResponse::success($r, $s->run($rep));
    }

    public function export(Request $r, string $id, ReportService $s)
    {
        $rep = AdminSavedReport::query()->findOrFail($id);
        $this->authorize($r, $rep);
        $v = $r->validate(['format' => 'nullable|in:csv,xlsx']);

        return AdminApiResponse::success($r, $s->export($r->user(), $rep, $v['format'] ?? 'csv'), 201);
    }

    public function download(Request $r, string $id)
    {
        $e = AdminReportExport::query()->findOrFail($id);
        if ((int) $e->admin_user_id !== (int) $r->user()->id && ! $e->savedReport?->team_shared) {
            abort(404);
        } if ($e->expires_at?->isPast()) {
            return AdminApiResponse::error($r, 'This report export has expired.', 'REPORT_EXPORT_EXPIRED', 410);
        } if (! $e->storage_path || ! Storage::disk(config('filesystems.default'))->exists($e->storage_path)) {
            abort(404);
        }$e->forceFill(['downloaded_at' => now()])->save();
        $type = $e->format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';

        return response(Storage::disk(config('filesystems.default'))->get($e->storage_path), 200, ['Content-Type' => $type]);
    }

    private function authorize(Request $r, AdminSavedReport $rep): void
    {
        if ((int) $rep->admin_user_id !== (int) $r->user()->id && ! $rep->team_shared) {
            abort(404);
        }
    }
}
