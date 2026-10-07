<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Lote de importación (dry-run + confirmación). Ver context.md §14.
 */
class ImportBatch extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'kind', 'user_id', 'filename', 'status', 'total_rows', 'summary', 'preview',
        'errors', 'raw_rows', 'result', 'applied_at',
    ];

    /** Las filas crudas pueden ser miles: no van a la bitácora. */
    protected $auditExclude = ['raw_rows', 'preview', 'errors'];

    protected $casts = [
        'summary' => 'array',
        'preview' => 'array',
        'errors' => 'array',
        'raw_rows' => 'array',
        'result' => 'array',
        'applied_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }
}
