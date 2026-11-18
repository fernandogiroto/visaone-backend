<?php

namespace App\Notifications;

use App\Models\ImmigrationCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StepReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ImmigrationCase $case,
        public int $number,
        public string $stepTitle,
        public bool $approved,
        public ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->approved) {
            return [
                'type' => 'step_approved',
                'title' => "Etapa {$this->number} aprovada",
                'message' => "A etapa \"{$this->stepTitle}\" foi aprovada.",
                'case_uuid' => $this->case->uuid,
                'step' => $this->number,
            ];
        }

        return [
            'type' => 'step_rejected',
            'title' => "Etapa {$this->number} precisa de correção",
            'message' => "A etapa \"{$this->stepTitle}\" foi recusada: {$this->reason}",
            'case_uuid' => $this->case->uuid,
            'step' => $this->number,
        ];
    }
}
