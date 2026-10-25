<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un paso de autorización de una regla. Los pasos de una regla se ejecutan
 * en secuencia según `step_order`.
 *  - authorizer_type: fixed (persona fija) | supervisor (jefe del gestionado)
 */
class AuthorizationRuleStep extends Model
{
    protected $fillable = ['authorization_rule_id', 'step_order', 'authorizer_type', 'authorizer_id'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AuthorizationRule::class, 'authorization_rule_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizer_id');
    }
}
