<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class CertificationItem extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'campaign_id', 'managed_user_id', 'role_id', 'reviewer_id',
        'user_name', 'user_igg', 'area', 'cargo', 'app_name', 'role_name', 'source',
        'assigned_at', 'expires_at', 'conflict_risk',
        'decision', 'decided_by', 'decided_at', 'comments', 'reminded_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CertificationCampaign::class, 'campaign_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
