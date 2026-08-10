<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Rol de NEGOCIO dentro de una aplicación.
 * No confundir con Spatie\Permission\Models\Role (tabla platform_roles).
 */
class Role extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['application_id', 'name', 'description'];

    protected static function booted(): void
    {
        // Al eliminar (incluso lógico) un rol, se eliminan sus opciones.
        static::deleting(function (Role $role): void {
            $role->options()->delete();
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }
}
