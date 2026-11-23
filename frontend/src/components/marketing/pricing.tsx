"use client";

import Link from "next/link";
import useSWR from "swr";
import { PlanList } from "@/components/marketing/plan-list";
import { Button, Spinner } from "@/components/ui";
import { fetcher } from "@/lib/api";
import type { Plan } from "@/lib/types";

export function Pricing() {
  const { data, error } = useSWR<{ data: Plan[] }>("/plans", fetcher, { revalidateOnFocus: false });

  if (error) {
    return (
      <p className="text-muted">
        Pricing is unavailable while the API is offline. Start it with <code className="font-mono">docker compose up</code>.
      </p>
    );
  }
  if (!data) return <Spinner label="Loading plans" />;

  return (
    <PlanList
      plans={data.data}
      action={(plan) => (
        <Link href="/register" className="block">
          <Button className="w-full" variant={plan.highlighted ? "primary" : "secondary"}>
            {plan.price_monthly === 0 ? "Start for free" : `Start with ${plan.name}`}
          </Button>
        </Link>
      )}
    />
  );
}
