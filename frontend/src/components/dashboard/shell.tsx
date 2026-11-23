"use client";

import clsx from "clsx";
import { BookOpen, ChevronsUpDown, CreditCard, LayoutGrid, LogOut, Menu, MessageSquare, Plus, Settings, Shield, Users, X } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Suspense, useEffect, useRef, useState } from "react";
import { SWRConfig } from "swr";
import { Logo } from "@/components/marketing/logo";
import { Spinner } from "@/components/ui";
import { SessionProvider, useSession } from "@/lib/use-session";

const NAV = [
  { href: "/dashboard", label: "Overview", icon: LayoutGrid, exact: true },
  { href: "/dashboard/chat", label: "Chat", icon: MessageSquare },
  { href: "/dashboard/documents", label: "Knowledge base", icon: BookOpen },
  { href: "/dashboard/team", label: "Team", icon: Users },
  { href: "/dashboard/billing", label: "Plan & billing", icon: CreditCard },
  { href: "/dashboard/settings", label: "Settings", icon: Settings },
];

const ADMIN_NAV = [
  { href: "/admin", label: "Platform overview", icon: LayoutGrid, exact: true },
  { href: "/admin/organizations", label: "Organizations", icon: Shield },
  { href: "/admin/users", label: "Users", icon: Users },
];

export function DashboardShell({ children, area = "app" }: { children: React.ReactNode; area?: "app" | "admin" }) {
  return (
    <SWRConfig value={{ revalidateOnFocus: false, shouldRetryOnError: false }}>
      <SessionProvider>
        {/* The app shell is client-rendered: its URL hooks stream in after the static prerender. */}
        <Suspense fallback={<FullScreenSpinner />}>
          <Frame area={area}>{children}</Frame>
        </Suspense>
      </SessionProvider>
    </SWRConfig>
  );
}

function FullScreenSpinner() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <Spinner label="Opening your workspace" />
    </div>
  );
}

function Frame({ children, area }: { children: React.ReactNode; area: "app" | "admin" }) {
  const { me, loading } = useSession();
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  const router = useRouter();

  useEffect(() => {
    if (area === "admin" && me && !me.user.is_super_admin) router.replace("/dashboard");
  }, [area, me, router]);

  // Mobile drawer: close on Escape, and stop the page behind it from scrolling.
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    window.addEventListener("keydown", onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = prev;
    };
  }, [open]);

  if (loading || !me) return <FullScreenSpinner />;

  const current = (area === "app" ? NAV : ADMIN_NAV).find((item) => (item.exact ? pathname === item.href : pathname.startsWith(item.href)));

  return (
    <div className="min-h-dvh lg:grid lg:grid-cols-[248px_1fr]">
      <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line bg-surface/90 px-3 backdrop-blur-md lg:hidden">
        <button className="rounded-md p-2 transition-colors hover:bg-paper" onClick={() => setOpen(true)} aria-label="Open navigation" aria-expanded={open}>
          <Menu className="size-5" />
        </button>
        <Logo href="/dashboard" />
        {current && <span className="ml-auto truncate text-sm text-muted">{current.label}</span>}
      </header>

      <div
        className={clsx(
          "fixed inset-0 z-40 bg-ink/35 backdrop-blur-[2px] transition-opacity duration-300 lg:hidden",
          open ? "opacity-100" : "pointer-events-none opacity-0",
        )}
        onClick={() => setOpen(false)}
        aria-hidden
      />

      <aside
        className={clsx(
          "fixed inset-y-0 left-0 z-50 flex w-[min(280px,85vw)] flex-col border-r border-line bg-surface transition-transform duration-300 ease-out lg:sticky lg:top-0 lg:h-dvh lg:w-[248px] lg:translate-x-0",
          open ? "translate-x-0 shadow-2xl shadow-ink/20 lg:shadow-none" : "-translate-x-full",
        )}
      >
        <div className="flex h-14 items-center justify-between px-4">
          <Logo />
          <button className="rounded p-1.5 hover:bg-paper lg:hidden" onClick={() => setOpen(false)} aria-label="Close navigation">
            <X className="size-4" />
          </button>
        </div>

        {area === "app" ? <OrgSwitcher /> : <p className="px-4 pb-3 text-sm font-medium text-muted">Platform admin</p>}

        <nav className="flex-1 space-y-0.5 overflow-y-auto px-2 py-2" aria-label="Main">
          {(area === "app" ? NAV : ADMIN_NAV).map((item) => {
            const active = item.exact ? pathname === item.href : pathname.startsWith(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setOpen(false)}
                aria-current={active ? "page" : undefined}
                className={clsx(
                  "group relative flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors",
                  active ? "bg-action-soft font-medium text-action" : "text-muted hover:bg-paper hover:text-ink",
                )}
              >
                <span
                  aria-hidden
                  className={clsx(
                    "absolute -left-2 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-action transition-all duration-300",
                    active ? "opacity-100" : "scale-y-0 opacity-0",
                  )}
                />
                <item.icon className={clsx("size-4 transition-transform", !active && "group-hover:translate-x-0.5")} aria-hidden />
                {item.label}
              </Link>
            );
          })}
        </nav>

        <div className="border-t border-line p-2">
          {me.user.is_super_admin && (
            <Link
              href={area === "app" ? "/admin" : "/dashboard"}
              className="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-muted hover:bg-paper hover:text-ink"
            >
              <Shield className="size-4" aria-hidden />
              {area === "app" ? "Platform admin" : "Back to workspace"}
            </Link>
          )}
          <UserRow />
        </div>
      </aside>

      {/* Full-width content. The chat screen cancels this padding with matching negative margins. */}
      <main className="min-w-0 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">{children}</main>
    </div>
  );
}

function OrgSwitcher() {
  const { me, organization, switchOrganization } = useSession();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const router = useRouter();

  useEffect(() => {
    const close = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && setOpen(false);
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);

  if (!me || !organization) return null;

  return (
    <div ref={ref} className="relative px-2 pb-2">
      <button
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center gap-2.5 rounded-md border border-line px-2.5 py-2 text-left hover:bg-paper"
      >
        <span className="flex size-7 shrink-0 items-center justify-center rounded bg-ink text-xs font-semibold text-white">
          {organization.name.slice(0, 1).toUpperCase()}
        </span>
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-medium">{organization.name}</span>
          <span className="block text-xs text-muted">{organization.plan.name} plan</span>
        </span>
        <ChevronsUpDown className="size-4 text-faint" aria-hidden />
      </button>

      {open && (
        <div className="absolute inset-x-2 top-full z-20 mt-1 rounded-md border border-line bg-surface p-1 shadow-lg shadow-ink/5">
          {me.organizations.map((org) => (
            <button
              key={org.id}
              onClick={async () => {
                setOpen(false);
                if (org.id !== organization.id) await switchOrganization(org.id);
              }}
              className={clsx(
                "flex w-full items-center justify-between rounded px-2.5 py-2 text-left text-sm hover:bg-paper",
                org.id === organization.id && "font-medium",
              )}
            >
              <span className="truncate">{org.name}</span>
              <span className="text-xs text-muted">{org.role}</span>
            </button>
          ))}
          <button
            onClick={() => {
              setOpen(false);
              router.push("/dashboard/settings#new-organization");
            }}
            className="mt-1 flex w-full items-center gap-2 rounded border-t border-line px-2.5 py-2 text-left text-sm text-muted hover:bg-paper hover:text-ink"
          >
            <Plus className="size-4" aria-hidden /> New organization
          </button>
        </div>
      )}
    </div>
  );
}

function UserRow() {
  const { me, logout } = useSession();
  if (!me) return null;
  return (
    <div className="flex items-center gap-2.5 px-2.5 py-2">
      <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-paper text-xs font-semibold ring-1 ring-line">
        {me.user.name.slice(0, 1).toUpperCase()}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-medium">{me.user.name}</span>
        <span className="block truncate text-xs text-muted">{me.user.email}</span>
      </span>
      <button onClick={logout} className="rounded p-1.5 text-muted hover:bg-paper hover:text-ink" aria-label="Sign out" title="Sign out">
        <LogOut className="size-4" />
      </button>
    </div>
  );
}
