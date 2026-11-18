<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCase;
use App\Services\CasePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCaseController extends Controller
{
    /** Casos aceitos pelo agente logado. */
    public function index(Request $request, CasePresenter $presenter): JsonResponse
    {
        $cases = ImmigrationCase::query()
            ->with('agent.agentProfile')
            ->where('agent_id', $request->user()->id)
            ->latest('accepted_at')
            ->get()
            ->map(fn (ImmigrationCase $case) => $presenter->summary($case));

        return response()->json(['items' => $cases]);
    }
}
