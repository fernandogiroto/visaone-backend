<?php

namespace App\Notifications;

use App\Models\ImmigrationCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CaseAcceptedByAgent extends Notification implements ShouldQueue
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
            'type' => 'case_accepted',
            'title' => 'Um agente aceitou seu caso',
            'message' => 'Seu agente já está responsável pelo processo. Confira o perfil dele no painel.',
            'case_uuid' => $this->case->uuid,
        ];
    }
}
