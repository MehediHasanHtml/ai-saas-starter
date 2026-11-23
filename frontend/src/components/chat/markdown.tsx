"use client";

import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";

/**
 * Renders an AI answer. Bracket citations like [2] become highlighter chips
 * that focus the matching source note in the margin.
 */
export function AnswerMarkdown({ content, messageId, onCite }: { content: string; messageId: string | number; onCite?: (n: number) => void }) {
  // [3] -> [3](#cite-3), but leave real markdown links "[text](url)" alone.
  const withCites = content.replace(/\[(\d{1,2})\](?!\()/g, "[$1](#cite-$1)");

  return (
    <div className="prose-chat">
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        components={{
          a({ href, children }) {
            const match = href?.match(/^#cite-(\d+)$/);
            if (match) {
              const n = Number(match[1]);
              return (
                <a href={`#src-${messageId}-${n}`} className="cite no-underline" aria-label={`Source ${n}`} onClick={() => onCite?.(n)}>
                  {n}
                </a>
              );
            }
            return (
              <a href={href} target="_blank" rel="noreferrer">
                {children}
              </a>
            );
          },
        }}
      >
        {withCites}
      </ReactMarkdown>
    </div>
  );
}
