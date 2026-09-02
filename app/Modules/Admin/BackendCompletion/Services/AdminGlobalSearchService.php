<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Services;

use App\Models\AdminUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminGlobalSearchService
{
    public function search(AdminUser $admin, string $term, int $limit = 8): array
    {
        $limit = min(max($limit, 1), 20);
        $permissions = $this->permissions($admin);
        $result = [];

        if ($permissions->has('users.view')) {
            $result['users'] = $this->users($term, $limit);
        }
        if ($permissions->has('circles.view')) {
            $result['circles'] = $this->circles($term, $limit);
        }
        if ($permissions->has('users.devices.view')) {
            $result['devices'] = $this->devices($term, $limit, $permissions->has('sensitive_fields.reveal'));
        }
        if ($permissions->has('sos.view')) {
            $result['sos'] = $this->simpleIdSearch('sos_events', $term, $limit, 'sos');
        }
        if ($permissions->has('reports.view')) {
            $result['reports'] = $this->reports($term, $limit);
        }
        if ($permissions->has('support.view')) {
            $result['support'] = $this->support($term, $limit);
        }
        if ($permissions->has('subscriptions.view')) {
            $result['subscriptions'] = $this->subscriptions($term, $limit);
        }
        if ($permissions->has('payments.view')) {
            $result['payments'] = $this->payments($term, $limit, $permissions->has('sensitive_fields.reveal'));
        }
        if ($permissions->has('audit.view')) {
            $result['audit'] = $this->audit($term, $limit);
        }
        if ($permissions->has('incidents.view')) {
            $result['incidents'] = $this->incidents($term, $limit);
        }

        return [
            'query' => $term,
            'results' => $result,
            'commands' => $this->commands($permissions),
        ];
    }

    private function permissions(AdminUser $admin): Collection
    {
        return $admin->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->flip();
    }

    private function users(string $term, int $limit): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->where(function (Builder $query) use ($term): void {
                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
                $like = $this->like($term);
                $query->orWhere('name', 'like', $like)->orWhere('email', 'like', $like);
            })
            ->limit($limit)
            ->get(['id', 'name', 'email'])
            ->map(fn ($row): array => [
                'type' => 'user',
                'id' => (string) $row->id,
                'label' => $row->name ?: 'Orbit user #'.$row->id,
                'secondary' => $this->maskEmail((string) $row->email),
                'deep_link' => '/admin/users/'.$row->id,
            ])->all();
    }

    private function circles(string $term, int $limit): array
    {
        if (! Schema::hasTable('circles')) {
            return [];
        }

        $like = $this->like($term);

        return DB::table('circles')
            ->where(fn (Builder $q) => $q->where('id', 'like', $like)->orWhere('name', 'like', $like))
            ->limit($limit)
            ->get(['id', 'name', 'archived_at'])
            ->map(fn ($row): array => [
                'type' => 'circle', 'id' => (string) $row->id, 'label' => (string) $row->name,
                'secondary' => $row->archived_at ? 'Archived Circle' : 'Circle',
                'deep_link' => '/admin/circles/'.$row->id,
            ])->all();
    }

    private function devices(string $term, int $limit, bool $reveal): array
    {
        if (! Schema::hasTable('devices')) {
            return [];
        }

        $like = $this->like($term);
        $nameColumn = Schema::hasColumn('devices', 'device_name') ? 'device_name' : 'name';

        return DB::table('devices')
            ->where(function (Builder $q) use ($like, $nameColumn): void {
                $q->where('id', 'like', $like)->orWhere('client_device_id', 'like', $like)->orWhere($nameColumn, 'like', $like);
            })
            ->limit($limit)
            ->get(['id', 'user_id', 'client_device_id', $nameColumn])
            ->map(fn ($row): array => [
                'type' => 'device',
                'id' => $reveal ? (string) $row->id : $this->maskToken((string) $row->id),
                'label' => (string) ($row->{$nameColumn} ?: 'Device'),
                'secondary' => 'User #'.$row->user_id.' · '.($reveal ? (string) $row->client_device_id : $this->maskToken((string) $row->client_device_id)),
                'deep_link' => '/admin/users/'.$row->user_id.'/devices',
                'sensitive_masked' => ! $reveal,
            ])->all();
    }

    private function reports(string $term, int $limit): array
    {
        if (! Schema::hasTable('moderation_reports')) {
            return [];
        }
        $like = $this->like($term);

        return DB::table('moderation_reports')
            ->where(fn (Builder $q) => $q->where('id', 'like', $like)->orWhere('target_id', 'like', $like)->orWhere('reason', 'like', $like))
            ->limit($limit)
            ->get(['id', 'target_type', 'target_id', 'reason', 'status'])
            ->map(fn ($row): array => [
                'type' => 'report', 'id' => (string) $row->id,
                'label' => ucfirst((string) $row->reason).' report',
                'secondary' => $row->target_type.' '.$row->target_id.' · '.$row->status,
                'deep_link' => '/admin/reports/'.$row->id,
            ])->all();
    }

    private function support(string $term, int $limit): array
    {
        if (! Schema::hasTable('support_tickets')) {
            return [];
        }
        $like = $this->like($term);

        return DB::table('support_tickets')
            ->where(fn (Builder $q) => $q->where('id', 'like', $like)->orWhere('subject', 'like', $like))
            ->limit($limit)
            ->get(['id', 'user_id', 'subject', 'status'])
            ->map(fn ($row): array => [
                'type' => 'support_ticket', 'id' => (string) $row->id,
                'label' => mb_substr((string) $row->subject, 0, 120),
                'secondary' => 'User #'.$row->user_id.' · '.$row->status,
                'deep_link' => '/admin/support/'.$row->id,
            ])->all();
    }

    private function subscriptions(string $term, int $limit): array
    {
        if (! Schema::hasTable('user_subscriptions')) {
            return [];
        }
        $like = $this->like($term);
        $query = DB::table('user_subscriptions as s')->leftJoin('billing_plans as p', 'p.id', '=', 's.plan_id');
        $query->where(function (Builder $q) use ($term, $like): void {
            $q->where('s.id', 'like', $like)->orWhere('p.slug', 'like', $like);
            if (ctype_digit($term)) {
                $q->orWhere('s.user_id', (int) $term);
            }
        });

        return $query->limit($limit)->get(['s.id', 's.user_id', 's.status', 'p.slug as plan_slug'])
            ->map(fn ($row): array => [
                'type' => 'subscription', 'id' => (string) $row->id,
                'label' => ucfirst((string) ($row->plan_slug ?: 'Unknown')).' subscription',
                'secondary' => 'User #'.$row->user_id.' · '.$row->status,
                'deep_link' => '/admin/subscriptions/'.$row->id,
            ])->all();
    }

    private function payments(string $term, int $limit, bool $reveal): array
    {
        if (! Schema::hasTable('payment_transactions')) {
            return [];
        }
        $like = $this->like($term);

        return DB::table('payment_transactions')
            ->where(function (Builder $q) use ($term, $like): void {
                $q->where('id', 'like', $like)->orWhere('provider_transaction_ref', 'like', $like);
                if (ctype_digit($term)) {
                    $q->orWhere('user_id', (int) $term);
                }
            })
            ->limit($limit)
            ->get(['id', 'user_id', 'provider_transaction_ref', 'amount_minor', 'currency', 'status'])
            ->map(fn ($row): array => [
                'type' => 'payment', 'id' => (string) $row->id,
                'label' => $row->currency.' '.$row->amount_minor.' · '.$row->status,
                'secondary' => $reveal ? (string) $row->provider_transaction_ref : $this->maskToken((string) $row->provider_transaction_ref),
                'deep_link' => '/admin/payments/'.$row->id,
                'sensitive_masked' => ! $reveal,
            ])->all();
    }

    private function audit(string $term, int $limit): array
    {
        if (! Schema::hasTable('admin_audit_logs')) {
            return [];
        }
        $like = $this->like($term);

        return DB::table('admin_audit_logs')
            ->where(fn (Builder $q) => $q->where('id', 'like', $like)->orWhere('request_id', 'like', $like)->orWhere('action', 'like', $like))
            ->limit($limit)
            ->get(['id', 'action', 'target_type', 'target_id', 'request_id', 'occurred_at'])
            ->map(fn ($row): array => [
                'type' => 'audit', 'id' => (string) $row->id, 'label' => (string) $row->action,
                'secondary' => trim((string) $row->target_type.' '.(string) $row->target_id),
                'request_id' => $row->request_id,
                'deep_link' => '/admin/audit?request_id='.urlencode((string) $row->request_id),
            ])->all();
    }

    private function incidents(string $term, int $limit): array
    {
        if (! Schema::hasTable('system_incidents')) {
            return [];
        }
        $like = $this->like($term);

        return DB::table('system_incidents')
            ->where(fn (Builder $q) => $q->where('id', 'like', $like)->orWhere('title', 'like', $like)->orWhere('service', 'like', $like))
            ->limit($limit)
            ->get(['id', 'title', 'service', 'severity', 'status'])
            ->map(fn ($row): array => [
                'type' => 'system_incident', 'id' => (string) $row->id, 'label' => (string) $row->title,
                'secondary' => $row->service.' · '.$row->severity.' · '.$row->status,
                'deep_link' => '/admin/system/incidents/'.$row->id,
            ])->all();
    }

    private function simpleIdSearch(string $table, string $term, int $limit, string $type): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }
        $like = $this->like($term);

        return DB::table($table)->where('id', 'like', $like)->limit($limit)->get(['id', 'user_id', 'circle_id', 'status'])
            ->map(fn ($row): array => [
                'type' => $type, 'id' => (string) $row->id,
                'label' => 'SOS incident '.mb_substr((string) $row->id, 0, 12),
                'secondary' => 'User #'.$row->user_id.' · '.$row->status,
                'deep_link' => '/admin/sos/'.$row->id,
            ])->all();
    }

    private function commands(Collection $permissions): array
    {
        $commands = [];
        if ($permissions->has('reports.view')) {
            $commands[] = ['key' => 'open_reports', 'label' => 'Open report queue', 'deep_link' => '/admin/reports'];
        }
        if ($permissions->has('announcements.manage')) {
            $commands[] = ['key' => 'create_announcement', 'label' => 'Create announcement', 'deep_link' => '/admin/announcements/new'];
        }
        if ($permissions->has('operations.view')) {
            $commands[] = ['key' => 'system_health', 'label' => 'View system health', 'deep_link' => '/admin/system/health'];
        }
        if ($permissions->has('sos.view')) {
            $commands[] = ['key' => 'active_sos', 'label' => 'Open SOS command center', 'deep_link' => '/admin/sos'];
        }

        return $commands;
    }

    private function like(string $term): string
    {
        return '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '') {
            return $this->maskToken($email);
        }

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function maskToken(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) <= 8) {
            return '***';
        }

        return mb_substr($value, 0, 4).'…'.mb_substr($value, -4);
    }
}
