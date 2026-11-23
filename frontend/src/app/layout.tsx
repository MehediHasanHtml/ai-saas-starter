import type { Metadata } from "next";
import "@fontsource/ibm-plex-sans/400.css";
import "@fontsource/ibm-plex-sans/500.css";
import "@fontsource/ibm-plex-sans/600.css";
import "@fontsource/ibm-plex-serif/400.css";
import "@fontsource/ibm-plex-serif/400-italic.css";
import "@fontsource/ibm-plex-mono/400.css";
import "./globals.css";

const appName = process.env.NEXT_PUBLIC_APP_NAME ?? "Margin";

export const metadata: Metadata = {
  title: { default: `${appName} — answers from your documents`, template: `%s | ${appName}` },
  description: "Chat with your team's documents. Every answer cites the page it came from.",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
