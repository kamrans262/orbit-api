<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AccountDeletionRequest;
use App\Models\DataExportRequest;
use App\Models\PrivacyRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PrivacyDirectoryService
{
    public function requests(array $filters): LengthAwarePaginator
    {
        $query = PrivacyRequest::query();

        foreach (['type', 'status', 'identity_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['assigned_admin_id'])) {
            $query->where('assigned_admin_id', (int) $filters['assigned_admin_id']);
        }

        if (filter_var($filters['unassigned'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNull('assigned_admin_id');
        }

        if (filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNotNull('deadline_at')
                ->where('deadline_at', '<', now())
                ->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
        }

        return $query->latest('created_at')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
    }

    public function exports(array $filters): LengthAwarePaginator
    {
        $query = DataExportRequest::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (filter_var($filters['expired'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
        }

        return $query->latest('requested_at')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
    }

    public function deletions(array $filters): LengthAwarePaginator
    {
        $query = AccountDeletionRequest::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (filter_var($filters['due'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNotNull('scheduled_for')->where('scheduled_for', '<=', now());
        }

        return $query->latest('requested_at')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
    }
}
