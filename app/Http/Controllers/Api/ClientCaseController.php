<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCase;
use App\Services\CaseWorkflow;
use App\Services\CasePresenter;
use App\Services\ProcessCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientCaseController extends Controller
{
    /** Lista dos processos do cliente logado (para o menu "Meus processos"). */
    public function index(Request $request, ProcessCatalog $catalog): JsonResponse
    {
        $items = $request->user()->clientCases()->get()->map(fn (ImmigrationCase $c) => [
            'uuid' => $c->uuid,
            'visa_label' => config("processes.visa_types.{$c->visa_type}.label"),
            'origin_country' => $c->origin_country,
            'destination_country' => $c->destination_country,
            'status' => $c->status,
            'current_step' => $c->current_step,
            'total_steps' => $catalog->stepCount($c->visa_type),
            'created_at' => $c->created_at->toIso8601String(),
        ]);

        return response()->json(['items' => $items]);
    }

    /** Processo mais recente do cliente (compatibilidade com a tela antiga). */
    public function show(Request $request, CasePresenter $presenter): JsonResponse
    {
        $case = $request->user()->latestCase();

        if (! $case) {
            return response()->json(['message' => 'Nenhum processo encontrado para este usuário.'], 404);
        }

        return response()->json($presenter->full($case));
    }

    /** Cliente abre um novo processo (já logado). */
    public function store(Request $request, ProcessCatalog $catalog, CaseWorkflow $workflow, CasePresenter $presenter): JsonResponse
    {
        $user = $request->user();
        $visaKeys = array_keys(config('processes.visa_types'));
        $countryCodes = collect(config('processes.countries'))->pluck('code')->all();

        $data = $request->validate([
            'visa_type' => ['required', Rule::in($visaKeys)],
            'origin_country' => ['required', Rule::in($countryCodes)],
            'destination_country' => ['required', Rule::in($countryCodes)],
        ]);

        abort_unless($catalog->isVisaTypeEnabled($data['visa_type']), 422, 'Este tipo de processo ainda não está disponível.');

        $case = ImmigrationCase::create([
            'first_name' => $user->name,
            'last_name' => '',
            'user_id' => $user->id,
            'visa_type' => $data['visa_type'],
            'origin_country' => $data['origin_country'],
            'destination_country' => $data['destination_country'],
            'status' => ImmigrationCase::STATUS_WAITING_AGENT,
        ]);

        $workflow->ensureSteps($case);
        $workflow->offerToRandomAgent($case->refresh());

        return response()->json([
            'message' => 'Processo criado. Um agente vai analisar seu caso em breve.',
            'uuid' => $case->uuid,
        ], 201);
    }
}
