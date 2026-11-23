import { NextRequest, NextResponse } from "next/server";
import { API_URL, ORG_COOKIE, TOKEN_COOKIE, cookieOptions } from "@/lib/session";

// Backend-for-frontend auth: exchanges credentials for a Sanctum token and
// stores it in an httpOnly cookie, so client JavaScript never handles it.
//
//   POST /api/auth/login     { email, password }
//   POST /api/auth/register  { name, email, password, organization_name? }
//   POST /api/auth/logout
//   POST /api/auth/organization { id }   select the active organization

export async function POST(request: NextRequest, ctx: RouteContext<"/api/auth/[action]">) {
  const { action } = await ctx.params;

  if (action === "logout") {
    const token = request.cookies.get(TOKEN_COOKIE)?.value;
    if (token) {
      await fetch(`${API_URL}/api/auth/logout`, {
        method: "POST",
        headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
      }).catch(() => undefined);
    }
    const res = NextResponse.json({ ok: true });
    res.cookies.delete(TOKEN_COOKIE);
    res.cookies.delete(ORG_COOKIE);
    return res;
  }

  if (action === "organization") {
    const { id } = await request.json();
    const token = request.cookies.get(TOKEN_COOKIE)?.value;
    const upstream = await fetch(`${API_URL}/api/organizations/${Number(id)}/switch`, {
      method: "POST",
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    });
    const res = NextResponse.json(await upstream.json(), { status: upstream.status });
    if (upstream.ok) res.cookies.set(ORG_COOKIE, String(Number(id)), { ...cookieOptions, httpOnly: false });
    return res;
  }

  if (action !== "login" && action !== "register") {
    return NextResponse.json({ message: "Not found" }, { status: 404 });
  }

  const upstream = await fetch(`${API_URL}/api/auth/${action}`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(await request.json()),
  });

  const data = await upstream.json().catch(() => ({ message: "The API is unreachable." }));

  if (!upstream.ok) {
    return NextResponse.json(data, { status: upstream.status });
  }

  const res = NextResponse.json({ user: data.user });
  res.cookies.set(TOKEN_COOKIE, data.token, cookieOptions);
  if (data.user?.current_organization_id) {
    res.cookies.set(ORG_COOKIE, String(data.user.current_organization_id), { ...cookieOptions, httpOnly: false });
  }
  return res;
}
