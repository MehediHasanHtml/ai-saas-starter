"use client";

import { useState } from "react";
import useSWR from "swr";
import { Badge, Button, Input, Notice, PageHeader, Panel, Select, Spinner } from "@/components/ui";
import { Pager } from "@/components/dashboard/pager";
import { api, errorMessage, fetcher } from "@/lib/api";

interface AdminOrg {
  id: number;
  name: string;
  slug: string;
  owner: { id: number; name: string; email: string } | null;
  plan: string;
  plan_override: string | null;
  subscription_status: string | null;
  suspended: boolean;
  members_count: number;
  documents_count: number;
  conversations_count: number;
  created_at: string;
}

interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export default function AdminOrganizations() {
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);
  const { data, error, isLoading, mutate } = useSWR<Page<AdminOrg>>(`/admin/organizations?page=${page}&q=${encodeURIComponent(q)}`, fetcher, {
    keepPreviousData: true,
  });
  const [actionError, setActionError] = useState<string | null>(null);

  const update = async (org: AdminOrg, patch: Record<string, unknown>) => {
    setActionError(null);
    try {
      await api(`/admin/organizations/${org.id}`, { method: "PATCH", json: patch });
      void mutate();
    } catch (e) {
      setActionError(errorMessage(e));
    }
  };

  return (
    <div className="w-full">
      <PageHeader
        title="Organizations"
        description="Assign a plan without Stripe for partners and demos, or suspend a tenant to block all access."
        actions={
          <Input
            value={q}
            onChange={(e) => {
              setQ(e.target.value);
              setPage(1);
            }}
            placeholder="Search by name"
            aria-label="Search organizations"
            className="w-64"
          />
        }
      />

      {(error || actionError) && (
        <div className="mb-4">
          <Notice tone="danger">{actionError ?? errorMessage(error)}</Notice>
        </div>
      )}
      {isLoading && !data && <Spinner />}

      {data && (
        <Panel className="overflow-x-auto">
          <table className="w-full min-w-[820px] text-sm">
            <thead className="border-b border-line text-left text-muted">
              <tr>
                <th className="px-4 py-3 font-medium">Organization</th>
                <th className="px-4 py-3 font-medium">Owner</th>
                <th className="px-4 py-3 text-right font-medium">Members</th>
                <th className="px-4 py-3 text-right font-medium">Docs</th>
                <th className="px-4 py-3 font-medium">Plan</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {data.data.map((org) => (
                <tr key={org.id}>
                  <td className="px-4 py-3">
                    <p className="font-medium">{org.name}</p>
                    <p className="text-[13px] text-muted">Created {new Date(org.created_at).toLocaleDateString()}</p>
                  </td>
                  <td className="px-4 py-3 text-muted">{org.owner?.email ?? "None"}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{org.members_count}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{org.documents_count}</td>
                  <td className="px-4 py-3">
                    <Select
                      value={org.plan_override ?? ""}
                      onChange={(e) => update(org, { plan_override: e.target.value || null })}
                      className="h-8 text-[13px]"
                      aria-label={`Plan for ${org.name}`}
                    >
                      <option value="">{org.subscription_status ? `Stripe (${org.plan})` : "Free (no subscription)"}</option>
                      <option value="free">Comped: Free</option>
                      <option value="pro">Comped: Pro</option>
                      <option value="business">Comped: Business</option>
                    </Select>
                  </td>
                  <td className="px-4 py-3">{org.suspended ? <Badge tone="danger">Suspended</Badge> : <Badge tone="success">Active</Badge>}</td>
                  <td className="px-4 py-3 text-right">
                    <Button variant={org.suspended ? "secondary" : "danger"} size="sm" onClick={() => update(org, { suspended: !org.suspended })}>
                      {org.suspended ? "Reactivate" : "Suspend"}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <Pager page={data.current_page} last={data.last_page} total={data.total} onPage={setPage} />
        </Panel>
      )}
    </div>
  );
}
