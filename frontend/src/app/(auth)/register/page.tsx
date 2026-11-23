import { AuthForm } from "@/components/marketing/auth-form";

export const metadata = { title: "Create your workspace" };

export default function RegisterPage() {
  return <AuthForm mode="register" />;
}
