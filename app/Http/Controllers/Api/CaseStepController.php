<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCase;
use App\Services\CasePresenter;
use App\Services\CaseWorkflow;
use App\Services\ProcessCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CaseStepController extends Controller
{
    /** Cliente envia a etapa atual (campos + documentos). */
    public function submit(
        Request $request,
        ImmigrationCase $case,
        int $number,
        ProcessCatalog $catalog,
        CaseWorkflow $workflow,
        CasePresenter $presenter,
    ): JsonResponse {
        abort_unless($case->user_id === $request->user()->id, 403, 'Este processo não pertence a você.');

        $step = $catalog->step($case->visa_type, $number);
        abort_if($step === null, 404, 'Etapa inexistente.');

        $rules = [];
        foreach ($catalog->fieldRules($step) as $key => $fieldRules) {
            $rules["data.{$key}"] = $fieldRules;
        }
        $rules = array_merge($rules, $catalog->documentRules($step));
        $rules['data'] = ['nullable', 'array'];

        $validated = $request->validate($rules);

        // Documentos obrigatórios: enviados agora ou já existentes nesta etapa.
        $caseStep = $case->steps()->where('number', $number)->first();
        $uploadedKeys = array_keys($request->file('files', []));
        $existingKeys = $caseStep?->documents()->pluck('document_key')->all() ?? [];

        $missing = collect($step['documents'] ?? [])
            ->filter(fn (array $doc) => $doc['required'])
            ->reject(fn (array $doc) => in_array($doc['key'], $uploadedKeys, true) || in_array($doc['key'], $existingKeys, true))
            ->pluck('label')
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'files' => ['Envie os documentos obrigatórios: '.implode(', ', $missing).'.'],
            ]);
        }

        $workflow->submitStep(
            $case,
            $number,
            $validated['data'] ?? [],
            $request->file('files', []),
            $request->user(),
        );

        return response()->json([
            'message' => 'Etapa enviada para análise do agente.',
            ...$presenter->full($case->fresh()),
        ]);
    }

    /** Agente aprova ou recusa a etapa enviada. */
    public function review(
        Request $request,
        ImmigrationCase $case,
        int $number,
        CaseWorkflow $workflow,
        CasePresenter $presenter,
    ): JsonResponse {
        abort_unless($case->agent_id === $request->user()->id, 403, 'Você não é o agente responsável por este processo.');

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $workflow->reviewStep(
            $case,
            $number,
            $data['decision'] === 'approve',
            $data['reason'] ?? null,
            $request->user(),
        );

        return response()->json([
            'message' => $data['decision'] === 'approve' ? 'Etapa aprovada.' : 'Etapa recusada. O cliente foi notificado.',
            ...$presenter->full($case->fresh()),
        ]);
    }
}
