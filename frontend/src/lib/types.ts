// Shapes returned by the Laravel API (see backend/app/Http/Resources).

export type Role = "owner" | "admin" | "member";

export interface User {
  id: number;
  name: string;
  email: string;
  is_super_admin: boolean;
  current_organization_id: number | null;
  role?: Role;
  created_at: string;
}

export interface Organization {
  id: number;
  name: string;
  slug: string;
  personal: boolean;
  role?: Role;
  plan: { key: PlanKey; name: string };
  settings: {
    default_provider: string | null;
    default_model: string | null;
    system_prompt: string | null;
  };
  suspended: boolean;
  created_at: string;
}

export interface Me {
  user: User;
  organizations: Organization[];
  current_organization_id: number | null;
}

export type PlanKey = "free" | "pro" | "business" | string;

export interface Plan {
  key: PlanKey;
  name: string;
  description: string;
  price_monthly: number;
  limits: {
    messages_per_month: number | null;
    documents: number | null;
    max_file_mb: number | null;
    storage_mb: number | null;
    members: number | null;
    requests_per_minute: number | null;
  };
  providers: string[];
  features: string[];
  highlighted: boolean;
  purchasable: boolean;
}

export interface Meter {
  used: number;
  limit: number | null;
  percent: number;
}

export interface Usage {
  plan: Plan;
  period_start: string;
  period_end: string;
  messages: Meter;
  documents: Meter;
  storage_mb: Meter;
  members: Meter;
}

export interface UsageSummary {
  usage: Usage;
  daily: { date: string; messages: number; tokens: number }[];
  by_model: { provider: string; model: string; requests: number; input_tokens: number; output_tokens: number }[];
  totals: { conversations: number; documents_ready: number };
}

export type DocumentStatus = "pending" | "processing" | "ready" | "failed";

export interface DocumentItem {
  id: number;
  title: string;
  filename: string;
  mime_type: string | null;
  size_bytes: number;
  status: DocumentStatus;
  error: string | null;
  chunk_count: number;
  token_count: number;
  uploaded_by?: { id: number; name: string } | null;
  processed_at: string | null;
  created_at: string;
}

export interface Citation {
  index: number;
  document_id: number;
  document_title: string;
  page: number | null;
  score: number;
  snippet: string;
}

export interface ChatMessage {
  id: number | string;
  role: "user" | "assistant";
  content: string;
  citations: Citation[];
  provider?: string | null;
  model?: string | null;
  input_tokens?: number;
  output_tokens?: number;
  latency_ms?: number | null;
  created_at?: string;
}

export interface Conversation {
  id: number;
  title: string;
  provider: string;
  model: string;
  system_prompt: string | null;
  use_knowledge_base: boolean;
  document_ids: number[] | null;
  last_message_at: string | null;
  created_at: string;
  messages?: ChatMessage[];
}

export interface ProviderCatalogue {
  default_provider: string;
  default_model: string | null;
  embedding: { provider: string; model: string; dimensions: number };
  data: { provider: string; label: string; configured: boolean; allowed: boolean; models: string[] }[];
}

export interface SearchResult {
  chunk_id: number;
  document_id: number;
  document_title: string;
  content: string;
  metadata: { page?: number };
  score: number;
}

export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
  // Laravel's plain paginator (admin endpoints)
  current_page?: number;
  last_page?: number;
  total?: number;
}
