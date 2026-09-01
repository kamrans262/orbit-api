<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminUser;
use App\Modules\Admin\Http\Requests\ListAdminsRequest;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminsController
{
    public function __invoke(ListAdminsRequest $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 25), 100));
        $query = AdminUser::query()->with('roles:id,slug,name');

        if (is_string($request->query('search')) && trim((string) $request->query('search')) !== '') {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(fn ($builder) => $builder->where('email', 'like', $search)->orWhere('name', 'like', $search));
        }
        if (is_string($request->query('status')) && $request->query('status') !== '') {
            $query->where('status', (string) $request->query('status'));
        }
        if (is_string($request->query('role')) && $request->query('role') !== '') {
            $role = (string) $request->query('role');
            $query->whereHas('roles', fn ($builder) => $builder->where('slug', $role));
        }

        $paginator = $query->orderBy('email')->paginate($limit);
        $data = collect($paginator->items())->map(fn (AdminUser $admin): array => [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'status' => $admin->status->value,
            'roles' => $admin->roles->pluck('slug')->values(),
            'mfa_enabled' => $admin->mfa_confirmed_at !== null,
            'access_expires_at' => $admin->access_expires_at?->toIso8601String(),
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
            'created_at' => $admin->created_at?->toIso8601String(),
        ])->values();

        return AdminApiResponse::success($request, [
            'items' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
