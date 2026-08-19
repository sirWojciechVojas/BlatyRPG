import { authSession } from "@/lib/auth/authSession";

export const createAuthGuard =
  (session = authSession) =>
  (to) => {
    if (!to.matched.some((record) => record.meta.requiresAuth)) return true;
    if (session.isAuthenticated()) return true;
    return {
      name: "home",
      query: { redirect: to.fullPath },
    };
  };

export const safeRedirectTarget = (router, value) => {
  if (typeof value !== "string" || !value.startsWith("/")) return null;
  if (value.startsWith("//") || value.includes("\\")) return null;
  const resolved = router.resolve(value);
  if (!resolved.matched.length || resolved.name === "not-found") return null;
  if (resolved.name === "home") return null;
  return resolved.fullPath;
};
