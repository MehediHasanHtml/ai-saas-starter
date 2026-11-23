<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rag\Retriever;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Semantic search across the organization's knowledge base. */
class SearchController extends Controller
{
    public function search(Request $request, Retriever $retriever): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'document_ids' => ['sometimes', 'array'],
            'document_ids.*' => ['integer', Rule::exists('documents', 'id')->where('organization_id', $this->tenant()->id)],
        ]);

        $results = $retriever->retrieve(
            $this->tenant(),
            $data['query'],
            $data['document_ids'] ?? null,
            $data['limit'] ?? 8,
        );

        return response()->json(['data' => $results]);
    }
}
