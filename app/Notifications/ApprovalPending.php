<?php

namespace App\Notifications;

use App\Models\RequestApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un paso de autorización pasó a ser el actual: avisa al aprobador.
 * Si `$reminder` es true, es un recordatorio por SLA vencido.
 */
class ApprovalPending extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RequestApproval $approval, public bool $reminder = false)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function summary(): string
    {
        $r = $this->approval->roleRequest->loadMissing(['managedUser', 'requestedRole.application']);

        return sprintf(
            'Solicitud #%d: %s para %s (%s)',
            $r->id,
            $r->requestedRole?->name ?? 'rol',
            $r->managedUser?->name ?? 'usuario',
            $r->requestedRole?->application?->name ?? 'aplicación',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $id = $this->approval->role_request_id;

        return (new MailMessage)
            ->subject(($this->reminder ? 'Recordatorio: ' : '').'Autorización pendiente · Solicitud #'.$id)
            ->greeting('Hola '.$notifiable->name)
            ->line($this->reminder
                ? 'Tienes una autorización vencida según el SLA que sigue sin resolverse.'
                : 'Tienes una solicitud esperando tu autorización.')
            ->line($this->summary())
            ->action('Revisar solicitud', route('solicitudes.show', $id));
    }

    public function toArray(object $notifiable): array
    {
        $id = $this->approval->role_request_id;

        return [
            'kind' => $this->reminder ? 'reminder' : 'approval_pending',
            'title' => $this->reminder ? 'Autorización vencida' : 'Autorización pendiente',
            'body' => $this->summary(),
            'url' => route('solicitudes.show', $id, absolute: false),
            'role_request_id' => $id,
        ];
    }
}
