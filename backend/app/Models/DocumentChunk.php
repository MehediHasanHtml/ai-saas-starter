<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `embedding` vector column is written and queried with raw SQL in
 * App\Rag\PgVectorStore, and deliberately hidden from serialization.
 */
class DocumentChunk extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'document_id', 'chunk_index', 'content', 'token_count', 'metadata'];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withoutGlobalScopes();
    }
}
