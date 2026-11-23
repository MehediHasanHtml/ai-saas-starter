import { AuthForm } from "@/components/marketing/auth-form";

export const metadata = { title: "Sign in" };

export default function LoginPage() {
  return <AuthForm mode="login" />;
}
