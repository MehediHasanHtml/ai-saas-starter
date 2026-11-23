"use client";

import { useState } from "react";
import useSWR from "swr";
import { PlanList } from "@/components/marketing/plan-list";
import { Button, Notice, PageHeader, Panel, Spinner, UsageMeter } from "@/components/ui";
import { api, errorMessage, fetcher } from "@/lib/api";
import type { Plan, Usage } from "@/lib/types";
import { useSession } from "@/lib/use-session";

interface Billing {
  plan: Plan;
  plans: Plan[];
  usage: Usage;
  stripe_enabled: boolean;
  override: string | null;
  subscription: { status: string; on_grace_period: boolean; ends_at: string | null; card: string | null } | null;
}

export default function BillingPage() {
  const { organization, canManage, refresh } = useSession();
  const { data, error, isLoading, mutate } = useSWR<Billing>(organization ? `/billing?org=${organization.id}` : null, fetcher);
  const [pending, setPending] = useState<string | null>(null);
  // Rendered client-side only (the dashboard shell waits for the session),
  // so reading the Stripe redirect status from the URL here is safe.
  const [message, setMessage] = useState<{ tone: "success" | "danger" | "neutral"; text: string } | null>(() => {
    const status = typeof window === "undefined" ? null : new URLSearchParams(window.location.search).get("checkout");
    if (status === "success")
      return { tone: "success", text: "Payment received. Your new plan is active as soon as Stripe confirms it, usually within a few seconds." };
    if (status === "cancelled") return { tone: "neutral", text: "Checkout was cancelled. You have not been charged." };
    return null;
  });

  const choose = async (plan: Plan) => {
    setPending(plan.key);
    setMessage(null);
    try {
      const res = await api<{ url?: string; message?: string }>("/billing/checkout", { method: "POST", json: { plan: plan.key } });
      if (res.url) {
        window.location.assign(res.url);
        return;
      }
      setMessage({ tone: "success", text: res.message ?? "Plan updated." });
      await mutate();
      refresh();
    } catch (e) {
      setMessage({ tone: "danger", text: errorMessage(e) });
    } finally {
      setPending(null);
    }
  };

  const portal = async () => {
    setPending("portal");
    try {
      const res = await api<{ url: string }>("/billing/portal", { method: "POST" });
      window.location.assign(res.url);
    } catch (e) {
      setMessage({ tone: "danger", text: errorMessage(e) });
      setPending(null);
    }
  };

  return (
    <div className="w-full">
      <PageHeader
        title="Plan & billing"
        description="Your plan applies to the whole organization: every member shares its message allowance, storage and seats."
        actions={
          data?.stripe_enabled && canManage && data.subscription ? (
            <Button variant="secondary" onClick={portal} loading={pending === "portal"}>
              Manage payment details
            </Button>
          ) : null
        }
      />

      {message && (
        <div className="mb-6">
          <Notice tone={message.tone}>{message.text}</Notice>
        </div>
      )}
      {error && <Notice tone="danger">{errorMessage(error)}</Notice>}
      {isLoading && <Spinner />}

      {data && (
        <>
          <Panel className="mb-10 grid gap-6 p-6 md:grid-cols-[1fr_2fr]">
            <div>
              <p className="text-sm text-muted">Current plan</p>
              <p className="mt-1 text-2xl font-semibold">{data.plan.name}</p>
              {data.override && <p className="mt-1 text-sm text-muted">Assigned by a platform admin.</p>}
              {data.subscription && (
                <p className="mt-1 text-sm text-muted">
                  {data.subscription.on_grace_period && data.subscription.ends_at
                    ? `Ends on ${new Date(data.subscription.ends_at).toLocaleDateString()}`
                    : `Subscription ${data.subscription.status}`}
                  {data.subscription.card && `, paying with ${data.subscription.card}`}
                </p>
              )}
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
              <UsageMeter label="AI messages this month" meter={data.usage.messages} />
              <UsageMeter label="Documents" meter={data.usage.documents} />
              <UsageMeter label="Storage" meter={data.usage.storage_mb} unit=" MB" />
              <UsageMeter label="Seats" meter={data.usage.members} />
            </div>
          </Panel>

          {!data.stripe_enabled && (
            <div className="mb-6">
              <Notice>
                Stripe isn&apos;t connected yet, so plans can&apos;t be purchased. Add <code className="font-mono">STRIPE_SECRET</code> and the plan
                price ids to the API&apos;s environment, or assign a plan from the platform admin.
              </Notice>
            </div>
          )}

          <PlanList
            plans={data.plans}
            currentKey={data.plan.key}
            action={(plan) => {
              const current = plan.key === data.plan.key;
              const downgrade = plan.price_monthly < data.plan.price_monthly;
              return (
                <Button
                  className="w-full"
                  variant={current ? "secondary" : plan.highlighted ? "primary" : "secondary"}
                  disabled={current || !canManage || !data.stripe_enabled || !plan.purchasable || !!data.override}
                  loading={pending === plan.key}
                  onClick={() => choose(plan)}
                >
                  {current ? "Your current plan" : downgrade ? `Switch to ${plan.name}` : `Upgrade to ${plan.name}`}
                </Button>
              );
            }}
          />
          {!canManage && <p className="mt-4 text-sm text-muted">Only owners and admins can change the plan.</p>}
        </>
      )}
    </div>
  );
}
