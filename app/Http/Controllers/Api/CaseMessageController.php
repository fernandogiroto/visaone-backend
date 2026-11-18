<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseMessage;
use App\Models\ImmigrationCase;
use App\Services\CasePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Painel de mensagens (assíncrono): cliente deixa recado, agente responde depois.
 */
class CaseMessageController extends Controller
{
    public function store(Request $request, ImmigrationCase $case, CasePresenter $presenter): JsonResponse
    {
        abort_unless($presenter->canView($request->user(), $case), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
            'request' => ['nullable', 'array'],
            'request.label' => ['required_with:request', 'string', 'max:150'],
            'request.field' => ['required_with:request', 'string', 'max:60'],
            'request.input' => ['nullable', 'in:text,textarea,file'],
        ]);

        // pedidos com campo de resposta são exclusivos do agente responsável
        $isRequest = ! empty($data['request'] ?? null);
        if ($isRequest && $case->agent_id !== $request->user()->id) {
            abort(403, 'Somente o agente responsável pode criar pedidos.');
        }

        $case->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'kind' => $isRequest ? CaseMessage::KIND_REQUEST : CaseMessage::KIND_TEXT,
            'meta' => $isRequest ? [
                'label' => $data['request']['label'],
                'field' => $data['request']['field'],
                'input' => $data['request']['input'] ?? 'text',
                'answer' => null,
                'answered_at' => null,
            ] : null,
        ]);

        return response()->json([
            'message' => 'Mensagem enviada.',
            'messages' => $presenter->messages($case),
        ], 201);
    }

    /** Edita o texto de uma mensagem. Só quem escreveu pode editar. */
    public function update(Request $request, ImmigrationCase $case, CaseMessage $message, CasePresenter $presenter): JsonResponse
    {
        abort_unless($message->immigration_case_id === $case->id, 404);
        abort_unless($message->user_id === $request->user()->id, 403, 'Você só pode editar as suas mensagens.');

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $message->update(['body' => $data['body']]);

        return response()->json([
            'message' => 'Mensagem editada.',
            'messages' => $presenter->messages($case),
        ]);
    }

    /** Exclui uma mensagem. Só quem escreveu pode excluir. */
    public function destroy(Request $request, ImmigrationCase $case, CaseMessage $message, CasePresenter $presenter): JsonResponse
    {
        abort_unless($message->immigration_case_id === $case->id, 404);
        abort_unless($message->user_id === $request->user()->id, 403, 'Você só pode excluir as suas mensagens.');

        if (! empty($message->meta['file']['path'])) {
            Storage::disk('local')->delete($message->meta['file']['path']);
        }

        $message->delete();

        return response()->json([
            'message' => 'Mensagem excluída.',
            'messages' => $presenter->messages($case),
        ]);
    }

    /** Baixa o arquivo enviado pelo cliente em um pedido (cliente dono ou agente responsável). */
    public function file(Request $request, ImmigrationCase $case, CaseMessage $message, CasePresenter $presenter): BinaryFileResponse
    {
        abort_unless($presenter->canView($request->user(), $case), 403);
        abort_unless($message->immigration_case_id === $case->id, 404);

        $file = $message->meta['file'] ?? null;
        abort_unless($file, 404);

        return response()->download(Storage::disk('local')->path($file['path']), $file['name']);
    }

    /** Cliente responde a um pedido com campo de resposta. */
    public function respond(Request $request, ImmigrationCase $case, CaseMessage $message, CasePresenter $presenter): JsonResponse
    {
        abort_unless($case->user_id === $request->user()->id, 403, 'Somente o cliente pode responder.');
        abort_unless($message->immigration_case_id === $case->id, 404);
        abort_unless($message->kind === CaseMessage::KIND_REQUEST, 422, 'Esta mensagem não aceita resposta.');

        $meta = $message->meta ?? [];
        if (! empty($meta['answer'])) {
            abort(422, 'Este pedido já foi respondido.');
        }

        if (($meta['input'] ?? 'text') === 'file') {
            $data = $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);

            $upload = $data['file'];
            $path = $upload->storeAs(
                "cases/{$case->uuid}/messages/{$message->id}",
                now()->format('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$upload->extension(),
                'local',
            );

            $meta['answer'] = $upload->getClientOriginalName();
            $meta['file'] = [
                'path' => $path,
                'name' => $upload->getClientOriginalName(),
                'size' => $upload->getSize(),
                'mime' => $upload->getMimeType() ?? 'application/octet-stream',
            ];
        } else {
            $data = $request->validate([
                'value' => ['required', 'string', 'min:1', 'max:1000'],
            ]);
            $meta['answer'] = $data['value'];
        }

        $meta['answered_at'] = now()->toIso8601String();
        $message->update(['meta' => $meta]);

        return response()->json([
            'message' => 'Resposta enviada.',
            'messages' => $presenter->messages($case),
        ]);
    }
}
