import { authSession } from "./authSession";
import { leaveCampaignSession } from "@/router/campaignSessionGuard";

const isProtectedRoute = (route) =>
  route?.matched?.some((record) => record.meta?.requiresAuth === true) === true;

const currentRoute = (router) =>
  router?.currentRoute?.value || router?.currentRoute || null;

export const installAuthLifecycle = (options = {}) => {
  const session = options.session || authSession;
  const store = options.store;
  const router = options.router;
  if (!store || !router) throw new TypeError("store_and_router_required");

  const handleChange = async (nextSession, reason) => {
    options.onSession?.(nextSession, reason);
    if (nextSession || reason === "initial") return;

    await leaveCampaignSession(store);
    const route = currentRoute(router);
    if (isProtectedRoute(route)) {
      await router.replace({
        name: "login",
        query: { redirect: route.fullPath },
      });
    }
  };

  return session.subscribe(
    (nextSession, reason) => handleChange(nextSession, reason).catch(() => {}),
    { immediate: true },
  );
};
