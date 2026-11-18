<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAssignment extends Model
{
    public const OFFERED = 'offered';
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const CLOSED = 'closed';

    protected $fillable = [
        'immigration_case_id', 'agent_id', 'status', 'offered_at', 'responded_at',
    ];

    protected $casts = [
        'offered_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'immigration_case_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
