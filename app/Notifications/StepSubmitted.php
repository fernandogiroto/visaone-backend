<?php

namespace App\Notifications;

use App\Models\ImmigrationCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StepSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ImmigrationCase $case,
        public int $number,
        public string $stepTitle,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'step_submitted',
            'title' => "Etapa {$this->number} enviada para análise",
            'message' => "{$this->case->first_name} enviou a etapa \"{$this->stepTitle}\".",
            'case_uuid' => $this->case->uuid,
            'step' => $this->number,
        ];
    }
}
