<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseDocument;
use App\Models\ImmigrationCase;
use App\Services\CasePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Visualização compartilhada do caso (cliente dono ou agente responsável).
 */
class CaseController extends Controller
{
    public function show(Request $request, ImmigrationCase $case, CasePresenter $presenter): JsonResponse
    {
        abort_unless($presenter->canView($request->user(), $case), 403, 'Você não tem acesso a este processo.');

        return response()->json($presenter->full($case));
    }

    public function download(Request $request, ImmigrationCase $case, CaseDocument $document, CasePresenter $presenter): BinaryFileResponse
    {
        abort_unless($presenter->canView($request->user(), $case), 403);
        abort_unless($document->immigration_case_id === $case->id, 404);

        return response()->download(
            Storage::disk('local')->path($document->path),
            $document->original_name,
        );
    }
}
