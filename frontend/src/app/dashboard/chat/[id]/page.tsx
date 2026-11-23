"use client";

import { useParams } from "next/navigation";
import { ChatWorkspace } from "@/components/chat/chat-workspace";

export default function ConversationPage() {
  const { id } = useParams<{ id: string }>();
  return <ChatWorkspace initialId={Number(id)} />;
}
