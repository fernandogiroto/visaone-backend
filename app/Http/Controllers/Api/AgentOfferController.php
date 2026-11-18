<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseAssignment;
use App\Services\CaseWorkflow;
use App\Services\ProcessCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentOfferController extends Controller
{
    /** Lista de casos recebidos (aguardando aceite do agente). */
    public function index(Request $request, ProcessCatalog $catalog): JsonResponse
    {
        $offers = CaseAssignment::query()
            ->with('immigrationCase')
            ->where('agent_id', $request->user()->id)
            ->where('status', CaseAssignment::OFFERED)
            ->latest('offered_at')
            ->get()
            ->map(fn (CaseAssignment $a) => [
                'id' => $a->id,
                'offered_at' => $a->offered_at->toIso8601String(),
                'case' => [
                    'first_name' => $a->immigrationCase->first_name,
                    'last_name' => $a->immigrationCase->last_name,
                    'visa_type' => $a->immigrationCase->visa_type,
                    'visa_label' => config("processes.visa_types.{$a->immigrationCase->visa_type}.label"),
                    'origin_country' => $a->immigrationCase->origin_country,
                    'destination_country' => $a->immigrationCase->destination_country,
                    'total_steps' => $catalog->stepCount($a->immigrationCase->visa_type),
                ],
            ]);

        return response()->json(['items' => $offers]);
    }

    public function accept(Request $request, CaseAssignment $assignment, CaseWorkflow $workflow): JsonResponse
    {
        abort_unless($assignment->agent_id === $request->user()->id, 403);
        abort_unless($assignment->status === CaseAssignment::OFFERED, 422, 'Esta oferta não está mais disponível.');

        $case = $workflow->acceptOffer($assignment);

        return response()->json([
            'message' => 'Caso aceito. Agora você pode analisar as etapas.',
            'case_uuid' => $case->uuid,
        ]);
    }

    public function decline(Request $request, CaseAssignment $assignment, CaseWorkflow $workflow): JsonResponse
    {
        abort_unless($assignment->agent_id === $request->user()->id, 403);
        abort_unless($assignment->status === CaseAssignment::OFFERED, 422, 'Esta oferta não está mais disponível.');

        $next = $workflow->declineOffer($assignment);

        return response()->json([
            'message' => $next
                ? 'Caso recusado. Ele foi encaminhado a outro agente.'
                : 'Caso recusado. Não há mais agentes disponíveis no momento.',
        ]);
    }
}
