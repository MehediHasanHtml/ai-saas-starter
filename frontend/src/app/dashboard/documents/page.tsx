"use client";

import clsx from "clsx";
import { FileText, RotateCw, Search, Trash2, Upload } from "lucide-react";
import { useRef, useState } from "react";
import useSWR from "swr";
import { Badge, Button, EmptyState, Input, Notice, PageHeader, Panel, Spinner, formatBytes } from "@/components/ui";
import { ApiError, api, errorMessage, fetcher } from "@/lib/api";
import type { DocumentItem, DocumentStatus, Paginated, SearchResult } from "@/lib/types";
import { useSession } from "@/lib/use-session";

const ACCEPT = ".pdf,.txt,.md,.markdown,.csv,.docx,.html,.htm,.json";

const STATUS: Record<DocumentStatus, { label: string; tone: "neutral" | "success" | "danger" | "action" }> = {
  pending: { label: "Queued", tone: "neutral" },
  processing: { label: "Indexing", tone: "action" },
  ready: { label: "Ready", tone: "success" },
  failed: { label: "Failed", tone: "danger" },
};

export default function DocumentsPage() {
  const { organization } = useSession();
  const key = organization ? `/documents?org=${organization.id}` : null;

  const { data, error, isLoading, mutate } = useSWR<Paginated<DocumentItem>>(key, fetcher, {
    // Poll while anything is still being indexed.
    refreshInterval: (latest) => (latest?.data.some((d) => d.status === "pending" || d.status === "processing") ? 2000 : 0),
  });

  const [uploading, setUploading] = useState<string[]>([]);
  const [uploadError, setUploadError] = useState<ApiError | Error | null>(null);
  const [dragging, setDragging] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const upload = async (files: FileList | File[]) => {
    setUploadError(null);
    for (const file of Array.from(files)) {
      setUploading((u) => [...u, file.name]);
      try {
        const form = new FormData();
        form.append("file", file);
        await api("/documents", { method: "POST", body: form });
        await mutate();
      } catch (e) {
        setUploadError(e as Error);
      } finally {
        setUploading((u) => u.filter((n) => n !== file.name));
      }
    }
  };

  const limit = organization?.plan.name;

  return (
    <div className="w-full">
      <PageHeader
        title="Knowledge base"
        description="Upload documents and the assistant will answer from them, citing the exact passage. Files are split into passages and indexed for semantic search."
      />

      <div
        onDragOver={(e) => {
          e.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragging(false);
          if (e.dataTransfer.files.length) void upload(e.dataTransfer.files);
        }}
        className={clsx(
          "flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors",
          dragging ? "border-action bg-action-soft" : "border-line-strong bg-surface",
        )}
      >
        <Upload className="size-6 text-muted" aria-hidden />
        <p className="mt-3 font-medium">Drop files here to add them</p>
        <p className="mt-1 text-sm text-muted">PDF, Word, Markdown, text, CSV, HTML or JSON. Size limits depend on your {limit} plan.</p>
        <Button className="mt-4" variant="secondary" onClick={() => inputRef.current?.click()} loading={uploading.length > 0}>
          {uploading.length ? `Uploading ${uploading.length} file${uploading.length > 1 ? "s" : ""}` : "Choose files"}
        </Button>
        <input
          ref={inputRef}
          type="file"
          multiple
          accept={ACCEPT}
          className="sr-only"
          onChange={(e) => {
            if (e.target.files?.length) void upload(e.target.files);
            e.target.value = "";
          }}
        />
      </div>

      {uploadError && (
        <div className="mt-4">
          <Notice tone="danger">{errorMessage(uploadError)}</Notice>
        </div>
      )}

      <div className="mt-10 grid gap-8 lg:grid-cols-[1fr_340px]">
        <section aria-labelledby="docs-heading">
          <h2 id="docs-heading" className="mb-3 font-semibold">
            Documents {data?.meta?.total ? <span className="font-normal text-muted">({data.meta.total})</span> : null}
          </h2>
          {error && <Notice tone="danger">{errorMessage(error)}</Notice>}
          {isLoading && <Spinner />}
          {data && data.data.length === 0 && (
            <EmptyState title="No documents yet" body="Add a handbook, a contract or product docs, then ask questions about them in Chat." />
          )}
          {data && data.data.length > 0 && (
            <Panel className="divide-y divide-line">
              {data.data.map((doc) => (
                <DocumentRow key={doc.id} doc={doc} onChange={() => void mutate()} />
              ))}
            </Panel>
          )}
        </section>

        <SemanticSearch />
      </div>
    </div>
  );
}

function DocumentRow({ doc, onChange }: { doc: DocumentItem; onChange: () => void }) {
  const [busy, setBusy] = useState(false);
  const status = STATUS[doc.status];

  const act = async (fn: () => Promise<unknown>) => {
    setBusy(true);
    try {
      await fn();
      onChange();
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex items-start gap-3 px-4 py-3.5">
      <FileText className="mt-0.5 size-5 shrink-0 text-faint" aria-hidden />
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <p className="truncate font-medium">{doc.title}</p>
          <Badge tone={status.tone}>{status.label}</Badge>
        </div>
        <p className="mt-0.5 text-[13px] text-muted">
          {doc.filename}, {formatBytes(doc.size_bytes)}
          {doc.status === "ready" && `, ${doc.chunk_count} passages`}
          {doc.uploaded_by && `, added by ${doc.uploaded_by.name}`}
        </p>
        {doc.error && <p className="mt-1 text-[13px] text-danger">{doc.error}</p>}
      </div>
      <div className="flex shrink-0 gap-1">
        {doc.status === "failed" && (
          <button
            disabled={busy}
            onClick={() => act(() => api(`/documents/${doc.id}/reprocess`, { method: "POST" }))}
            className="rounded p-1.5 text-muted hover:bg-paper hover:text-ink"
            aria-label={`Retry indexing ${doc.title}`}
          >
            <RotateCw className="size-4" />
          </button>
        )}
        <button
          disabled={busy}
          onClick={() =>
            confirm(`Delete “${doc.title}”? It will no longer be used in answers.`) && act(() => api(`/documents/${doc.id}`, { method: "DELETE" }))
          }
          className="rounded p-1.5 text-muted hover:bg-danger-soft hover:text-danger"
          aria-label={`Delete ${doc.title}`}
        >
          <Trash2 className="size-4" />
        </button>
      </div>
    </div>
  );
}

function SemanticSearch() {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<SearchResult[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const run = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!query.trim()) return;
    setLoading(true);
    setError(null);
    try {
      const res = await api<{ data: SearchResult[] }>("/search", { method: "POST", json: { query, limit: 5 } });
      setResults(res.data);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <section aria-labelledby="search-heading">
      <h2 id="search-heading" className="mb-1 font-semibold">
        Test retrieval
      </h2>
      <p className="mb-3 text-sm text-muted">See which passages the assistant would read for a question.</p>
      <form onSubmit={run} className="flex gap-2">
        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="e.g. notice period" aria-label="Search query" />
        <Button type="submit" variant="secondary" loading={loading} aria-label="Search" className="shrink-0 px-3">
          {!loading && <Search className="size-4" />}
        </Button>
      </form>

      {error && <p className="mt-3 text-sm text-danger">{error}</p>}
      {results && results.length === 0 && <p className="mt-4 text-sm text-muted">No passage is close enough to that question.</p>}
      {results && results.length > 0 && (
        <ol className="mt-4 space-y-3">
          {results.map((r) => (
            <li key={r.chunk_id} className="rounded-md bg-surface p-3 ring-1 ring-line">
              <div className="flex items-baseline justify-between gap-2 text-xs text-muted">
                <span className="truncate font-medium text-ink">
                  {r.document_title}
                  {r.metadata.page ? `, p. ${r.metadata.page}` : ""}
                </span>
                <span className="shrink-0 tabular-nums" title="Cosine similarity">
                  {(r.score * 100).toFixed(0)}% match
                </span>
              </div>
              <p className="mt-1.5 line-clamp-5 font-serif text-[13px] leading-relaxed">
                <span className="mark">{r.content}</span>
              </p>
            </li>
          ))}
        </ol>
      )}
    </section>
  );
}
