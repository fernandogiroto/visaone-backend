<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryPasswordCreated extends Notification
{
    use Queueable;

    public function __construct(private string $temporaryPassword)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua conta no VisaOne')
            ->greeting('Olá!')
            ->line('Sua conta foi criada. Use a senha temporária abaixo para entrar:')
            ->line("**{$this->temporaryPassword}**")
            ->line('No primeiro acesso você deverá trocar essa senha.')
            ->action('Entrar', url('/login'));
    }
}
