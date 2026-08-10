<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Option extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['role_id', 'name', 'action_key'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
