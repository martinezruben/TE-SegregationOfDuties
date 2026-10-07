<?php

namespace App\Notifications;

use App\Models\RoleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resultado final de una solicitud (aprobada / rechazada) para quien la creó. */
class RequestResolved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RoleRequest $request, public bool $approved, public ?string $comments = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function summary(): string
    {
        $r = $this->request->loadMissing(['managedUser', 'requestedRole.application']);

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
        $mail = (new MailMessage)
            ->subject('Solicitud #'.$this->request->id.($this->approved ? ' aprobada' : ' rechazada'))
            ->greeting('Hola '.$notifiable->name)
            ->line($this->approved ? 'Tu solicitud fue aprobada y el acceso quedó registrado.' : 'Tu solicitud fue rechazada.')
            ->line($this->summary());

        if ($this->comments) {
            $mail->line('Comentario del aprobador: '.$this->comments);
        }

        return $mail->action('Ver solicitud', route('solicitudes.show', $this->request->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->approved ? 'request_approved' : 'request_rejected',
            'title' => $this->approved ? 'Solicitud aprobada' : 'Solicitud rechazada',
            'body' => $this->summary().($this->comments ? ' — '.$this->comments : ''),
            'url' => route('solicitudes.show', $this->request->id, absolute: false),
            'role_request_id' => $this->request->id,
        ];
    }
}
