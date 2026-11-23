"use client";

import clsx from "clsx";
import { Check } from "lucide-react";
import type { Plan } from "@/lib/types";

/**
 * Pricing table shared by the landing page and the billing page. Plans are
 * defined once in backend/config/plans.php and read from GET /api/plans.
 */
export function PlanList({ plans, currentKey, action }: { plans: Plan[]; currentKey?: string; action: (plan: Plan) => React.ReactNode }) {
  return (
    <div className="grid gap-px overflow-hidden rounded-lg border border-line bg-line md:grid-cols-3">
      {plans.map((plan) => (
        <div
          key={plan.key}
          className={clsx(
            "flex flex-col bg-surface p-6",
            plan.highlighted && "md:bg-[linear-gradient(var(--color-highlight)_0_3px,var(--color-surface)_3px)]",
          )}
        >
          <div className="flex items-baseline justify-between gap-2">
            <h3 className="text-lg font-semibold">{plan.name}</h3>
            {plan.key === currentKey && <span className="text-xs font-medium text-action">Current plan</span>}
          </div>
          <p className="mt-1 min-h-10 text-sm text-muted">{plan.description}</p>
          <p className="mt-5">
            <span className="text-4xl font-semibold tracking-[-0.02em] tabular-nums">${plan.price_monthly}</span>
            <span className="text-sm text-muted"> per month</span>
          </p>
          <ul className="mt-6 flex-1 space-y-2.5 text-sm">
            {plan.features.map((f) => (
              <li key={f} className="flex gap-2.5">
                <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
                <span>{f}</span>
              </li>
            ))}
          </ul>
          <div className="mt-7">{action(plan)}</div>
        </div>
      ))}
    </div>
  );
}
