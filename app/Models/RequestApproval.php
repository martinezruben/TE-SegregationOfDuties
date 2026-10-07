<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class RequestApproval extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'role_request_id', 'approver_id', 'approver_role', 'reasons', 'step_order',
        'status', 'is_auto', 'comments', 'resolved_at', 'resolved_by',
        'activated_at', 'reminded_at', 'reminder_count',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'reasons' => 'array',
        'is_auto' => 'boolean',
        'resolved_at' => 'datetime',
        'activated_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function roleRequest(): BelongsTo
    {
        return $this->belongsTo(RoleRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
