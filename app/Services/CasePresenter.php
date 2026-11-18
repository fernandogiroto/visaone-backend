<?php

namespace App\Services;

use App\Models\AgentProfile;
use App\Models\CaseMessage;
use App\Models\CaseStep;
use App\Models\ImmigrationCase;
use App\Models\User;

/**
 * Monta o JSON que o painel (cliente e agente) consome.
 */
class CasePresenter
{
    public function __construct(private ProcessCatalog $catalog)
    {
    }

    public function canView(User $user, ImmigrationCase $case): bool
    {
        return $user->id === $case->user_id || $user->id === $case->agent_id;
    }

    /** Resumo do caso (sem etapas). */
    public function summary(ImmigrationCase $case): array
    {
        $case->loadMissing(['agent.agentProfile', 'client']);

        return [
            'client' => [
                'name' => $case->client?->name,
                'photo_url' => $case->client?->photo_path ? asset('storage/'.$case->client->photo_path) : null,
            ],
            'uuid' => $case->uuid,
            'first_name' => $case->first_name,
            'last_name' => $case->last_name,
            'full_name' => trim("{$case->first_name} {$case->last_name}"),
            'visa_type' => $case->visa_type,
            'visa_label' => config("processes.visa_types.{$case->visa_type}.label"),
            'origin_country' => $case->origin_country,
            'destination_country' => $case->destination_country,
            'status' => $case->status,
            'current_step' => $case->current_step,
            'total_steps' => $this->catalog->stepCount($case->visa_type),
            'accepted_at' => optional($case->accepted_at)->toIso8601String(),
            'completed_at' => optional($case->completed_at)->toIso8601String(),
            'agent' => $case->agent ? $this->agent($case->agent) : null,
        ];
    }

    /** Perfil resumido do agente (sem dados sensíveis). */
    public function agent(User $agent): array
    {
        /** @var AgentProfile|null $profile */
        $profile = $agent->agentProfile;

        return [
            'id' => $agent->id,
            'name' => $agent->name,
            // URL completa (placeholder externo), caminho em public/ ou arquivo no disco público
            'photo_url' => $this->photoUrl($profile?->photo_path),
            'age' => $profile?->age,
            'job_title' => $profile?->job_title,
            'specialty' => $profile?->specialty,
            'years_experience' => $profile?->years_experience,
            'languages' => $profile?->languages,
            'bio' => $profile?->bio,
        ];
    }

    private function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset(str_starts_with($path, 'avatars/') ? $path : 'storage/'.$path);
    }

    /** Etapas com campos, documentos enviados e status. */
    public function steps(ImmigrationCase $case): array
    {
        $caseSteps = $case->steps()->with('documents')->get()->keyBy('number');

        return collect($this->catalog->steps($case->visa_type))
            ->map(function (array $definition, int $index) use ($caseSteps, $case) {
                $number = $index + 1;
                /** @var CaseStep|null $step */
                $step = $caseSteps->get($number);

                return [
                    'number' => $number,
                    'key' => $definition['key'],
                    'title' => $definition['title'],
                    'description' => $definition['description'] ?? null,
                    'fields' => $definition['fields'],
                    'groups' => $definition['groups'] ?? [],
                    'document_groups' => $definition['document_groups'] ?? [],
                    'documents' => collect($definition['documents'] ?? [])->map(function (array $doc) use ($step, $case) {
                        $uploaded = $step?->documents
                            ->where('document_key', $doc['key'])
                            ->values()
                            ->map(fn ($d) => [
                                'id' => $d->id,
                                'name' => $d->original_name,
                                'size' => $d->size,
                                'url' => route('cases.documents.download', ['case' => $case->uuid, 'document' => $d->id]),
                            ])
                            ->all() ?? [];

                        return $doc + ['uploaded' => $uploaded];
                    })->values()->all(),
                    'status' => $step?->status ?? CaseStep::PENDING,
                    'data' => $step?->data ?? new \stdClass,
                    'rejection_reason' => $step?->rejection_reason,
                    'submitted_at' => optional($step?->submitted_at)->toIso8601String(),
                    'reviewed_at' => optional($step?->reviewed_at)->toIso8601String(),
                    'is_current' => $number === $case->current_step && $case->status !== ImmigrationCase::STATUS_COMPLETED,
                    'is_locked' => $number > $case->current_step,
                ];
            })
            ->values()
            ->all();
    }

    /** Mensagens do painel (não é chat ao vivo). */
    public function messages(ImmigrationCase $case): array
    {
        return $case->messages()
            ->with(['author:id,name,role', 'author.agentProfile'])
            ->latest()
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (CaseMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'kind' => $m->kind,
                'author_id' => $m->user_id,
                'edited' => $m->updated_at->gt($m->created_at),
                'updated_at' => $m->updated_at->toIso8601String(),
                'meta' => $m->meta ? array_merge($m->meta, [
                    'file_url' => isset($m->meta['file'])
                        ? route('cases.messages.file', ['case' => $m->immigrationCase?->uuid ?? $case->uuid, 'message' => $m->id])
                        : null,
                ]) : null,
                'author' => [
                    'id' => $m->author?->id,
                    'name' => $m->author?->name,
                    'role' => $m->author?->role,
                    'photo_url' => $m->author?->photo_path
                        ? asset('storage/'.$m->author->photo_path)
                        : $this->photoUrl($m->author?->agentProfile?->photo_path),
                ],
                'created_at' => $m->created_at->toIso8601String(),
            ])
            ->all();
    }

    /** Payload completo usado pela tela do caso. */
    public function full(ImmigrationCase $case): array
    {
        return [
            'case' => $this->summary($case),
            'steps' => $this->steps($case),
            'messages' => $this->messages($case),
        ];
    }
}
