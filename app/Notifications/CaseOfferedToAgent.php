<?php

namespace App\Notifications;

use App\Models\ImmigrationCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CaseOfferedToAgent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ImmigrationCase $case)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'case_offered',
            'title' => 'Novo caso disponível',
            'message' => "Caso de {$this->case->first_name} {$this->case->last_name} aguarda sua resposta.",
            'case_uuid' => $this->case->uuid,
        ];
    }
}
