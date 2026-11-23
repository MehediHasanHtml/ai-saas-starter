"use client";

import { useState } from "react";
import useSWR from "swr";
import { Pager } from "@/components/dashboard/pager";
import { Badge, Button, Input, Notice, PageHeader, Panel, Spinner } from "@/components/ui";
import { api, errorMessage, fetcher } from "@/lib/api";
import { useSession } from "@/lib/use-session";

interface AdminUser {
  id: number;
  name: string;
  email: string;
  is_super_admin: boolean;
  organizations_count: number;
  last_seen_at: string | null;
  created_at: string;
}

export default function AdminUsers() {
  const { me } = useSession();
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);
  const { data, error, mutate } = useSWR<{ data: AdminUser[]; current_page: number; last_page: number; total: number }>(
    `/admin/users?page=${page}&q=${encodeURIComponent(q)}`,
    fetcher,
    { keepPreviousData: true },
  );
  const [actionError, setActionError] = useState<string | null>(null);

  const toggleAdmin = async (user: AdminUser) => {
    setActionError(null);
    try {
      await api(`/admin/users/${user.id}`, { method: "PATCH", json: { is_super_admin: !user.is_super_admin } });
      void mutate();
    } catch (e) {
      setActionError(errorMessage(e));
    }
  };

  return (
    <div className="w-full">
      <PageHeader
        title="Users"
        description="Everyone with an account, across all organizations."
        actions={
          <Input
            value={q}
            onChange={(e) => {
              setQ(e.target.value);
              setPage(1);
            }}
            placeholder="Search by name or email"
            aria-label="Search users"
            className="w-64"
          />
        }
      />
      {(error || actionError) && (
        <div className="mb-4">
          <Notice tone="danger">{actionError ?? errorMessage(error)}</Notice>
        </div>
      )}
      {!data && !error && <Spinner />}
      {data && (
        <Panel className="overflow-x-auto">
          <table className="w-full min-w-[680px] text-sm">
            <thead className="border-b border-line text-left text-muted">
              <tr>
                <th className="px-4 py-3 font-medium">User</th>
                <th className="px-4 py-3 text-right font-medium">Organizations</th>
                <th className="px-4 py-3 font-medium">Last sign-in</th>
                <th className="px-4 py-3 font-medium">Access</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {data.data.map((user) => (
                <tr key={user.id}>
                  <td className="px-4 py-3">
                    <p className="font-medium">{user.name}</p>
                    <p className="text-[13px] text-muted">{user.email}</p>
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums">{user.organizations_count}</td>
                  <td className="px-4 py-3 text-muted">{user.last_seen_at ? new Date(user.last_seen_at).toLocaleDateString() : "Never"}</td>
                  <td className="px-4 py-3">{user.is_super_admin ? <Badge tone="action">Platform admin</Badge> : <Badge>Customer</Badge>}</td>
                  <td className="px-4 py-3 text-right">
                    {user.id !== me?.user.id && (
                      <Button variant="secondary" size="sm" onClick={() => toggleAdmin(user)}>
                        {user.is_super_admin ? "Remove admin access" : "Make admin"}
                      </Button>
                    )}
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
