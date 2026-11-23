"use client";

import { Copy } from "lucide-react";
import { useState } from "react";
import useSWR from "swr";
import { Badge, Button, Field, Input, Notice, PageHeader, Panel, Select, Spinner } from "@/components/ui";
import { api, errorMessage, fetcher } from "@/lib/api";
import type { Role, User } from "@/lib/types";
import { useSession } from "@/lib/use-session";

interface Invitation {
  id: number;
  email: string;
  role: Role;
  expires_at: string;
}

export default function TeamPage() {
  const { me, organization, canManage } = useSession();
  const org = organization ? `org=${organization.id}` : null;
  const members = useSWR<{ data: User[] }>(org && `/members?${org}`, fetcher);
  const invites = useSWR<{ data: Invitation[] }>(org && `/invitations?${org}`, fetcher);

  const [email, setEmail] = useState("");
  const [role, setRole] = useState<Role>("member");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [link, setLink] = useState<string | null>(null);

  const invite = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setError(null);
    setLink(null);
    try {
      const res = await api<{ accept_url: string }>("/invitations", { method: "POST", json: { email, role } });
      setLink(res.accept_url);
      setEmail("");
      void invites.mutate();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setSending(false);
    }
  };

  const changeRole = async (user: User, next: Role) => {
    try {
      await api(`/members/${user.id}`, { method: "PATCH", json: { role: next } });
      void members.mutate();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  const remove = async (user: User) => {
    if (!confirm(`Remove ${user.name} from ${organization?.name}?`)) return;
    try {
      await api(`/members/${user.id}`, { method: "DELETE" });
      void members.mutate();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  return (
    <div className="w-full">
      <PageHeader
        title="Team"
        description={`Everyone in ${organization?.name ?? "this organization"} shares its knowledge base and plan. Chats stay private to each person.`}
      />

      {error && (
        <div className="mb-6">
          <Notice tone="danger">{error}</Notice>
        </div>
      )}

      {canManage && (
        <Panel className="mb-8 p-5">
          <h2 className="font-semibold">Invite someone</h2>
          <form onSubmit={invite} className="mt-4 flex flex-wrap items-end gap-3">
            <div className="min-w-60 flex-1">
              <Field label="Email address">
                <Input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="colleague@company.com" />
              </Field>
            </div>
            <Field label="Role">
              <Select value={role} onChange={(e) => setRole(e.target.value as Role)}>
                <option value="member">Member</option>
                <option value="admin">Admin</option>
              </Select>
            </Field>
            <Button type="submit" loading={sending}>
              Send invitation
            </Button>
          </form>
          {link && (
            <div className="mt-4 flex items-center gap-2 rounded-md bg-paper px-3 py-2 text-sm ring-1 ring-line">
              <span className="text-muted">Share this link:</span>
              <code className="min-w-0 flex-1 truncate font-mono text-[13px]">{link}</code>
              <button onClick={() => navigator.clipboard.writeText(link)} className="rounded p-1 text-muted hover:text-ink" aria-label="Copy link">
                <Copy className="size-4" />
              </button>
            </div>
          )}
          <p className="mt-3 text-[13px] text-muted">
            Admins can invite people, manage billing and change settings. Members can chat and add documents.
          </p>
        </Panel>
      )}

      <section>
        <h2 className="mb-3 font-semibold">Members</h2>
        {members.isLoading && <Spinner />}
        <Panel className="divide-y divide-line">
          {members.data?.data.map((user) => (
            <div key={user.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
              <span className="flex size-8 items-center justify-center rounded-full bg-paper text-sm font-semibold ring-1 ring-line">
                {user.name.slice(0, 1).toUpperCase()}
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium">
                  {user.name} {user.id === me?.user.id && <span className="font-normal text-muted">(you)</span>}
                </p>
                <p className="truncate text-[13px] text-muted">{user.email}</p>
              </div>
              {user.role === "owner" || !canManage || user.id === me?.user.id ? (
                <Badge>{user.role}</Badge>
              ) : (
                <>
                  <Select
                    value={user.role}
                    onChange={(e) => changeRole(user, e.target.value as Role)}
                    className="h-8 text-[13px]"
                    aria-label={`Role for ${user.name}`}
                  >
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                  </Select>
                  <Button variant="ghost" size="sm" onClick={() => remove(user)}>
                    Remove
                  </Button>
                </>
              )}
            </div>
          ))}
        </Panel>
      </section>

      {invites.data && invites.data.data.length > 0 && (
        <section className="mt-8">
          <h2 className="mb-3 font-semibold">Pending invitations</h2>
          <Panel className="divide-y divide-line">
            {invites.data.data.map((inv) => (
              <div key={inv.id} className="flex items-center gap-3 px-4 py-3 text-sm">
                <span className="min-w-0 flex-1 truncate">{inv.email}</span>
                <Badge>{inv.role}</Badge>
                <span className="text-muted">expires {new Date(inv.expires_at).toLocaleDateString()}</span>
                {canManage && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={async () => {
                      await api(`/invitations/${inv.id}`, { method: "DELETE" });
                      void invites.mutate();
                    }}
                  >
                    Revoke
                  </Button>
                )}
              </div>
            ))}
          </Panel>
        </section>
      )}
    </div>
  );
}
