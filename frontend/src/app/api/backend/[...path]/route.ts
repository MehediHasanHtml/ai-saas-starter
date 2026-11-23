import { NextRequest } from "next/server";
import { API_URL, ORG_COOKIE, TOKEN_COOKIE } from "@/lib/session";

// Authenticated pass-through to the Laravel API:
//   /api/backend/documents  ->  {API_URL}/api/documents
// Adds the Bearer token (httpOnly cookie) and X-Organization-Id, and streams
// the response body back untouched, so Server-Sent Events work end to end.

const FORWARD_REQUEST_HEADERS = ["content-type", "accept", "accept-language"];
const FORWARD_RESPONSE_HEADERS = ["content-type", "cache-control", "retry-after", "x-ratelimit-limit", "x-ratelimit-remaining"];

async function handle(request: NextRequest, ctx: RouteContext<"/api/backend/[...path]">) {
  const { path } = await ctx.params;
  const url = new URL(`${API_URL}/api/${path.map(encodeURIComponent).join("/")}`);
  request.nextUrl.searchParams.forEach((value, key) => url.searchParams.append(key, value));

  const headers = new Headers();
  for (const name of FORWARD_REQUEST_HEADERS) {
    const value = request.headers.get(name);
    if (value) headers.set(name, value);
  }
  if (!headers.has("accept")) headers.set("accept", "application/json");

  const token = request.cookies.get(TOKEN_COOKIE)?.value;
  if (token) headers.set("authorization", `Bearer ${token}`);

  const org = request.cookies.get(ORG_COOKIE)?.value;
  if (org && /^\d+$/.test(org)) headers.set("x-organization-id", org);

  headers.set("x-forwarded-for", request.headers.get("x-forwarded-for") ?? "");

  const hasBody = !["GET", "HEAD"].includes(request.method);

  let upstream: Response;
  try {
    upstream = await fetch(url, {
      method: request.method,
      headers,
      body: hasBody ? await request.arrayBuffer() : undefined,
      cache: "no-store",
      redirect: "manual",
      signal: request.signal,
    });
  } catch {
    return Response.json({ message: "The API is unreachable. Is the Laravel server running?" }, { status: 502 });
  }

  const responseHeaders = new Headers();
  for (const name of FORWARD_RESPONSE_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) responseHeaders.set(name, value);
  }
  if (responseHeaders.get("content-type")?.startsWith("text/event-stream")) {
    responseHeaders.set("cache-control", "no-cache, no-transform");
    responseHeaders.set("x-accel-buffering", "no");
  }

  return new Response(upstream.body, { status: upstream.status, headers: responseHeaders });
}

export { handle as GET, handle as POST, handle as PUT, handle as PATCH, handle as DELETE };
