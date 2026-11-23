<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('status')->default('pending')->index(); // pending|processing|ready|failed
            $table->text('error')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('token_count')->default(0);
            $table->string('embedding_model')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            // Denormalised so vector search can filter by tenant without a join.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'document_id']);
        });

        $dimensions = (int) config('ai.embeddings.dimensions', 1536);
        DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector({$dimensions})");

        // HNSW index for approximate nearest-neighbour search on cosine distance.
        // pgvector's HNSW supports up to 2,000 dimensions for the vector type.
        if ($dimensions <= 2000) {
            DB::statement('CREATE INDEX document_chunks_embedding_hnsw ON document_chunks USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
