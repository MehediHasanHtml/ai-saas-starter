"use client";

// Browser-side API client. Every call goes through the Next.js route handler
// at /api/backend/*, which attaches the httpOnly session token and the
// selected organization. The browser never sees the API token.

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public data: Record<string, unknown> = {},
  ) {
    super(message);
  }

  get isQuota() {
    return this.status === 402 || this.data.code === "quota_exceeded";
  }

  /** First validation message, if the API returned 422 errors. */
  get fieldError(): string | undefined {
    const errors = this.data.errors as Record<string, string[]> | undefined;
    return errors ? Object.values(errors)[0]?.[0] : undefined;
  }
}

const BASE = "/api/backend";

export async function api<T = unknown>(path: string, init: RequestInit & { json?: unknown } = {}): Promise<T> {
  const { json, headers, ...rest } = init;
  const res = await fetch(`${BASE}${path.startsWith("/") ? path : `/${path}`}`, {
    ...rest,
    headers: {
      Accept: "application/json",
      ...(json !== undefined ? { "Content-Type": "application/json" } : {}),
      ...headers,
    },
    body: json !== undefined ? JSON.stringify(json) : rest.body,
  });

  if (res.status === 401 && typeof window !== "undefined") {
    // Full reload on purpose: drops all cached data from the previous session/tenant.
    // eslint-disable-next-line @next/next/no-location-assign-relative-destination
    window.location.assign(`/login?next=${encodeURIComponent(window.location.pathname)}`);
  }

  const text = await res.text();
  const data = text ? safeJson(text) : {};

  if (!res.ok) {
    const message = (data.message as string) || `Request failed (${res.status})`;
    throw new ApiError(message, res.status, data);
  }

  return data as T;
}

function safeJson(text: string): Record<string, unknown> {
  try {
    return JSON.parse(text);
  } catch {
    return { message: text.slice(0, 200) };
  }
}

/** SWR fetcher */
export const fetcher = <T>(path: string) => api<T>(path);

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.fieldError ?? error.message;
  if (error instanceof Error) return error.message;
  return "Something went wrong.";
}

/**
 * POST a chat message and consume the Server-Sent Events stream.
 * Events: start, citations, delta, done, error (see MessageController).
 */
export async function streamChat(
  conversationId: number,
  content: string,
  handlers: {
    onCitations?: (citations: unknown[]) => void;
    onDelta?: (text: string) => void;
    onDone?: (message: unknown) => void;
    onError?: (message: string) => void;
  },
  signal?: AbortSignal,
) {
  const res = await fetch(`${BASE}/conversations/${conversationId}/messages`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "text/event-stream" },
    body: JSON.stringify({ content, stream: true }),
    signal,
  });

  if (!res.ok || !res.body) {
    const data = safeJson(await res.text());
    throw new ApiError((data.message as string) || "Could not send message.", res.status, data);
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { value, done } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });

    let boundary: number;
    while ((boundary = buffer.indexOf("\n\n")) !== -1) {
      const frame = buffer.slice(0, boundary);
      buffer = buffer.slice(boundary + 2);

      let event = "message";
      const dataLines: string[] = [];
      for (const line of frame.split("\n")) {
        if (line.startsWith("event:")) event = line.slice(6).trim();
        else if (line.startsWith("data:")) dataLines.push(line.slice(5).trimStart());
      }
      if (!dataLines.length) continue;

      const payload = safeJson(dataLines.join("\n")) as Record<string, unknown> & unknown[];
      if (event === "citations") handlers.onCitations?.(payload as unknown[]);
      else if (event === "delta") handlers.onDelta?.(String(payload.text ?? ""));
      else if (event === "done") handlers.onDone?.(payload);
      else if (event === "error") handlers.onError?.(String(payload.message ?? "Stream error"));
    }
  }
}
