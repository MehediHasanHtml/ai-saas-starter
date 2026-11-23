<?php

namespace App\Http\Controllers\Api;

use App\AI\AiManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ModelController extends Controller
{
    /** Providers + models for the picker, flagged by configuration and plan. */
    public function index(AiManager $ai): JsonResponse
    {
        $organization = $this->tenant();

        return response()->json([
            'default_provider' => $organization->setting('default_provider', config('ai.default')),
            'default_model' => $organization->setting('default_model', config('ai.default_model')),
            'embedding' => [
                'provider' => config('ai.embeddings.provider'),
                'model' => config('ai.embeddings.model'),
                'dimensions' => config('ai.embeddings.dimensions'),
            ],
            'data' => $ai->catalogue($organization->plan()),
        ]);
    }
}
