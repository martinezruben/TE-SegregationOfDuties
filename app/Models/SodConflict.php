<?php

namespace App\Models;

use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Un par de roles en conflicto (fila de la matriz SoD).
 * Convención: role_a_id < role_b_id.
 */
class SodConflict extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'sod_matrix';

    protected $fillable = ['role_a_id', 'role_b_id', 'risk_level', 'description', 'is_active'];

    protected $casts = [
        'risk_level' => RiskLevel::class,
        'is_active' => 'boolean',
    ];

    public function roleA(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_a_id');
    }

    public function roleB(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_b_id');
    }
}
