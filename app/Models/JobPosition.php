<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class JobPosition extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['name', 'description', 'generic_position_id', 'is_generic', 'is_active'];

    protected $casts = [
        'is_generic' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** Cargo genérico equivalente. */
    public function genericPosition(): BelongsTo
    {
        return $this->belongsTo(self::class, 'generic_position_id');
    }

    public function specificPositions(): HasMany
    {
        return $this->hasMany(self::class, 'generic_position_id');
    }

    public function managedUsers(): HasMany
    {
        return $this->hasMany(ManagedUser::class);
    }

    /** Reglas de cargo (permitido / no permitido) sobre roles de negocio. */
    public function roleRules(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'job_position_role')
            ->withPivot(['rule_type', 'notes'])
            ->withTimestamps();
    }
}
