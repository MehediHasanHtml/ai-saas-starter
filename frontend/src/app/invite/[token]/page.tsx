import { Suspense } from "react";
import { AcceptInvitation } from "@/components/marketing/accept-invitation";

export const metadata = { title: "Join a team" };

export default function AcceptInvitationPage() {
  return (
    <Suspense>
      <AcceptInvitation />
    </Suspense>
  );
}
