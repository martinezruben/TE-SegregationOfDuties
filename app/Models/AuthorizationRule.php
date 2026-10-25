<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Regla de la matriz de autorizaciones configurable (context.md §8.5).
 *
 * Define quién autoriza según el disparador de la solicitud:
 *  - trigger_type: sod_risk | application | cargo_misalignment
 *  - steps: una o varias autorizaciones en secuencia (persona fija o su supervisor)
 */
class AuthorizationRule extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name', 'trigger_type', 'risk_level', 'application_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AuthorizationRuleStep::class)->orderBy('step_order');
    }

    /**
     * Reemplaza los pasos de la regla (en el orden recibido, desde 1).
     *
     * @param  list<array{authorizer_type:string, authorizer_id:?int}>  $steps
     */
    public function syncSteps(array $steps): void
    {
        $this->steps()->delete();

        foreach (array_values($steps) as $i => $step) {
            $this->steps()->create([
                'step_order' => $i + 1,
                'authorizer_type' => $step['authorizer_type'],
                'authorizer_id' => $step['authorizer_type'] === 'supervisor' ? null : ($step['authorizer_id'] ?? null),
            ]);
        }
    }
}
