"use client";

import { useState } from "react";
import useSWR from "swr";
import { Button, Field, Input, Notice, PageHeader, Panel, Select, Textarea } from "@/components/ui";
import { api, errorMessage, fetcher } from "@/lib/api";
import type { Organization, ProviderCatalogue } from "@/lib/types";
import { useSession } from "@/lib/use-session";

export default function SettingsPage() {
  const { organization, switchOrganization } = useSession();

  return (
    <div className="max-w-3xl">
      <PageHeader title="Settings" description="Defaults for everyone in this organization." />
      {/* Keyed so the form resets when you switch organization. */}
      {organization && <OrganizationSettings key={organization.id} organization={organization} />}
      <NewOrganization onCreated={switchOrganization} />
    </div>
  );
}

function OrganizationSettings({ organization }: { organization: Organization }) {
  const { canManage, refresh } = useSession();
  const { data: catalogue } = useSWR<ProviderCatalogue>(`/models?org=${organization.id}`, fetcher);

  const { default_provider: p, default_model: m, system_prompt } = organization.settings;
  const [name, setName] = useState(organization.name);
  const [model, setModel] = useState(p && m ? `${p}::${m}` : "");
  const [prompt, setPrompt] = useState(system_prompt ?? "");
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState<{ tone: "success" | "danger"; text: string } | null>(null);

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setNotice(null);
    const [default_provider, default_model] = model ? model.split("::") : [null, null];
    try {
      await api("/organization", {
        method: "PATCH",
        json: { name, settings: { default_provider, default_model, system_prompt: prompt || null } },
      });
      refresh();
      setNotice({ tone: "success", text: "Settings saved." });
    } catch (err) {
      setNotice({ tone: "danger", text: errorMessage(err) });
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={save}>
      <Panel className="space-y-5 p-6">
        {notice && <Notice tone={notice.tone}>{notice.text}</Notice>}
        <Field label="Organization name">
          <Input value={name} onChange={(e) => setName(e.target.value)} disabled={!canManage} required maxLength={255} />
        </Field>
        <Field label="Default model for new chats" hint="Models your plan doesn't include, or whose provider has no API key, can't be selected.">
          <Select value={model} onChange={(e) => setModel(e.target.value)} disabled={!canManage} className="w-full">
            <option value="">Platform default</option>
            {catalogue?.data.map((p) => (
              <optgroup key={p.provider} label={p.label}>
                {p.models.map((m) => (
                  <option key={m} value={`${p.provider}::${m}`} disabled={!p.allowed || !p.configured}>
                    {m}
                  </option>
                ))}
              </optgroup>
            ))}
          </Select>
        </Field>
        <Field
          label="Instructions for the assistant"
          hint="Added to every chat, before the document sources. For example: your company name, tone, or what to avoid."
        >
          <Textarea
            rows={5}
            value={prompt}
            onChange={(e) => setPrompt(e.target.value)}
            disabled={!canManage}
            maxLength={4000}
            placeholder="You answer questions for the Acme support team. Keep answers short and link to the policy section."
          />
        </Field>
        {catalogue && (
          <p className="text-[13px] text-muted">
            Documents are embedded with {catalogue.embedding.model} ({catalogue.embedding.provider}, {catalogue.embedding.dimensions} dimensions).
          </p>
        )}
        {canManage ? (
          <Button type="submit" loading={saving}>
            Save settings
          </Button>
        ) : (
          <p className="text-sm text-muted">Only owners and admins can change these settings.</p>
        )}
      </Panel>
    </form>
  );
}

function NewOrganization({ onCreated }: { onCreated: (id: number) => Promise<void> }) {
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <Panel id="new-organization" className="mt-8 scroll-mt-8 p-6">
      <h2 className="font-semibold">Create another organization</h2>
      <p className="mt-1 text-sm text-muted">Each organization has its own documents, members and plan. You&apos;ll be its owner.</p>
      <form
        className="mt-4 flex gap-3"
        onSubmit={async (e) => {
          e.preventDefault();
          setBusy(true);
          setError(null);
          try {
            const res = await api<{ data: Organization }>("/organizations", { method: "POST", json: { name } });
            setName("");
            await onCreated(res.data.id);
          } catch (err) {
            setError(errorMessage(err));
          } finally {
            setBusy(false);
          }
        }}
      >
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Organization name" required aria-label="Organization name" />
        <Button type="submit" variant="secondary" loading={busy} className="shrink-0">
          Create organization
        </Button>
      </form>
      {error && <p className="mt-2 text-sm text-danger">{error}</p>}
    </Panel>
  );
}
