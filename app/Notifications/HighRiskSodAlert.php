<?php

namespace App\Notifications;

use App\Models\RoleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerta a Seguridad: se registró una solicitud con conflicto SoD de riesgo alto (§11). */
class HighRiskSodAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RoleRequest $request)
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
            'Solicitud #%d: %s para %s (%s) genera conflicto SoD de riesgo ALTO.',
            $r->id,
            $r->requestedRole?->name ?? 'rol',
            $r->managedUser?->name ?? 'usuario',
            $r->requestedRole?->application?->name ?? 'aplicación',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerta SoD de riesgo alto · Solicitud #'.$this->request->id)
            ->greeting('Hola '.$notifiable->name)
            ->line($this->summary())
            ->action('Revisar solicitud', route('solicitudes.show', $this->request->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'sod_high_risk',
            'title' => 'Conflicto SoD de riesgo alto',
            'body' => $this->summary(),
            'url' => route('solicitudes.show', $this->request->id, absolute: false),
            'role_request_id' => $this->request->id,
        ];
    }
}
