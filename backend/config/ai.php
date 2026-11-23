<?php

/*
|--------------------------------------------------------------------------
| AI providers
|--------------------------------------------------------------------------
|
| Every chat provider implements App\AI\Contracts\ChatProvider and is
| resolved through App\AI\AiManager (a Laravel Manager), so adding a new
| provider is one class plus one entry below.
|
| Model ids change often. Everything here is overridable from .env, and the
| "models" lists drive the model picker in the frontend.
|
| The "fake" provider needs no API key. It returns deterministic answers and
| hashed bag-of-words embeddings so the whole product (uploads, RAG,
| streaming chat, quotas) works locally and in tests with zero setup.
|
*/

return [

    'default' => env('AI_DEFAULT_PROVIDER', 'fake'),

    'default_model' => env('AI_DEFAULT_MODEL'),

    /*
    | Embeddings must all live in the same vector space, so one embedding
    | provider is used platform-wide. If you change the provider/model or the
    | dimensions you must re-embed existing documents (php artisan rag:reindex).
    */
    'embeddings' => [
        'provider' => env('AI_EMBEDDING_PROVIDER', 'fake'),
        'model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('AI_EMBEDDING_BATCH', 64),
    ],

    'rag' => [
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 1200),       // characters
        'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 200),  // characters
        'top_k' => (int) env('RAG_TOP_K', 6),
        'min_score' => (float) env('RAG_MIN_SCORE', 0.2),        // cosine similarity cut-off
        'history_messages' => (int) env('RAG_HISTORY_MESSAGES', 12),
    ],

    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', 120),

    'providers' => [

        'openai' => [
            'label' => 'OpenAI',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'models' => array_filter(explode(',', env('OPENAI_MODELS', 'gpt-4.1-mini,gpt-4.1'))),
        ],

        'anthropic' => [
            'label' => 'Anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            'models' => array_filter(explode(',', env('ANTHROPIC_MODELS', 'claude-haiku-5-5,claude-sonnet-5-5'))),
        ],

        'gemini' => [
            'label' => 'Google Gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'models' => array_filter(explode(',', env('GEMINI_MODELS', 'gemini-3.5-flash-lite,gemini-3.8-flash,gemini-3.5-flash'))),
        ],

        'ollama' => [
            'label' => 'Ollama (self-hosted)',
            'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
            'models' => array_filter(explode(',', env('OLLAMA_MODELS', 'llama3.2'))),
        ],

        'fake' => [
            'label' => 'Demo (no API key)',
            'models' => ['demo-echo'],
        ],

    ],

];
