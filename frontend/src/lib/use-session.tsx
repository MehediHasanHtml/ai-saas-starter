"use client";

import { createContext, useCallback, useContext, useEffect, useMemo } from "react";
import useSWR, { useSWRConfig } from "swr";
import { fetcher } from "@/lib/api";
import type { Me, Organization } from "@/lib/types";

interface SessionValue {
  me: Me | undefined;
  loading: boolean;
  organization: Organization | undefined;
  canManage: boolean;
  switchOrganization: (id: number) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => void;
}

const SessionContext = createContext<SessionValue | null>(null);

function readOrgCookie(): number | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(/(?:^|; )org_id=(\d+)/);
  return match ? Number(match[1]) : null;
}

export function SessionProvider({ children }: { children: React.ReactNode }) {
  const { data: me, isLoading, mutate } = useSWR<Me>("/auth/me", fetcher);
  const { mutate: globalMutate } = useSWRConfig();

  const organization = useMemo(() => {
    if (!me) return undefined;
    const selected = readOrgCookie() ?? me.current_organization_id;
    return me.organizations.find((o) => o.id === selected) ?? me.organizations[0];
  }, [me]);

  const switchOrganization = useCallback(
    async (id: number) => {
      await fetch("/api/auth/organization", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
      });
      await mutate();
      // Everything tenant-scoped must be refetched for the new organization.
      await globalMutate((key) => typeof key === "string" && key !== "/auth/me", undefined, { revalidate: true });
    },
    [mutate, globalMutate],
  );

  // Keep the org cookie in sync if it points at an org we no longer belong to.
  useEffect(() => {
    if (me && organization && readOrgCookie() !== organization.id) {
      void fetch("/api/auth/organization", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: organization.id }),
      });
    }
  }, [me, organization]);

  const logout = useCallback(async () => {
    await fetch("/api/auth/logout", { method: "POST" });
    // Full reload on purpose: drops all cached data from the previous session/tenant.
    // eslint-disable-next-line @next/next/no-location-assign-relative-destination
    window.location.assign("/login");
  }, []);

  const value: SessionValue = {
    me,
    loading: isLoading,
    organization,
    canManage: organization?.role === "owner" || organization?.role === "admin",
    switchOrganization,
    logout,
    refresh: () => void mutate(),
  };

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession() {
  const ctx = useContext(SessionContext);
  if (!ctx) throw new Error("useSession must be used inside <SessionProvider>");
  return ctx;
}
