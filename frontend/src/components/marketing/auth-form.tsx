"use client";

import Link from "next/link";
import { useState } from "react";
import { Logo } from "@/components/marketing/logo";
import { Button, Field, Input, Notice } from "@/components/ui";

export function AuthForm({ mode }: { mode: "login" | "register" }) {
  const [form, setForm] = useState({ name: "", email: "", password: "", organization_name: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, [key]: e.target.value });

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setErrors({});
    setMessage(null);

    const body = mode === "login" ? { email: form.email, password: form.password } : form;
    const res = await fetch(`/api/auth/${mode}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      setLoading(false);
      if (data.errors) {
        setErrors(Object.fromEntries(Object.entries(data.errors as Record<string, string[]>).map(([k, v]) => [k, v[0]])));
      } else {
        setMessage(data.message ?? "Sign-in failed.");
      }
      return;
    }

    const next = new URLSearchParams(window.location.search).get("next");
    window.location.assign(next && next.startsWith("/") && !next.startsWith("//") ? next : "/dashboard");
  };

  return (
    <div className="flex min-h-screen flex-col items-center justify-center px-5 py-12">
      <div className="w-full max-w-sm">
        <Logo />
        <h1 className="mt-8 text-2xl font-semibold tracking-[-0.01em]">{mode === "login" ? "Sign in to your workspace" : "Create your workspace"}</h1>
        <p className="mt-1.5 text-muted">{mode === "login" ? "Welcome back." : "Free to start. No card needed."}</p>

        <form onSubmit={submit} className="mt-8 space-y-4">
          {message && <Notice tone="danger">{message}</Notice>}
          {mode === "register" && (
            <Field label="Your name" error={errors.name}>
              <Input autoComplete="name" required value={form.name} onChange={set("name")} />
            </Field>
          )}
          <Field label="Work email" error={errors.email}>
            <Input type="email" autoComplete="email" required value={form.email} onChange={set("email")} />
          </Field>
          <Field label="Password" error={errors.password} hint={mode === "register" ? "At least 8 characters." : undefined}>
            <Input
              type="password"
              autoComplete={mode === "login" ? "current-password" : "new-password"}
              required
              minLength={mode === "register" ? 8 : undefined}
              value={form.password}
              onChange={set("password")}
            />
          </Field>
          {mode === "register" && (
            <Field label="Company or team name" hint="Optional. You can invite teammates later." error={errors.organization_name}>
              <Input value={form.organization_name} onChange={set("organization_name")} />
            </Field>
          )}
          <Button type="submit" className="w-full" loading={loading}>
            {mode === "login" ? "Sign in" : "Create workspace"}
          </Button>
        </form>

        <p className="mt-6 text-sm text-muted">
          {mode === "login" ? (
            <>
              New here?{" "}
              <Link href="/register" className="text-action hover:underline">
                Create a workspace
              </Link>
            </>
          ) : (
            <>
              Already have an account?{" "}
              <Link href="/login" className="text-action hover:underline">
                Sign in
              </Link>
            </>
          )}
        </p>

        {/* Local/demo builds only. Never set NEXT_PUBLIC_SHOW_DEMO_LOGIN on a live site. */}
        {mode === "login" && process.env.NEXT_PUBLIC_SHOW_DEMO_LOGIN === "true" && (
          <p className="mt-8 rounded-md bg-surface px-3 py-2.5 text-[13px] text-muted ring-1 ring-line">
            Demo data is seeded: sign in as <code className="font-mono text-ink">admin@example.com</code> or{" "}
            <code className="font-mono text-ink">demo@example.com</code> with the password <code className="font-mono text-ink">password</code>.
          </p>
        )}
      </div>
    </div>
  );
}
