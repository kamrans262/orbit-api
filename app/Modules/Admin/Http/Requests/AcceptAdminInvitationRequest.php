<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Validation\Rules\Password;

final class AcceptAdminInvitationRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'invitation_token' => ['required', 'string', 'min:40', 'max:512'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'max:1024', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
    }
}
