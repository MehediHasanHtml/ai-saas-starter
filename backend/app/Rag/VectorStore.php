<?php

namespace App\Rag;

interface VectorStore
{
    /**
     * Replace all chunks of a document.
     *
     * @param  list<array{content: string, embedding: list<float>, token_count: int, metadata: array}>  $chunks
     */
    public function replaceDocumentChunks(int $organizationId, int $documentId, array $chunks): void;

    /**
     * Nearest chunks by cosine similarity, always constrained to one tenant.
     *
     * @param  list<float>  $embedding
     * @param  list<int>|null  $documentIds
     * @return list<array{chunk_id: int, document_id: int, document_title: string, content: string, metadata: array, score: float}>
     */
    public function search(int $organizationId, array $embedding, int $limit = 6, ?array $documentIds = null): array;
}
