<?php

namespace App\Models;

use App\Enums\CriticalityLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Application extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['name', 'description', 'criticality_level', 'owner_id', 'is_active'];

    protected $casts = [
        'criticality_level' => CriticalityLevel::class,
        'is_active' => 'boolean',
    ];

    /** Owner = agente (User) responsable de aprobar accesos. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
