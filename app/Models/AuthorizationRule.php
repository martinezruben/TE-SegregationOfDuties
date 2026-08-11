<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Regla de la matriz de autorizaciones configurable (context.md §8.5).
 *
 * Define quién autoriza según el disparador de la solicitud:
 *  - trigger_type: sod_risk | application | cargo_misalignment
 *  - authorizer_type: fixed (persona fija) | supervisor (jefe del gestionado)
 */
class AuthorizationRule extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name', 'trigger_type', 'risk_level', 'application_id',
        'authorizer_type', 'authorizer_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizer_id');
    }
}
