import { ArrowRight, Building2, Cpu, FileSearch, MessageSquareText, Quote } from "lucide-react";
import Link from "next/link";
import { Logo } from "@/components/marketing/logo";
import { Pricing } from "@/components/marketing/pricing";
import { Reveal } from "@/components/marketing/reveal";
import { SiteHeader } from "@/components/marketing/site-header";

const STEPS = [
  {
    icon: FileSearch,
    title: "Add your documents",
    body: "Drop in PDFs, Word files, Markdown or exports from your wiki. Each file is split into passages and indexed.",
  },
  {
    icon: MessageSquareText,
    title: "Ask in plain language",
    body: "Your question is matched against every passage by meaning, not keywords, so “notice period” finds “termination”.",
  },
  {
    icon: Quote,
    title: "Check the source",
    body: "The answer cites the passages it used. Open any citation to read the original text next to the answer.",
  },
];

const PROVIDERS = ["OpenAI", "Anthropic", "Google Gemini", "Ollama (self-hosted)"];

export default function Home() {
  return (
    <div className="overflow-x-clip bg-surface">
      <SiteHeader />

      {/* Hero: the product's defining moment, an answer with its source in the margin. */}
      <section className="relative">
        {/* Soft background wash */}
        <div
          aria-hidden
          className="pointer-events-none absolute inset-x-0 top-0 -z-0 h-[560px] bg-[radial-gradient(60%_60%_at_80%_10%,var(--color-action-soft),transparent_70%),radial-gradient(40%_40%_at_10%_30%,var(--color-highlight-soft),transparent_70%)] opacity-70"
        />
        <div className="relative mx-auto grid max-w-6xl gap-12 px-5 pb-16 pt-28 sm:px-8 sm:pb-20 lg:grid-cols-[1.1fr_1fr] lg:items-center lg:pt-36">
          <div>
            <p className="inline-flex animate-fade-up items-center gap-2 rounded-full bg-surface/80 px-3 py-1 text-xs font-medium text-muted ring-1 ring-line backdrop-blur">
              <span className="size-1.5 rounded-full bg-success" aria-hidden /> Answers with citations, from your own files
            </p>
            <h1 className="mt-5 animate-fade-up text-[clamp(2.1rem,6vw,3.25rem)] font-semibold leading-[1.08] tracking-[-0.025em] [animation-delay:80ms]">
              Ask your company&apos;s documents.
              <br className="hidden sm:block" />{" "}
              <span className="mark">Get the answer and the page it came from.</span>
            </h1>
            <p className="mt-6 max-w-lg animate-fade-up text-base leading-relaxed text-muted [animation-delay:160ms] sm:text-lg">
              Margin reads your handbooks, contracts and product docs, answers your team&apos;s questions, and shows its sources in the margin, so
              nobody has to take an AI&apos;s word for it.
            </p>
            <div className="mt-8 flex animate-fade-up flex-col gap-3 [animation-delay:240ms] sm:flex-row sm:flex-wrap">
              <Link
                href="/register"
                className="group inline-flex items-center justify-center gap-2 rounded-md bg-action px-5 py-3 font-medium text-white shadow-lg shadow-action/20 transition-all hover:-translate-y-0.5 hover:bg-action-hover hover:shadow-xl hover:shadow-action/25"
              >
                Create a free workspace
                <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden />
              </Link>
              <a
                href="#how"
                className="inline-flex items-center justify-center rounded-md bg-surface px-5 py-3 font-medium text-ink ring-1 ring-line-strong transition-colors hover:bg-paper"
              >
                See how it works
              </a>
            </div>
            <p className="mt-5 animate-fade-up text-sm text-faint [animation-delay:320ms]">100 messages a month free. No card needed.</p>
          </div>

          <figure
            className="animate-fade-up rounded-xl bg-paper p-4 shadow-2xl shadow-ink/10 ring-1 ring-line [animation-delay:200ms] sm:p-7"
            aria-label="Example answer with a cited source"
          >
            <p className="ml-auto w-fit max-w-[90%] animate-fade-up rounded-lg bg-ink px-4 py-2.5 text-[15px] text-white [animation-delay:500ms]">
              How much notice do we give before ending a vendor contract?
            </p>
            <div className="mt-6 grid gap-5 sm:grid-cols-[1fr_200px]">
              <div className="animate-fade-up text-[15px] leading-relaxed [animation-delay:800ms]">
                <p>
                  Vendor contracts can be ended with <strong>60 days&apos; written notice</strong>
                  <span className="cite animate-pop [animation-delay:1300ms]">1</span>. If the vendor has missed its service levels twice in a quarter,
                  that drops to 15 days
                  <span className="cite animate-pop [animation-delay:1450ms]">2</span>.
                </p>
                <p className="mt-3 text-xs text-faint">claude-sonnet-5-5, 1.8s</p>
              </div>
              <div className="space-y-3 sm:border-l sm:border-line sm:pl-4">
                <div className="animate-fade-up rounded-md bg-highlight-soft px-3 py-2.5 [animation-delay:1100ms]">
                  <p className="flex items-center gap-1.5 text-xs font-medium">
                    <span className="cite m-0">1</span> Procurement policy, p. 4
                  </p>
                  <p className="mt-1.5 font-serif text-[13px] leading-snug text-ink/80">
                    &ldquo;Either party may terminate this agreement upon sixty (60) days&apos; written notice…&rdquo;
                  </p>
                </div>
                <div className="animate-fade-up rounded-md bg-surface px-3 py-2.5 ring-1 ring-line [animation-delay:1250ms]">
                  <p className="flex items-center gap-1.5 text-xs font-medium">
                    <span className="cite m-0">2</span> Master services agreement, p. 11
                  </p>
                  <p className="mt-1.5 font-serif text-[13px] leading-snug text-muted">&ldquo;…two or more SLA breaches within a calendar quarter…&rdquo;</p>
                </div>
              </div>
            </div>
          </figure>
        </div>
      </section>

      <section id="how" className="border-y border-line bg-paper">
        <div className="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-24">
          <Reveal>
            <p className="text-sm font-medium text-action">How it works</p>
            <h2 className="mt-2 max-w-xl text-[clamp(1.6rem,3.5vw,2rem)] font-semibold leading-tight tracking-[-0.02em]">
              From a folder of files to answers your team can check
            </h2>
          </Reveal>
          <ol className="mt-12 grid gap-5 md:grid-cols-3">
            {STEPS.map((step, i) => (
              <Reveal
                as="li"
                key={step.title}
                delay={i * 120}
                className="group rounded-xl bg-surface p-6 ring-1 ring-line transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-ink/5 hover:ring-line-strong"
              >
                <div className="flex items-center justify-between">
                  <span className="flex size-10 items-center justify-center rounded-lg bg-action-soft text-action transition-transform duration-300 group-hover:scale-110">
                    <step.icon className="size-5" aria-hidden />
                  </span>
                  <span className="font-mono text-sm text-faint">0{i + 1}</span>
                </div>
                <h3 className="mt-5 text-lg font-semibold">{step.title}</h3>
                <p className="mt-2 text-muted">{step.body}</p>
              </Reveal>
            ))}
          </ol>
        </div>
      </section>

      <section id="models" className="mx-auto grid max-w-6xl gap-6 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-2">
        <Reveal className="rounded-xl p-6 ring-1 ring-line transition-shadow duration-300 hover:shadow-xl hover:shadow-ink/5 sm:p-8">
          <span className="flex size-10 items-center justify-center rounded-lg bg-action-soft text-action">
            <Cpu className="size-5" aria-hidden />
          </span>
          <h2 className="mt-5 text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">Use the model that suits the question</h2>
          <p className="mt-4 max-w-lg text-muted">
            Switch between providers per chat. Fast, inexpensive models handle quick lookups; larger models take the long, careful questions. Or keep
            everything on your own servers with Ollama.
          </p>
          <ul className="mt-6 flex flex-wrap gap-2">
            {PROVIDERS.map((p) => (
              <li key={p} className="rounded-md bg-paper px-3 py-1.5 text-sm ring-1 ring-line transition-colors hover:bg-action-soft hover:text-action hover:ring-action/30">
                {p}
              </li>
            ))}
          </ul>
        </Reveal>
        <Reveal delay={120} className="rounded-xl p-6 ring-1 ring-line transition-shadow duration-300 hover:shadow-xl hover:shadow-ink/5 sm:p-8">
          <span className="flex size-10 items-center justify-center rounded-lg bg-highlight-soft text-ink">
            <Building2 className="size-5" aria-hidden />
          </span>
          <h2 className="mt-5 text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">Built for teams, kept separate</h2>
          <p className="mt-4 max-w-lg text-muted">
            Each organization has its own knowledge base, members and plan. Invite colleagues as admins or members, and keep each person&apos;s chats
            private. Run several organizations from one account.
          </p>
        </Reveal>
      </section>

      <section id="pricing" className="border-t border-line bg-paper">
        <div className="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-24">
          <Reveal>
            <p className="text-sm font-medium text-action">Pricing</p>
            <h2 className="mt-2 text-[clamp(1.6rem,3.5vw,2rem)] font-semibold tracking-[-0.02em]">Start free, upgrade when your team relies on it</h2>
            <p className="mt-3 max-w-lg text-muted">Plans cover the whole organization.</p>
          </Reveal>
          <Reveal delay={120} className="mt-10">
            <Pricing />
          </Reveal>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-5 py-16 sm:px-8">
        <Reveal className="flex flex-col items-start justify-between gap-6 rounded-2xl bg-ink px-6 py-10 text-white sm:px-10 md:flex-row md:items-center">
          <div>
            <h2 className="text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">Stop searching. Start asking.</h2>
            <p className="mt-2 text-white/70">Set up a workspace in a minute and upload your first document.</p>
          </div>
          <Link
            href="/register"
            className="group inline-flex shrink-0 items-center gap-2 rounded-md bg-white px-5 py-3 font-medium text-ink transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-black/30"
          >
            Create a free workspace
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden />
          </Link>
        </Reveal>
      </section>

      <footer className="border-t border-line">
        <div className="mx-auto flex max-w-6xl flex-col gap-4 px-5 py-10 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-8">
          <Logo />
          <p>Next.js, Laravel and PostgreSQL with pgvector. Built from the AI SaaS Starter Kit.</p>
        </div>
      </footer>
    </div>
  );
}
