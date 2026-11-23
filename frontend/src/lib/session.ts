// Server-only helpers shared by route handlers.

export const TOKEN_COOKIE = "session_token";
export const ORG_COOKIE = "org_id";

export const API_URL = (process.env.API_URL ?? "http://localhost:8000").replace(/\/$/, "");

export const cookieOptions = {
  httpOnly: true,
  sameSite: "lax" as const,
  secure: process.env.NODE_ENV === "production",
  path: "/",
  maxAge: 60 * 60 * 24 * 30, // matches the 30-day Sanctum token
};
