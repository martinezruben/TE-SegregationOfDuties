<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class RoleRequest extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'managed_user_id', 'requested_role_id', 'type', 'payload', 'status',
        'requester_id', 'justification', 'has_sod_conflict', 'is_major', 'auto_resolved',
    ];

    protected $casts = [
        'type' => RequestType::class,
        'status' => RequestStatus::class,
        'payload' => 'array',
        'has_sod_conflict' => 'boolean',
        'is_major' => 'boolean',
        'auto_resolved' => 'boolean',
    ];

    public function managedUser(): BelongsTo
    {
        return $this->belongsTo(ManagedUser::class);
    }

    public function requestedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'requested_role_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class)->orderBy('step_order');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(RequestSodFinding::class);
    }

    /** Paso pendiente actual (menor step_order sin resolver). */
    public function currentStep(): ?RequestApproval
    {
        return $this->approvals->firstWhere('status', \App\Enums\ApprovalStatus::Pendiente);
    }
}
