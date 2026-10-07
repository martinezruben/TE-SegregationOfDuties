<?php

namespace App\Notifications;

use App\Models\CertificationCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al revisor: tiene accesos por certificar en una campaña (o recordatorio si `$reminder`). */
class CertificationAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificationCampaign $campaign,
        public int $pending,
        public bool $reminder = false,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function body(): string
    {
        return sprintf(
            '%s: %d acceso(s) por certificar, vence el %s.',
            $this->campaign->name,
            $this->pending,
            $this->campaign->due_date->format('d/m/Y'),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->reminder ? 'Recordatorio: ' : '').'Recertificación de accesos · '.$this->campaign->name)
            ->greeting('Hola '.$notifiable->name)
            ->line($this->reminder
                ? 'Tienes accesos pendientes de certificar y la fecha límite se acerca o ya venció.'
                : 'Se te asignaron accesos para confirmar o revocar.')
            ->line($this->body())
            ->action('Certificar accesos', route('certificaciones.show', $this->campaign));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->reminder ? 'certification_reminder' : 'certification_assigned',
            'title' => $this->reminder ? 'Recertificación pendiente' : 'Recertificación de accesos',
            'body' => $this->body(),
            'url' => route('certificaciones.show', $this->campaign, absolute: false),
            'campaign_id' => $this->campaign->id,
        ];
    }
}
