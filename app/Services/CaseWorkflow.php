<?php

namespace App\Services;

use App\Models\CaseAssignment;
use App\Models\CaseDocument;
use App\Models\CaseStep;
use App\Models\ImmigrationCase;
use App\Models\User;
use App\Notifications\CaseAcceptedByAgent;
use App\Notifications\CaseOfferedToAgent;
use App\Notifications\StepReviewed;
use App\Notifications\StepSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Regras de negócio: distribuição de casos para agentes e fluxo de etapas.
 */
class CaseWorkflow
{
    public function __construct(private ProcessCatalog $catalog)
    {
    }

    /* ------------------------------------------------------------------
     | Distribuição de caso (oferta aleatória para agentes)
     |------------------------------------------------------------------ */

    /**
     * Oferece o caso a um agente aleatório que ainda não recusou/recebeu a oferta.
     */
    public function offerToRandomAgent(ImmigrationCase $case): ?CaseAssignment
    {
        if ($case->agent_id !== null) {
            return null;
        }

        $alreadyOffered = $case->assignments()->pluck('agent_id');

        $agent = User::query()
            ->where('role', User::ROLE_AGENT)
            ->whereNotIn('id', $alreadyOffered)
            ->inRandomOrder()
            ->first();

        if (! $agent) {
            $case->update(['status' => ImmigrationCase::STATUS_NO_AGENT]);

            return null;
        }

        $assignment = $case->assignments()->create([
            'agent_id' => $agent->id,
            'status' => CaseAssignment::OFFERED,
            'offered_at' => now(),
        ]);

        $case->update(['status' => ImmigrationCase::STATUS_WAITING_AGENT]);

        $agent->notify(new CaseOfferedToAgent($case));

        return $assignment;
    }

    public function declineOffer(CaseAssignment $assignment): ?CaseAssignment
    {
        $assignment->update([
            'status' => CaseAssignment::DECLINED,
            'responded_at' => now(),
        ]);

        return $this->offerToRandomAgent($assignment->immigrationCase);
    }

    public function acceptOffer(CaseAssignment $assignment): ImmigrationCase
    {
        return DB::transaction(function () use ($assignment) {
            $case = $assignment->immigrationCase()->lockForUpdate()->first();

            if ($case->agent_id !== null) {
                throw ValidationException::withMessages([
                    'assignment' => ['Este caso já foi aceito por outro agente.'],
                ]);
            }

            $assignment->update([
                'status' => CaseAssignment::ACCEPTED,
                'responded_at' => now(),
            ]);

            $case->assignments()
                ->where('id', '!=', $assignment->id)
                ->where('status', CaseAssignment::OFFERED)
                ->update(['status' => CaseAssignment::CLOSED, 'responded_at' => now()]);

            $case->update([
                'agent_id' => $assignment->agent_id,
                'status' => ImmigrationCase::STATUS_IN_PROGRESS,
                'accepted_at' => now(),
            ]);

            $this->ensureSteps($case);

            if ($case->client) {
                $case->client->notify(new CaseAcceptedByAgent($case));
            }

            return $case->fresh(['agent.agentProfile']);
        });
    }

    /* ------------------------------------------------------------------
     | Etapas
     |------------------------------------------------------------------ */

    public function ensureSteps(ImmigrationCase $case): void
    {
        foreach ($this->catalog->steps($case->visa_type) as $index => $step) {
            CaseStep::firstOrCreate(
                ['immigration_case_id' => $case->id, 'number' => $index + 1],
                ['step_key' => $step['key'], 'status' => CaseStep::PENDING],
            );
        }
    }

    /**
     * Cliente envia (ou reenvia, após rejeição) a etapa atual.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile>  $files
     */
    public function submitStep(ImmigrationCase $case, int $number, array $data, array $files, User $by): CaseStep
    {
        $step = $this->catalog->step($case->visa_type, $number);

        if ($number !== $case->current_step) {
            throw ValidationException::withMessages([
                'step' => ['Esta etapa ainda não está liberada para envio.'],
            ]);
        }

        $caseStep = $case->steps()->where('number', $number)->firstOrFail();

        if (! in_array($caseStep->status, [CaseStep::PENDING, CaseStep::REJECTED], true)) {
            throw ValidationException::withMessages([
                'step' => ['Esta etapa já foi enviada e aguarda análise do agente.'],
            ]);
        }

        return DB::transaction(function () use ($case, $caseStep, $step, $data, $files, $by, $number) {
            $caseStep->update([
                'status' => CaseStep::SUBMITTED,
                'data' => $data,
                'rejection_reason' => null,
                'submitted_at' => now(),
            ]);

            foreach ($files as $key => $file) {
                $this->storeDocument($case, $caseStep, $key, $file, $by);
            }

            if ($case->agent) {
                $case->agent->notify(new StepSubmitted($case, $number, $step['title']));
            }

            return $caseStep->fresh();
        });
    }

    /** Agente aprova ou rejeita a etapa enviada. */
    public function reviewStep(ImmigrationCase $case, int $number, bool $approve, ?string $reason, User $agent): CaseStep
    {
        $caseStep = $case->steps()->where('number', $number)->firstOrFail();

        if ($caseStep->status !== CaseStep::SUBMITTED) {
            throw ValidationException::withMessages([
                'step' => ['A etapa não está aguardando análise.'],
            ]);
        }

        $step = $this->catalog->step($case->visa_type, $number);

        return DB::transaction(function () use ($case, $caseStep, $step, $approve, $reason, $agent, $number) {
            $caseStep->update([
                'status' => $approve ? CaseStep::APPROVED : CaseStep::REJECTED,
                'rejection_reason' => $approve ? null : $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $agent->id,
            ]);

            if ($approve) {
                $total = $this->catalog->stepCount($case->visa_type);

                if ($number >= $total) {
                    $case->update(['status' => ImmigrationCase::STATUS_COMPLETED, 'completed_at' => now(), 'current_step' => $total]);
                } else {
                    $case->update(['current_step' => $number + 1]);
                }
            }

            if ($case->client) {
                $case->client->notify(new StepReviewed($case, $number, $step['title'], $approve, $reason));
            }

            return $caseStep->fresh();
        });
    }

    private function storeDocument(ImmigrationCase $case, CaseStep $caseStep, string $key, UploadedFile $file, User $by): CaseDocument
    {
        // Substitui o arquivo anterior do mesmo documento nesta etapa.
        $previous = $caseStep->documents()->where('document_key', $key)->get();
        foreach ($previous as $old) {
            Storage::disk('local')->delete($old->path);
            $old->delete();
        }

        $path = $file->storeAs(
            "cases/{$case->uuid}/steps/{$caseStep->number}",
            now()->format('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$file->extension(),
            'local',
        );

        return CaseDocument::create([
            'immigration_case_id' => $case->id,
            'case_step_id' => $caseStep->id,
            'document_key' => $key,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $by->id,
        ]);
    }
}
