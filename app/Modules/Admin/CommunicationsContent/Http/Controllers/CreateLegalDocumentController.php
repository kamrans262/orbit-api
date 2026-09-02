<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\LegalDocument;
use App\Modules\Admin\CommunicationsContent\Exceptions\CommunicationsContentException;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateLegalDocumentController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['document_type' => ['required', Rule::in(['terms', 'privacy_policy', 'safety_disclosure', 'subscription_terms', 'regional_notice'])], 'version' => ['required', 'string', 'max:40'], 'regions' => ['nullable', 'array'], 'requires_reacceptance' => ['nullable', 'boolean'], 'effective_at' => ['nullable', 'date']]);
        if (LegalDocument::query()->where('document_type', $d['document_type'])->where('version', $d['version'])->exists()) {
            throw new CommunicationsContentException('LEGAL_VERSION_EXISTS', 'That legal document version already exists.', 409);
        } $m = LegalDocument::query()->create([...$d, 'status' => 'draft', 'requires_reacceptance' => (bool) ($d['requires_reacceptance'] ?? false), 'created_by_admin_id' => $request->user()->id]);
        $audit->write('legal.created', $request->user(), $request->attributes->get('admin_session'), 'legal', $m->id, request: $request);

        return AdminApiResponse::success($request, $m->toArray(), 201);
    }
}
