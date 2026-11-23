"use client";

import { ArrowUpRight, FileCheck2, MessageSquare, MessagesSquare, Plus, Users } from "lucide-react";
import Link from "next/link";
import useSWR from "swr";
import { BarChart, Button, Notice, PageHeader, Panel, Spinner, UsageMeter } from "@/components/ui";
import { errorMessage, fetcher } from "@/lib/api";
import type { UsageSummary } from "@/lib/types";
import { useSession } from "@/lib/use-session";

export default function OverviewPage() {
  const { me, organization } = useSession();
  const { data, error, isLoading } = useSWR<UsageSummary>(organization ? `/usage?org=${organization.id}` : null, fetcher);

  const firstName = me?.user.name.split(" ")[0];
  const monthTotal = data?.daily.reduce((a, d) => a + d.messages, 0) ?? 0;
  const topModelRequests = Math.max(1, ...(data?.by_model.map((r) => Number(r.requests)) ?? [1]));

  const stats = data
    ? [
        { label: "AI messages this month", value: data.usage.messages.used, icon: MessageSquare, href: "/dashboard/chat" },
        { label: "Conversations", value: data.totals.conversations, icon: MessagesSquare, href: "/dashboard/chat" },
        { label: "Documents ready to search", value: data.totals.documents_ready, icon: FileCheck2, href: "/dashboard/documents" },
        { label: "Team members", value: data.usage.members.used, icon: Users, href: "/dashboard/team" },
      ]
    : [];

  return (
    <div className="w-full">
      <PageHeader
        title={`Good to see you, ${firstName}`}
        description={`Here's how ${organization?.name ?? "your workspace"} is using AI this month.`}
        actions={
          <>
            <Link href="/dashboard/documents" className="flex-1 sm:flex-none">
              <Button variant="secondary" className="w-full">
                <Plus className="size-4" aria-hidden /> Add documents
              </Button>
            </Link>
            <Link href="/dashboard/chat" className="flex-1 sm:flex-none">
              <Button className="w-full">
                <MessageSquare className="size-4" aria-hidden /> New chat
              </Button>
            </Link>
          </>
        }
      />

      {error && <Notice tone="danger">{errorMessage(error)}</Notice>}
      {isLoading && <Spinner />}

      {data && (
        <div className="space-y-4 sm:space-y-6">
          {/* KPI cards */}
          <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            {stats.map((s, i) => (
              <Link
                key={s.label}
                href={s.href}
                className="group animate-fade-up rounded-lg border border-line bg-surface p-4 transition-all duration-300 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lg hover:shadow-ink/5 sm:p-5"
                style={{ animationDelay: `${i * 60}ms` }}
              >
                <div className="flex items-start justify-between gap-2">
                  <span className="flex size-9 items-center justify-center rounded-md bg-action-soft text-action">
                    <s.icon className="size-[18px]" aria-hidden />
                  </span>
                  <ArrowUpRight className="size-4 text-faint opacity-0 transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:opacity-100" aria-hidden />
                </div>
                <p className="mt-4 text-2xl font-semibold tabular-nums sm:text-[28px]">{Number(s.value).toLocaleString()}</p>
                <p className="mt-0.5 text-[13px] leading-snug text-muted sm:text-sm">{s.label}</p>
              </Link>
            ))}
          </div>

          <div className="grid gap-4 sm:gap-6 xl:grid-cols-3">
            <Panel className="animate-fade-up p-4 [animation-delay:200ms] sm:p-6 xl:col-span-2">
              <div className="mb-5 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                  <h2 className="font-semibold">Messages, last 30 days</h2>
                  <p className="text-sm text-muted">Every AI reply across your organization</p>
                </div>
                <span className="rounded-md bg-paper px-2.5 py-1 text-sm tabular-nums text-muted ring-1 ring-line">
                  <span className="font-semibold text-ink">{monthTotal.toLocaleString()}</span> total
                </span>
              </div>
              <BarChart data={data.daily} valueKey="messages" label="Messages per day" className="h-44 sm:h-56" />
            </Panel>

            <Panel className="animate-fade-up p-4 [animation-delay:260ms] sm:p-6">
              <h2 className="font-semibold">Models this month</h2>
              {data.by_model.length === 0 ? (
                <p className="mt-3 text-sm text-muted">No AI requests yet. Start a chat to see usage by model.</p>
              ) : (
                <ul className="mt-4 space-y-4">
                  {data.by_model.slice(0, 6).map((row) => (
                    <li key={`${row.provider}:${row.model}`} className="text-sm">
                      <div className="flex items-baseline justify-between gap-3">
                        <span className="min-w-0">
                          <span className="block truncate font-medium">{row.model}</span>
                          <span className="text-xs text-muted">{row.provider}</span>
                        </span>
                        <span className="tabular-nums text-muted">{Number(row.requests).toLocaleString()}</span>
                      </div>
                      <div className="mt-1.5 h-1 overflow-hidden rounded-full bg-paper" aria-hidden>
                        <div
                          className="h-full origin-left animate-[bar-grow-x_0.8s_var(--ease-out-soft)_both] rounded-full bg-action/70"
                          style={{ width: `${(Number(row.requests) / topModelRequests) * 100}%` }}
                        />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>
          </div>

          <Panel className="animate-fade-up p-4 [animation-delay:320ms] sm:p-6">
            <div className="mb-5 flex flex-wrap items-baseline justify-between gap-2">
              <h2 className="font-semibold">Plan usage</h2>
              <p className="text-sm text-muted">
                <span className="font-medium text-ink">{data.usage.plan.name}</span> plan, resets on{" "}
                {new Date(data.usage.period_end).toLocaleDateString(undefined, { month: "long", day: "numeric" })}.{" "}
                <Link href="/dashboard/billing" className="text-action underline-offset-2 hover:underline">
                  Compare plans
                </Link>
              </p>
            </div>
            <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
              <UsageMeter label="AI messages" meter={data.usage.messages} />
              <UsageMeter label="Documents" meter={data.usage.documents} />
              <UsageMeter label="Storage" meter={data.usage.storage_mb} unit=" MB" />
              <UsageMeter label="Seats" meter={data.usage.members} />
            </div>
          </Panel>
        </div>
      )}
    </div>
  );
}
