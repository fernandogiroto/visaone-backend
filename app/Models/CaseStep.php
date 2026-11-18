<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseStep extends Model
{
    public const PENDING = 'pending';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'immigration_case_id', 'number', 'step_key', 'status', 'data',
        'rejection_reason', 'submitted_at', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'data' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'immigration_case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class);
    }
}
