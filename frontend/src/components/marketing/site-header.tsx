"use client";

import clsx from "clsx";
import { Menu, X } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { Logo } from "@/components/marketing/logo";

const LINKS = [
  { href: "#how", id: "how", label: "How it works" },
  { href: "#models", id: "models", label: "Models" },
  { href: "#pricing", id: "pricing", label: "Pricing" },
];

/** Fixed top bar for the marketing site: glass background once scrolled, section highlighting, mobile menu. */
export function SiteHeader() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState<string | null>(null);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Highlight the nav link for the section in view.
  useEffect(() => {
    const sections = LINKS.map((l) => document.getElementById(l.id)).filter(Boolean) as HTMLElement[];
    if (!sections.length || typeof IntersectionObserver === "undefined") return;
    const io = new IntersectionObserver(
      (entries) => {
        for (const e of entries) if (e.isIntersecting) setActive(e.target.id);
      },
      { rootMargin: "-45% 0px -50% 0px" },
    );
    sections.forEach((s) => io.observe(s));
    return () => io.disconnect();
  }, []);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open]);

  return (
    <header
      className={clsx(
        "fixed inset-x-0 top-0 z-50 animate-slide-down transition-[background-color,box-shadow,border-color] duration-300",
        scrolled || open ? "border-b border-line bg-surface/85 shadow-[0_6px_24px_-12px_rgb(23_32_51/0.18)] backdrop-blur-md" : "border-b border-transparent bg-transparent",
      )}
    >
      <div className={clsx("mx-auto flex max-w-6xl items-center justify-between px-5 transition-[height] duration-300 sm:px-8", scrolled ? "h-14" : "h-[72px]")}>
        <Logo />

        <nav className="hidden items-center gap-7 text-sm md:flex" aria-label="Main">
          {LINKS.map((l) => (
            <a
              key={l.id}
              href={l.href}
              aria-current={active === l.id ? "true" : undefined}
              className={clsx("link-grow py-1", active === l.id ? "text-ink" : "text-muted hover:text-ink")}
            >
              {l.label}
            </a>
          ))}
        </nav>

        <div className="flex items-center gap-1 text-sm">
          <Link href="/login" className="hidden rounded-md px-3 py-2 text-muted transition-colors hover:text-ink sm:block">
            Sign in
          </Link>
          <Link
            href="/register"
            className="rounded-md bg-ink px-3.5 py-2 font-medium text-white transition-all hover:-translate-y-px hover:bg-ink/90 hover:shadow-md hover:shadow-ink/20 active:translate-y-0"
          >
            Start free
          </Link>
          <button
            className="ml-1 rounded-md p-2 text-ink transition-colors hover:bg-paper md:hidden"
            onClick={() => setOpen((v) => !v)}
            aria-expanded={open}
            aria-controls="mobile-menu"
            aria-label={open ? "Close menu" : "Open menu"}
          >
            {open ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        </div>
      </div>

      {/* Mobile menu: grid-rows trick animates height without measuring. */}
      <div id="mobile-menu" className={clsx("grid transition-[grid-template-rows] duration-300 ease-out md:hidden", open ? "grid-rows-[1fr]" : "grid-rows-[0fr]")}>
        <div className="overflow-hidden">
          <nav className="flex flex-col gap-1 px-5 pb-5 pt-1 sm:px-8" aria-label="Mobile">
            {LINKS.map((l, i) => (
              <a
                key={l.id}
                href={l.href}
                onClick={() => setOpen(false)}
                className={clsx(
                  "rounded-md px-3 py-2.5 text-[15px] transition-all duration-300 hover:bg-paper",
                  open ? "translate-x-0 opacity-100" : "-translate-x-2 opacity-0",
                  active === l.id ? "font-medium text-ink" : "text-muted",
                )}
                style={{ transitionDelay: open ? `${60 + i * 40}ms` : "0ms" }}
              >
                {l.label}
              </a>
            ))}
            <Link href="/login" onClick={() => setOpen(false)} className="rounded-md px-3 py-2.5 text-[15px] text-muted hover:bg-paper sm:hidden">
              Sign in
            </Link>
          </nav>
        </div>
      </div>
    </header>
  );
}
