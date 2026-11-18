<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ImmigrationCase extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_WAITING_AGENT = 'aguardando_agente';
    public const STATUS_IN_PROGRESS = 'em_andamento';
    public const STATUS_COMPLETED = 'concluido';
    public const STATUS_NO_AGENT = 'sem_agente';

    protected $fillable = [
        'uuid', 'user_id', 'agent_id', 'first_name', 'last_name', 'visa_type',
        'origin_country', 'destination_country', 'status', 'current_step',
        'accepted_at', 'completed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_step' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $case) {
            $case->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CaseStep::class)->orderBy('number');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class)->orderBy('created_at');
    }

    public function step(int $number): ?CaseStep
    {
        return $this->steps->firstWhere('number', $number)
            ?? $this->steps()->where('number', $number)->first();
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->agent_id !== null && $this->agent_id === $user->id;
    }
}
