<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentProfile extends Model
{
    protected $fillable = [
        'user_id', 'photo_path', 'birth_date', 'job_title', 'specialty',
        'years_experience', 'languages', 'bio',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    protected $appends = ['age'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }
}
