<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'user_id', 'title', 'filename', 'mime_type', 'size_bytes', 'disk', 'path',
        'status', 'error', 'chunk_count', 'token_count', 'embedding_model', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'processed_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Document $document) {
            Storage::disk($document->disk)->delete($document->path);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->withoutGlobalScopes();
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }
}
