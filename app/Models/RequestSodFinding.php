<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestSodFinding extends Model
{
    protected $fillable = ['role_request_id', 'conflicting_role_id', 'sod_matrix_id', 'risk_level'];

    public function conflictingRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'conflicting_role_id');
    }
}
