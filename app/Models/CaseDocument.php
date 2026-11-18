<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDocument extends Model
{
    protected $fillable = [
        'immigration_case_id', 'case_step_id', 'document_key', 'original_name',
        'path', 'mime_type', 'size', 'uploaded_by',
    ];

    protected $hidden = ['path'];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'immigration_case_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(CaseStep::class, 'case_step_id');
    }
}
