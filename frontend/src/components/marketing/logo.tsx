import Link from "next/link";

// The mark is a page with a highlighted line: answers come from documents.
export function Logo({ href = "/" }: { href?: string }) {
  return (
    <Link href={href} className="inline-flex items-center gap-2 font-semibold tracking-[-0.01em]">
      <svg width="22" height="22" viewBox="0 0 22 22" aria-hidden>
        <rect x="3.5" y="1.5" width="15" height="19" rx="2" fill="none" stroke="currentColor" strokeWidth="1.5" />
        <rect x="6" y="9" width="10" height="3.2" rx="0.6" fill="var(--color-highlight)" />
        <path d="M6.5 6h7M6.5 15.5h9" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
      </svg>
      <span>{process.env.NEXT_PUBLIC_APP_NAME ?? "Margin"}</span>
    </Link>
  );
}
