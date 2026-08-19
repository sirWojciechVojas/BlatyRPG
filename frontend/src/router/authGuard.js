import { authSession } from "@/lib/auth/authSession";
import { defaultAuthenticatedRoute } from "@/lib/auth/authNavigation";

export const createAuthGuard =
  (session = authSession, campaignAuthorization = null) =>
  async (to) => {
    const current = session.read?.();
    const authenticated = Boolean(current && session.isAuthenticated());
    const redirectsAuthenticated = to.matched.some(
      (record) => record.meta.redirectAuthenticated,
    );
    if (redirectsAuthenticated && authenticated) {
      return defaultAuthenticatedRoute(current);
    }

    if (!to.matched.some((record) => record.meta.requiresAuth)) return true;
    if (!authenticated) {
      return {
        name: "login",
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
    } catch (error) {
      if (error?.status === 401) {
        session.clear?.("unauthorized");
        return { name: "login", query: { redirect: to.fullPath } };
      }
      return { name: "forbidden" };
    }
  };

export { safeRedirectTarget } from "@/lib/auth/authNavigation";
