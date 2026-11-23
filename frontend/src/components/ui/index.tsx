"use client";

import clsx from "clsx";
import { Loader2 } from "lucide-react";
import { forwardRef } from "react";
import type { Meter as MeterData } from "@/lib/types";

/* ---------------------------------------------------------------- Button */

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: "primary" | "secondary" | "ghost" | "danger";
  size?: "sm" | "md";
  loading?: boolean;
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = "primary", size = "md", loading, className, children, disabled, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      disabled={disabled || loading}
      className={clsx(
        "inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-55",
        size === "sm" ? "h-8 px-3 text-[13px]" : "h-10 px-4 text-sm",
        variant === "primary" && "bg-action text-white hover:bg-action-hover",
        variant === "secondary" && "border border-line-strong bg-surface text-ink hover:bg-paper",
        variant === "ghost" && "text-muted hover:bg-paper hover:text-ink",
        variant === "danger" && "border border-line-strong bg-surface text-danger hover:bg-danger-soft",
        className,
      )}
      {...props}
    >
      {loading && <Loader2 className="size-4 animate-spin" aria-hidden />}
      {children}
    </button>
  );
});

/* ----------------------------------------------------------- Form fields */

export const Input = forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...props }, ref) {
  return (
    <input
      ref={ref}
      className={clsx(
        "h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-faint focus:border-action focus:outline-none focus:ring-2 focus:ring-action-soft",
        className,
      )}
      {...props}
    />
  );
});

export const Textarea = forwardRef<HTMLTextAreaElement, React.TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea(
  { className, ...props },
  ref,
) {
  return (
    <textarea
      ref={ref}
      className={clsx(
        "w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink placeholder:text-faint focus:border-action focus:outline-none focus:ring-2 focus:ring-action-soft",
        className,
      )}
      {...props}
    />
  );
});

export const Select = forwardRef<HTMLSelectElement, React.SelectHTMLAttributes<HTMLSelectElement>>(function Select(
  { className, children, ...props },
  ref,
) {
  return (
    <select
      ref={ref}
      className={clsx(
        "h-10 rounded-md border border-line-strong bg-surface px-2.5 text-sm text-ink focus:border-action focus:outline-none focus:ring-2 focus:ring-action-soft",
        className,
      )}
      {...props}
    >
      {children}
    </select>
  );
});

export function Field({ label, hint, error, children }: { label: string; hint?: string; error?: string; children: React.ReactNode }) {
  return (
    <label className="block space-y-1.5">
      <span className="text-sm font-medium text-ink">{label}</span>
      {children}
      {error ? (
        <span className="block text-[13px] text-danger">{error}</span>
      ) : hint ? (
        <span className="block text-[13px] text-muted">{hint}</span>
      ) : null}
    </label>
  );
}

/* ------------------------------------------------------------- Surfaces */

export function Panel({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={clsx("rounded-lg border border-line bg-surface", className)} {...props}>
      {children}
    </div>
  );
}

export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: React.ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
      <div className="max-w-2xl">
        <h1 className="text-[22px] font-semibold leading-tight tracking-[-0.01em] sm:text-[26px]">{title}</h1>
        {description && <p className="mt-1.5 text-muted">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

export function Badge({
  tone = "neutral",
  children,
}: {
  tone?: "neutral" | "success" | "danger" | "action" | "highlight";
  children: React.ReactNode;
}) {
  return (
    <span
      className={clsx(
        "inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-xs font-medium",
        tone === "neutral" && "bg-paper text-muted ring-1 ring-line",
        tone === "success" && "bg-success-soft text-success",
        tone === "danger" && "bg-danger-soft text-danger",
        tone === "action" && "bg-action-soft text-action",
        tone === "highlight" && "bg-highlight-soft text-ink",
      )}
    >
      {children}
    </span>
  );
}

export function Notice({ tone = "neutral", children }: { tone?: "neutral" | "danger" | "success"; children: React.ReactNode }) {
  return (
    <div
      role={tone === "danger" ? "alert" : "status"}
      className={clsx(
        "rounded-md border px-3.5 py-2.5 text-sm",
        tone === "neutral" && "border-line bg-paper text-muted",
        tone === "danger" && "border-danger/25 bg-danger-soft text-danger",
        tone === "success" && "border-success/25 bg-success-soft text-success",
      )}
    >
      {children}
    </div>
  );
}

export function Spinner({ label = "Loading" }: { label?: string }) {
  return (
    <div className="flex items-center gap-2 py-10 text-sm text-muted" role="status">
      <Loader2 className="size-4 animate-spin" aria-hidden /> {label}…
    </div>
  );
}

export function EmptyState({ title, body, action }: { title: string; body: string; action?: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-dashed border-line-strong px-6 py-12 text-center">
      <p className="font-medium">{title}</p>
      <p className="mx-auto mt-1 max-w-md text-sm text-muted">{body}</p>
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

/* --------------------------------------------------------------- Meters */

export function UsageMeter({ label, meter, unit = "" }: { label: string; meter: MeterData; unit?: string }) {
  const unlimited = meter.limit === null;
  const near = !unlimited && meter.percent >= 80;
  return (
    <div>
      <div className="flex items-baseline justify-between gap-3 text-sm">
        <span className="text-muted">{label}</span>
        <span className="tabular-nums">
          <span className="font-semibold">{meter.used.toLocaleString()}</span>
          <span className="text-muted"> / {unlimited ? "unlimited" : `${meter.limit!.toLocaleString()}${unit}`}</span>
        </span>
      </div>
      <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-paper ring-1 ring-line" aria-hidden>
        <div
          className={clsx("h-full origin-left animate-[bar-grow-x_0.8s_var(--ease-out-soft)_both] rounded-full", near ? "bg-danger" : "bg-action")}
          style={{ width: unlimited ? "4%" : `${Math.max(meter.percent, meter.used ? 2 : 0)}%` }}
        />
      </div>
    </div>
  );
}

/* ------------------------------------------------------------ Bar chart */

export function BarChart({
  data,
  valueKey,
  label,
  className = "h-36",
}: {
  data: Record<string, number | string>[];
  valueKey: string;
  label: string;
  className?: string;
}) {
  const values = data.map((d) => Number(d[valueKey]) || 0);
  const max = Math.max(1, ...values);
  const total = values.reduce((a, b) => a + b, 0);

  return (
    <figure>
      <figcaption className="sr-only">
        {label}: {total} over {data.length} days
      </figcaption>
      <div className={clsx("flex items-end gap-[2px] sm:gap-[3px]", className)} aria-hidden>
        {data.map((d, i) => (
          <div key={i} className="group relative flex h-full flex-1 items-end">
            <div
              className="w-full origin-bottom animate-bar rounded-t-[3px] bg-action/75 transition-colors group-hover:bg-action"
              style={{ height: `${(values[i] / max) * 100}%`, minHeight: values[i] ? 2 : 0, animationDelay: `${i * 18}ms` }}
            />
            <div className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-ink px-2 py-1 text-xs text-white group-hover:block">
              {String(d.date)}: {values[i].toLocaleString()}
            </div>
          </div>
        ))}
      </div>
      <div className="mt-2 flex justify-between text-xs text-faint">
        <span>{String(data[0]?.date ?? "")}</span>
        <span>{String(data[data.length - 1]?.date ?? "")}</span>
      </div>
    </figure>
  );
}

export function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
