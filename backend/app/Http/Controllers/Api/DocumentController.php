<?php

namespace App\Http\Controllers\Api;

use App\Billing\Entitlements;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = Document::query()
            ->with('user:id,name')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->where('title', 'ilike', '%'.addcslashes($term, '%_\\').'%'))
            ->latest()
            ->paginate(25);

        return DocumentResource::collection($documents);
    }

    public function store(Request $request, Entitlements $entitlements): JsonResponse
    {
        $organization = $this->tenant();
        $maxKb = ($organization->plan()->limit('max_file_mb') ?? 100) * 1024;

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb, 'extensions:'.implode(',', config('saas.document_mimes'))],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $data['file'];
        $entitlements->ensureCanUploadDocument($organization, $file->getSize());

        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            "organizations/{$organization->id}/documents",
            Str::uuid().'.'.$extension,
            'local',
        );

        $document = Document::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'disk' => 'local',
            'path' => $path,
            'status' => DocumentStatus::Pending,
        ]);

        ProcessDocument::dispatch($document->id);

        return (new DocumentResource($document->fresh()->load('user:id,name')))->response()->setStatusCode(202);
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load('user:id,name'));
    }

    public function reprocess(Document $document): DocumentResource
    {
        $document->update(['status' => DocumentStatus::Pending, 'error' => null]);
        ProcessDocument::dispatch($document->id);

        return new DocumentResource($document);
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete(); // chunks cascade; file removed in model event

        return response()->json(['message' => 'Document deleted.']);
    }
}
