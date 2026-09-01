<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class CreateAdminInvitationRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'role_slugs' => ['required', 'array', 'min:1', 'max:8'],
            'role_slugs.*' => ['required', 'string', 'distinct', 'exists:admin_roles,slug'],
            'access_expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
