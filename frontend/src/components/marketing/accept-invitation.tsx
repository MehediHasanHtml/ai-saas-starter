"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useState } from "react";
import { Logo } from "@/components/marketing/logo";
import { Button, Notice } from "@/components/ui";
import { api, ApiError, errorMessage } from "@/lib/api";
import type { Organization } from "@/lib/types";

export function AcceptInvitation() {
  const { token } = useParams<{ token: string }>();
  const [state, setState] = useState<"idle" | "busy" | "error" | "signin">("idle");
  const [error, setError] = useState("");

  const accept = async () => {
    setState("busy");
    try {
      const res = await api<{ data: Organization }>(`/invitations/${encodeURIComponent(token)}/accept`, { method: "POST" });
      await fetch("/api/auth/organization", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: res.data.id }),
      });
      // Full reload on purpose: drops all cached data from the previous session/tenant.
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination
      window.location.assign("/dashboard");
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) return setState("signin");
      setError(errorMessage(e));
      setState("error");
    }
  };

  const next = `/invite/${token}`;

  return (
    <div className="flex min-h-screen items-center justify-center px-5">
      <div className="w-full max-w-sm">
        <Logo />
        <h1 className="mt-8 text-2xl font-semibold">You&apos;ve been invited to a team</h1>
        <p className="mt-1.5 text-muted">Join to share its knowledge base and plan. Sign in with the email address the invitation was sent to.</p>
        <div className="mt-6 space-y-3">
          {state === "error" && <Notice tone="danger">{error}</Notice>}
          <Button className="w-full" onClick={accept} loading={state === "busy"}>
            Accept invitation
          </Button>
          <p className="text-sm text-muted">
            Not signed in?{" "}
            <Link href={`/login?next=${encodeURIComponent(next)}`} className="text-action hover:underline">
              Sign in
            </Link>{" "}
            or{" "}
            <Link href={`/register?next=${encodeURIComponent(next)}`} className="text-action hover:underline">
              create an account
            </Link>
            .
          </p>
        </div>
      </div>
    </div>
  );
}
