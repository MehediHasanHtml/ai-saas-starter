import { NextRequest, NextResponse } from "next/server";
import { TOKEN_COOKIE } from "@/lib/session";

// Optimistic auth check only (cookie presence). The API is the real
// authority: every request is verified by Sanctum and tenant middleware.
export function proxy(request: NextRequest) {
  const signedIn = request.cookies.has(TOKEN_COOKIE);
  const { pathname } = request.nextUrl;

  if (!signedIn && (pathname.startsWith("/dashboard") || pathname.startsWith("/admin"))) {
    const url = new URL("/login", request.url);
    url.searchParams.set("next", pathname);
    return NextResponse.redirect(url);
  }

  if (signedIn && (pathname === "/login" || pathname === "/register")) {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/dashboard/:path*", "/admin/:path*", "/login", "/register"],
};
