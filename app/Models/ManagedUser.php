<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Usuario Gestionado (sujeto): población gobernada, sin login.
 * Ver context.md §1.1.
 */
class ManagedUser extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['external_id', 'igg', 'name', 'area_id', 'job_position_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    /** La "Foto": roles de negocio que posee actualmente. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot(['assigned_at', 'assigned_by', 'source', 'expires_at'])
            ->withTimestamps();
    }
}
