import { authSession } from "@/lib/auth/authSession";

export const createAuthGuard =
  (session = authSession, campaignAuthorization = null) =>
  async (to) => {
    if (!to.matched.some((record) => record.meta.requiresAuth)) return true;
    const current = session.read?.();
    if (!current || !session.isAuthenticated()) {
      return {
        name: "home",
        query: { redirect: to.fullPath },
      };
    }

    const requiresAdmin = to.matched.some(
      (record) => record.meta.requiresAdmin,
    );
    if (requiresAdmin && current.user?.role !== "admin") {
      return { name: "forbidden" };
    }

    const requiresGm = to.matched.some((record) => record.meta.requiresGm);
    if (!requiresGm) return true;
    if (typeof campaignAuthorization !== "function") {
      return { name: "forbidden" };
    }
    try {
      const allowed = await campaignAuthorization(to, current);
      return allowed === true ? true : { name: "forbidden" };
    } catch (_error) {
      return { name: "forbidden" };
    }
  };

export const safeRedirectTarget = (router, value) => {
  if (typeof value !== "string" || !value.startsWith("/")) return null;
  if (value.startsWith("//") || value.includes("\\")) return null;
  const resolved = router.resolve(value);
  if (!resolved.matched.length || resolved.name === "not-found") return null;
  if (resolved.name === "home") return null;
  return resolved.fullPath;
};
