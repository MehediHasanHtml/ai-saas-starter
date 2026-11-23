<?php

namespace App\Rag;

use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL + pgvector. Uses cosine distance (<=>) with an HNSW index.
 * Swap the VectorStore binding in AppServiceProvider to use Qdrant,
 * Pinecone, etc. without touching the rest of the RAG pipeline.
 */
class PgVectorStore implements VectorStore
{
    public function replaceDocumentChunks(int $organizationId, int $documentId, array $chunks): void
    {
        DB::transaction(function () use ($organizationId, $documentId, $chunks) {
            DB::table('document_chunks')
                ->where('organization_id', $organizationId)
                ->where('document_id', $documentId)
                ->delete();

            $now = now();

            foreach (array_chunk($chunks, 100, true) as $batch) {
                $placeholders = [];
                $bindings = [];

                foreach ($batch as $index => $chunk) {
                    $placeholders[] = '(?, ?, ?, ?, ?, ?::jsonb, ?::vector, ?, ?)';
                    array_push(
                        $bindings,
                        $organizationId,
                        $documentId,
                        $index,
                        $chunk['content'],
                        $chunk['token_count'],
                        json_encode($chunk['metadata'] ?: new \stdClass),
                        self::toVector($chunk['embedding']),
                        $now,
                        $now,
                    );
                }

                DB::insert(
                    'INSERT INTO document_chunks (organization_id, document_id, chunk_index, content, token_count, metadata, embedding, created_at, updated_at) VALUES '
                    .implode(', ', $placeholders),
                    $bindings
                );
            }
        });
    }

    public function search(int $organizationId, array $embedding, int $limit = 6, ?array $documentIds = null): array
    {
        $query = DB::table('document_chunks as c')
            ->join('documents as d', 'd.id', '=', 'c.document_id')
            ->where('c.organization_id', $organizationId)
            ->where('d.status', 'ready')
            ->whereNotNull('c.embedding')
            ->select([
                'c.id as chunk_id',
                'c.document_id',
                'd.title as document_title',
                'c.content',
                'c.metadata',
            ])
            ->selectRaw('1 - (c.embedding <=> ?::vector) as score', [self::toVector($embedding)])
            ->orderByRaw('c.embedding <=> ?::vector', [self::toVector($embedding)])
            ->limit($limit);

        if ($documentIds) {
            $query->whereIn('c.document_id', $documentIds);
        }

        return $query->get()->map(fn ($row) => [
            'chunk_id' => (int) $row->chunk_id,
            'document_id' => (int) $row->document_id,
            'document_title' => $row->document_title,
            'content' => $row->content,
            'metadata' => json_decode($row->metadata ?? '{}', true) ?: [],
            'score' => round((float) $row->score, 4),
        ])->all();
    }

    /** pgvector text format: [0.1,0.2,...] */
    public static function toVector(array $embedding): string
    {
        return '['.implode(',', array_map(fn ($v) => sprintf('%.7F', $v), $embedding)).']';
    }
}
