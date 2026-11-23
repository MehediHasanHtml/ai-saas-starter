"use client";

import useSWR from "swr";
import { BarChart, Notice, PageHeader, Panel, Spinner } from "@/components/ui";
import { errorMessage, fetcher } from "@/lib/api";

interface Stats {
  users: number;
  users_new_30d: number;
  organizations: number;
  suspended: number;
  plans: Record<string, number>;
  mrr: number;
  documents: number;
  chunks: number;
  messages_this_month: number;
  tokens_this_month: number;
  by_provider: { provider: string; messages: number; tokens: number }[];
}

interface Daily {
  date: string;
  messages: number;
  tokens: number;
  active_orgs: number;
  signups: number;
}

export default function AdminOverview() {
  const stats = useSWR<Stats>("/admin/stats", fetcher);
  const usage = useSWR<{ data: Daily[] }>("/admin/usage", fetcher);

  if (stats.error) return <Notice tone="danger">{errorMessage(stats.error)}</Notice>;
  if (!stats.data) return <Spinner />;
  const s = stats.data;
  const totalPlans = Object.values(s.plans).reduce((a, b) => a + b, 0) || 1;
  const maxProvider = Math.max(1, ...s.by_provider.map((p) => Number(p.messages)));

  const figures = [
    { label: "Monthly recurring revenue", value: `$${s.mrr.toLocaleString()}`, note: "Excludes comped plans" },
    { label: "Organizations", value: s.organizations.toLocaleString(), note: s.suspended ? `${s.suspended} suspended` : "None suspended" },
    { label: "Users", value: s.users.toLocaleString(), note: `${s.users_new_30d} joined in the last 30 days` },
    { label: "AI messages this month", value: s.messages_this_month.toLocaleString(), note: `${new Intl.NumberFormat("en", { notation: "compact" }).format(s.tokens_this_month)} tokens` },
  ];

  return (
    <div className="w-full">
      <PageHeader title="Platform overview" description="Every tenant, across all plans and providers." />

      <Panel className="grid divide-y divide-line sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-4 lg:divide-x">
        {figures.map((f) => (
          <div key={f.label} className="p-5">
            <p className="text-sm text-muted">{f.label}</p>
            <p className="mt-1 text-[28px] font-semibold tracking-[-0.02em] tabular-nums">{f.value}</p>
            <p className="text-[13px] text-faint">{f.note}</p>
          </div>
        ))}
      </Panel>

      <div className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr] lg:items-start">
        <Panel className="p-6">
          <h2 className="mb-5 font-semibold">Messages per day</h2>
          {usage.data ? (
            <BarChart data={usage.data.data as unknown as Record<string, number | string>[]} valueKey="messages" label="Platform messages per day" />
          ) : (
            <Spinner />
          )}
        </Panel>

        <Panel className="p-6">
          <h2 className="font-semibold">Organizations by plan</h2>
          <div className="mt-4 flex h-2.5 overflow-hidden rounded-full bg-paper ring-1 ring-line" aria-hidden>
            {Object.entries(s.plans).map(([plan, n], i) => (
              <div key={plan} style={{ width: `${(n / totalPlans) * 100}%` }} className={["bg-line-strong", "bg-action", "bg-ink"][i % 3]} />
            ))}
          </div>
          <ul className="mt-4 space-y-2 text-sm">
            {Object.entries(s.plans).map(([plan, n], i) => (
              <li key={plan} className="flex items-center gap-2">
                <span className={["bg-line-strong", "bg-action", "bg-ink"][i % 3] + " size-2.5 rounded-sm"} aria-hidden />
                <span className="flex-1 capitalize">{plan}</span>
                <span className="tabular-nums text-muted">{n}</span>
              </li>
            ))}
          </ul>

          <h2 className="mt-8 font-semibold">Messages by provider</h2>
          <ul className="mt-3 space-y-3 text-sm">
            {s.by_provider.length === 0 && <li className="text-muted">No chat traffic this month.</li>}
            {s.by_provider.map((p) => (
              <li key={p.provider}>
                <div className="flex justify-between">
                  <span>{p.provider}</span>
                  <span className="tabular-nums text-muted">{Number(p.messages).toLocaleString()}</span>
                </div>
                <div className="mt-1 h-1.5 rounded-full bg-paper">
                  <div className="h-full rounded-full bg-action" style={{ width: `${(Number(p.messages) / maxProvider) * 100}%` }} />
                </div>
              </li>
            ))}
          </ul>
        </Panel>
      </div>

      <p className="mt-6 text-sm text-muted">
        Knowledge base: {s.documents.toLocaleString()} documents, {s.chunks.toLocaleString()} indexed passages.
      </p>
    </div>
  );
}
