"use client";

import clsx from "clsx";
import { ArrowUp, BookOpen, MessageSquarePlus, PanelLeft, Sparkles, Square, Trash2, X } from "lucide-react";
import Link from "next/link";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import useSWR from "swr";
import { AnswerMarkdown } from "@/components/chat/markdown";
import { Button, Notice, Select } from "@/components/ui";
import { ApiError, api, errorMessage, fetcher, streamChat } from "@/lib/api";
import type { ChatMessage, Citation, Conversation, Paginated, ProviderCatalogue } from "@/lib/types";
import { useSession } from "@/lib/use-session";

export function ChatWorkspace({ initialId }: { initialId?: number }) {
  const { organization } = useSession();
  const orgKey = organization ? `org=${organization.id}` : null;

  const [conversationId, setConversationId] = useState<number | undefined>(initialId);
  // Remount the thread only when the user picks a chat, not when a new chat
  // gets its id mid-stream.
  const [threadKey, setThreadKey] = useState<string>(String(initialId ?? "new"));
  const { data: list, mutate: refreshList } = useSWR<Paginated<Conversation>>(orgKey && `/conversations?${orgKey}`, fetcher);
  const { data: catalogue } = useSWR<ProviderCatalogue>(orgKey && `/models?${orgKey}`, fetcher);

  // Conversation list is a slide-over drawer below the lg breakpoint.
  const [listOpen, setListOpen] = useState(false);

  const open = useCallback((id: number | undefined, remount = true) => {
    setListOpen(false);
    setConversationId(id);
    if (remount) setThreadKey(`${id ?? "new"}-${Date.now()}`);
    // Update the URL without a navigation so an in-flight stream survives.
    window.history.replaceState(null, "", id ? `/dashboard/chat/${id}` : "/dashboard/chat");
  }, []);

  return (
    <div className="-mx-4 -my-6 grid h-[calc(100dvh-3.5rem)] sm:-mx-6 lg:-mx-8 lg:-my-8 lg:h-dvh lg:grid-cols-[260px_1fr]">
      <ConversationList
        conversations={list?.data ?? []}
        activeId={conversationId}
        mobileOpen={listOpen}
        onClose={() => setListOpen(false)}
        onSelect={open}
        onDelete={async (id) => {
          await api(`/conversations/${id}`, { method: "DELETE" });
          if (id === conversationId) open(undefined);
          await refreshList();
        }}
      />
      <Thread
        key={threadKey}
        conversationId={conversationId}
        catalogue={catalogue}
        onOpenList={() => setListOpen(true)}
        onCreated={(id) => {
          open(id, false);
          void refreshList();
        }}
        onActivity={() => void refreshList()}
      />
    </div>
  );
}

/* ------------------------------------------------------------------ List */

function ConversationList({
  conversations,
  activeId,
  mobileOpen,
  onClose,
  onSelect,
  onDelete,
}: {
  conversations: Conversation[];
  activeId?: number;
  mobileOpen: boolean;
  onClose: () => void;
  onSelect: (id: number | undefined) => void;
  onDelete: (id: number) => Promise<void>;
}) {
  useEffect(() => {
    if (!mobileOpen) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [mobileOpen, onClose]);

  return (
    <>
      <div
        className={clsx(
          "fixed inset-0 z-40 bg-ink/35 backdrop-blur-[2px] transition-opacity duration-300 lg:hidden",
          mobileOpen ? "opacity-100" : "pointer-events-none opacity-0",
        )}
        onClick={onClose}
        aria-hidden
      />
    <aside
      className={clsx(
        "fixed inset-y-0 left-0 z-50 flex w-[min(300px,85vw)] flex-col border-r border-line bg-surface transition-transform duration-300 ease-out",
        "lg:static lg:z-auto lg:w-auto lg:translate-x-0",
        mobileOpen ? "translate-x-0 shadow-2xl shadow-ink/20 lg:shadow-none" : "-translate-x-full",
      )}
    >
      <div className="flex items-center gap-2 p-3">
        <Button variant="secondary" className="flex-1 justify-start" onClick={() => onSelect(undefined)}>
          <MessageSquarePlus className="size-4" aria-hidden /> New chat
        </Button>
        <button className="rounded-md p-2 text-muted hover:bg-paper hover:text-ink lg:hidden" onClick={onClose} aria-label="Close chats">
          <X className="size-4" />
        </button>
      </div>
      <nav className="flex-1 overflow-y-auto px-2 pb-4" aria-label="Conversations">
        {conversations.length === 0 && <p className="px-2.5 py-2 text-sm text-muted">Your chats will appear here.</p>}
        {conversations.map((c) => (
          <div key={c.id} className="group relative">
            <button
              onClick={() => onSelect(c.id)}
              className={clsx(
                "w-full truncate rounded-md px-2.5 py-2 pr-8 text-left text-sm",
                c.id === activeId ? "bg-paper font-medium text-ink" : "text-muted hover:bg-paper hover:text-ink",
              )}
            >
              {c.title}
            </button>
            <button
              onClick={() => onDelete(c.id)}
              className="absolute right-1.5 top-1.5 rounded p-1 text-faint hover:bg-surface hover:text-danger focus:block lg:hidden lg:group-hover:block"
              aria-label={`Delete “${c.title}”`}
            >
              <Trash2 className="size-3.5" />
            </button>
          </div>
        ))}
      </nav>
    </aside>
    </>
  );
}

/* ---------------------------------------------------------------- Thread */

type LocalMessage = ChatMessage & { streaming?: boolean; error?: string };

const SUGGESTIONS = [
  "Summarise the key points of my latest document",
  "What does our refund policy say?",
  "List every deadline mentioned in the contract",
];

function Thread({
  conversationId,
  catalogue,
  onOpenList,
  onCreated,
  onActivity,
}: {
  conversationId?: number;
  catalogue?: ProviderCatalogue;
  onOpenList: () => void;
  onCreated: (id: number) => void;
  onActivity: () => void;
}) {
  const { data: conversation, mutate } = useSWR<{ data: Conversation }>(conversationId ? `/conversations/${conversationId}` : null, fetcher);

  // null = show what the server has; set once this tab starts a turn.
  const [localMessages, setLocalMessages] = useState<LocalMessage[] | null>(null);
  const messages: LocalMessage[] = useMemo(() => localMessages ?? conversation?.data.messages ?? [], [localMessages, conversation]);
  const seq = useRef(0);
  const [draft, setDraft] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | Error | null>(null);
  const [focusedSource, setFocusedSource] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const bottomRef = useRef<HTMLDivElement>(null);

  // Model selection for a brand-new chat (existing chats keep theirs).
  const options = useMemo(
    () => (catalogue?.data ?? []).flatMap((p) => p.models.map((m) => ({ value: `${p.provider}::${m}`, label: m, provider: p }))),
    [catalogue],
  );
  // User choice > the conversation's saved model > the organization default.
  const [modelChoice, setModelChoice] = useState<string | null>(null);
  const [knowledgeChoice, setKnowledgeChoice] = useState<boolean | null>(null);

  const defaultModel = useMemo(() => {
    if (!catalogue) return "";
    const usable = catalogue.data.filter((p) => p.allowed && p.configured);
    const preferred = usable.find((p) => p.provider === catalogue.default_provider) ?? usable[0];
    const model = catalogue.default_model && preferred?.models.includes(catalogue.default_model) ? catalogue.default_model : preferred?.models[0];
    return preferred && model ? `${preferred.provider}::${model}` : "";
  }, [catalogue]);

  const saved = conversation?.data;
  const selected = modelChoice ?? (saved ? `${saved.provider}::${saved.model}` : defaultModel);
  const useKnowledge = knowledgeChoice ?? saved?.use_knowledge_base ?? true;
  const setSelected = setModelChoice;
  const setUseKnowledge = setKnowledgeChoice;

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ block: "end" });
  }, [messages]);

  const updateSettings = async (patch: Partial<Conversation>) => {
    if (!conversationId) return;
    try {
      await api(`/conversations/${conversationId}`, { method: "PATCH", json: patch });
      void mutate();
    } catch (e) {
      setError(e as Error);
    }
  };

  const send = async (text: string) => {
    const content = text.trim();
    if (!content || busy) return;
    setError(null);
    setBusy(true);
    setDraft("");

    let id = conversationId;
    try {
      if (!id) {
        const [provider, model] = selected.split("::");
        const created = await api<{ data: Conversation }>("/conversations", {
          method: "POST",
          json: { provider, model, use_knowledge_base: useKnowledge },
        });
        id = created.data.id;
        onCreated(id);
      }
    } catch (e) {
      setError(e as Error);
      setBusy(false);
      setDraft(content);
      return;
    }

    seq.current += 1;
    const assistantId = `pending-${seq.current}`;
    setLocalMessages((prev) => [
      ...(prev ?? messages),
      { id: `local-${seq.current}`, role: "user", content, citations: [] },
      { id: assistantId, role: "assistant", content: "", citations: [], streaming: true },
    ]);

    const patch = (fn: (m: LocalMessage) => LocalMessage) => setLocalMessages((prev) => (prev ?? []).map((m) => (m.id === assistantId ? fn(m) : m)));

    abortRef.current = new AbortController();
    try {
      await streamChat(
        id,
        content,
        {
          onCitations: (citations) => patch((m) => ({ ...m, citations: citations as Citation[] })),
          onDelta: (delta) => patch((m) => ({ ...m, content: m.content + delta })),
          onDone: (message) => patch(() => ({ ...(message as ChatMessage), streaming: false })),
          onError: (message) => patch((m) => ({ ...m, streaming: false, error: message })),
        },
        abortRef.current.signal,
      );
    } catch (e) {
      if ((e as Error).name !== "AbortError") {
        setLocalMessages((prev) => (prev ?? []).filter((m) => m.id !== assistantId));
        setError(e as Error);
      }
    } finally {
      patch((m) => ({ ...m, streaming: false }));
      setBusy(false);
      abortRef.current = null;
      onActivity();
    }
  };

  const stop = () => abortRef.current?.abort();
  const current = options.find((o) => o.value === selected);

  return (
    <section className="flex min-h-0 min-w-0 flex-col bg-paper">
      <header className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line bg-surface px-4 py-2.5 sm:px-5 sm:py-3">
        <button
          onClick={onOpenList}
          className="-ml-1 rounded-md p-1.5 text-muted transition-colors hover:bg-paper hover:text-ink lg:hidden"
          aria-label="Show chats"
        >
          <PanelLeft className="size-5" />
        </button>
        <h1 className="mr-auto min-w-0 flex-1 truncate font-medium">{conversation?.data.title ?? "New chat"}</h1>

        {/* On phones the controls drop to their own row so the title stays readable. */}
        <div className="flex w-full items-center gap-3 sm:w-auto">
        <label className="flex min-w-0 flex-1 items-center gap-2 text-sm text-muted sm:flex-none">
          <span className="sr-only">Model</span>
          <Select
            value={selected}
            onChange={(e) => {
              setSelected(e.target.value);
              const [provider, model] = e.target.value.split("::");
              void updateSettings({ provider, model });
            }}
            className="h-8 w-full text-[13px] sm:w-auto sm:max-w-[220px]"
          >
            {(catalogue?.data ?? []).map((p) => (
              <optgroup key={p.provider} label={`${p.label}${!p.allowed ? " (upgrade)" : !p.configured ? " (no API key)" : ""}`}>
                {p.models.map((m) => (
                  <option key={m} value={`${p.provider}::${m}`} disabled={!p.allowed || !p.configured}>
                    {m}
                  </option>
                ))}
              </optgroup>
            ))}
          </Select>
        </label>

        <label className="flex shrink-0 cursor-pointer items-center gap-2 text-sm text-muted">
          <input
            type="checkbox"
            className="size-4 accent-[var(--color-action)]"
            checked={useKnowledge}
            onChange={(e) => {
              setUseKnowledge(e.target.checked);
              void updateSettings({ use_knowledge_base: e.target.checked });
            }}
          />
          <BookOpen className="size-4" aria-hidden /> <span className="hidden sm:inline">Use knowledge base</span>
          <span className="sm:hidden">Docs</span>
        </label>
        </div>
      </header>

      <div className="min-h-0 flex-1 overflow-y-auto">
        <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
          {messages.length === 0 ? (
            <div className="mx-auto max-w-xl pt-10">
              <h2 className="text-xl font-semibold tracking-[-0.01em]">Ask your documents anything</h2>
              <p className="mt-2 text-muted">
                Answers are grounded in your{" "}
                <Link href="/dashboard/documents" className="text-action hover:underline">
                  knowledge base
                </Link>{" "}
                and cite the passage they came from.
                {current && (
                  <>
                    {" "}
                    You&apos;re using <span className="text-ink">{current.label}</span> from {current.provider.label}.
                  </>
                )}
              </p>
              <div className="mt-6 space-y-2">
                {SUGGESTIONS.map((s) => (
                  <button
                    key={s}
                    onClick={() => send(s)}
                    disabled={!selected}
                    className="block w-full rounded-md border border-line bg-surface px-4 py-3 text-left text-sm hover:border-line-strong"
                  >
                    {s}
                  </button>
                ))}
              </div>
            </div>
          ) : (
            <ol className="space-y-8">
              {messages.map((m) => (
                <li key={m.id}>
                  {m.role === "user" ? (
                    <div className="flex justify-end">
                      <p className="max-w-[min(42rem,85%)] whitespace-pre-wrap rounded-lg bg-ink px-4 py-2.5 text-[15px] text-white">{m.content}</p>
                    </div>
                  ) : (
                    <AssistantMessage message={m} focused={focusedSource} onFocus={setFocusedSource} />
                  )}
                </li>
              ))}
            </ol>
          )}
          <div ref={bottomRef} />
        </div>
      </div>

      <div className="border-t border-line bg-surface px-3 py-3 sm:px-5 sm:py-4">
        <div className="mx-auto max-w-3xl">
          {error && (
            <div className="mb-3">
              <Notice tone="danger">
                {errorMessage(error)}{" "}
                {error instanceof ApiError && error.isQuota && (
                  <Link href="/dashboard/billing" className="font-medium underline">
                    See plans
                  </Link>
                )}
              </Notice>
            </div>
          )}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              void send(draft);
            }}
            className={clsx(
              "group/composer rounded-2xl border bg-surface shadow-sm shadow-ink/5 transition-all duration-200",
              "focus-within:border-action focus-within:shadow-lg focus-within:shadow-action/10 focus-within:ring-[3px] focus-within:ring-action-soft",
              busy ? "border-action/40" : "border-line-strong [&:hover:not(:focus-within)]:border-faint",
            )}
          >
            <label htmlFor="composer" className="sr-only">
              Message
            </label>
            <textarea
              id="composer"
              rows={1}
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && !e.shiftKey && !e.nativeEvent.isComposing) {
                  e.preventDefault();
                  void send(draft);
                }
              }}
              placeholder={busy ? "Writing an answer…" : "Ask a question about your documents…"}
              className="block max-h-52 min-h-12 w-full resize-none rounded-t-2xl bg-transparent px-4 pb-1 pt-3.5 text-[15px] leading-relaxed outline-none placeholder:text-faint focus:outline-none focus-visible:outline-none"
              style={{ fieldSizing: "content" } as React.CSSProperties}
            />

            <div className="flex items-center gap-2 px-2.5 pb-2.5 pt-1">
              {/* What this message will use, at a glance */}
              <div className="flex min-w-0 flex-1 items-center gap-1.5 text-xs">
                {current && (
                  <span className="inline-flex min-w-0 items-center gap-1.5 rounded-full bg-paper px-2.5 py-1 text-muted ring-1 ring-line">
                    <Sparkles className="size-3.5 shrink-0 text-action" aria-hidden />
                    <span className="truncate">{current.label}</span>
                  </span>
                )}
                <span
                  className={clsx(
                    "hidden items-center gap-1.5 rounded-full px-2.5 py-1 ring-1 transition-colors sm:inline-flex",
                    useKnowledge ? "bg-highlight-soft text-ink ring-highlight/60" : "bg-paper text-faint ring-line",
                  )}
                  title={useKnowledge ? "Answers use your knowledge base" : "Knowledge base is off for this chat"}
                >
                  <BookOpen className="size-3.5" aria-hidden />
                  {useKnowledge ? "Knowledge base" : "No documents"}
                </span>
              </div>

              <span className="hidden items-center gap-1 text-[11px] text-faint opacity-0 transition-opacity group-focus-within/composer:opacity-100 md:inline-flex">
                <kbd className="rounded border border-line bg-paper px-1.5 py-px font-sans">Shift</kbd>+
                <kbd className="rounded border border-line bg-paper px-1.5 py-px font-sans">Enter</kbd>
                new line
              </span>

              {busy ? (
                <button
                  type="button"
                  onClick={stop}
                  aria-label="Stop generating"
                  title="Stop generating"
                  className="relative flex size-10 shrink-0 items-center justify-center rounded-full bg-ink text-white transition-transform hover:scale-105 active:scale-95"
                >
                  <span className="absolute inset-0 animate-ping rounded-full bg-ink/25" aria-hidden />
                  <Square className="relative size-3.5 fill-current" />
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={!draft.trim() || !selected}
                  aria-label="Send message"
                  title="Send (Enter)"
                  className={clsx(
                    "flex size-10 shrink-0 items-center justify-center rounded-full transition-all duration-200",
                    "enabled:bg-action enabled:text-white enabled:shadow-md enabled:shadow-action/30 enabled:hover:-translate-y-0.5 enabled:hover:bg-action-hover enabled:hover:shadow-lg enabled:active:translate-y-0 enabled:active:scale-95",
                    "disabled:cursor-not-allowed disabled:bg-paper disabled:text-faint disabled:ring-1 disabled:ring-line",
                  )}
                >
                  <ArrowUp className="size-5" strokeWidth={2.5} />
                </button>
              )}
            </div>
          </form>
          <p className="mt-2 text-center text-xs text-faint">AI can make mistakes, so check the sources.</p>
        </div>
      </div>
    </section>
  );
}

/* ------------------------------------------------------ Answer + margin */

function AssistantMessage({ message, focused, onFocus }: { message: LocalMessage; focused: string | null; onFocus: (key: string) => void }) {
  const hasSources = message.citations.length > 0;

  return (
    <div className={clsx("grid gap-x-8 gap-y-4", hasSources && "xl:grid-cols-[minmax(0,1fr)_280px]")}>
      <div className="min-w-0 max-w-3xl text-[15px] leading-relaxed">
        {message.error ? (
          <Notice tone="danger">{message.error}</Notice>
        ) : message.content === "" && message.streaming ? (
          <span className="caret text-muted" aria-label="Thinking" />
        ) : (
          <div className={clsx(message.streaming && "streaming")}>
            <AnswerMarkdown content={message.content} messageId={message.id} onCite={(n) => onFocus(`${message.id}-${n}`)} />
          </div>
        )}
        {!message.streaming && message.model && (
          <p className="mt-3 text-xs text-faint">
            {message.model}
            {message.latency_ms ? `, ${(message.latency_ms / 1000).toFixed(1)}s` : ""}
            {message.output_tokens ? `, ${message.output_tokens.toLocaleString()} tokens` : ""}
          </p>
        )}
      </div>

      {hasSources && (
        <aside aria-label="Sources" className="min-w-0 space-y-3 xl:border-l xl:border-line xl:pl-5">
          {message.citations.map((c) => {
            const key = `${message.id}-${c.index}`;
            return (
              <figure
                key={key}
                id={`src-${key}`}
                className={clsx(
                  "scroll-mt-24 rounded-md px-3 py-2.5 transition-colors",
                  focused === key ? "bg-highlight-soft" : "bg-surface ring-1 ring-line",
                )}
              >
                <figcaption className="flex min-w-0 items-center gap-2 text-xs text-muted">
                  <span className="cite m-0 shrink-0">{c.index}</span>
                  <span className="min-w-0 truncate font-medium text-ink">{c.document_title}</span>
                  {c.page && <span className="shrink-0">p. {c.page}</span>}
                </figcaption>
                {/* Snippets can hold long unbroken strings (emails, URLs) that would push the page sideways. */}
                <blockquote className="mt-1.5 font-serif text-[13px] leading-relaxed text-muted [overflow-wrap:anywhere]">{c.snippet}</blockquote>
              </figure>
            );
          })}
        </aside>
      )}
    </div>
  );
}
