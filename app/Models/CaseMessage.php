<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseMessage extends Model
{
    public const KIND_TEXT = 'text';
    public const KIND_REQUEST = 'request';

    protected $fillable = ['immigration_case_id', 'user_id', 'body', 'kind', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'immigration_case_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
