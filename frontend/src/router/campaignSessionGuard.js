import { ensureCampaignContextForRoute } from "@/store/modules/loadCampaignContextModule";
import { connectRealtimeForRoute } from "@/store/modules/loadRealtimeModule";

const routeCampaignId = (route) => {
  const id = Number(route?.params?.campaignId);
  return Number.isInteger(id) && id > 0 ? id : null;
};

const requires = (route, key) =>
  route?.matched?.some((record) => record.meta?.[key] === true) === true;

const hasModule = (store, name) =>
  typeof store.hasModule === "function"
    ? store.hasModule(name)
    : Boolean(store.state?.[name]);

export const leaveCampaignSession = async (store) => {
  const tasks = [];
  if (hasModule(store, "realtime")) {
    tasks.push(store.dispatch("realtime/disconnect"));
  }
  if (hasModule(store, "campaignContext")) {
    tasks.push(store.dispatch("campaignContext/leaveCampaign"));
  }
  await Promise.allSettled(tasks);
};

export const ensureCampaignRouteSession = async (store, route) => {
  const campaignId = routeCampaignId(route);
  if (!campaignId) return null;
  await ensureCampaignContextForRoute(store, route);
  const context = store.state.campaignContext;
  if (
    Number(context?.campaignId) !== campaignId ||
    Number(context?.currentCampaign?.id) !== campaignId ||
    context?.unauthorized === true ||
    context?.error
  ) {
    return null;
  }
  await connectRealtimeForRoute(store, route);
  return store.state.campaignContext?.currentCampaign || null;
};

export const campaignCanManageRoute = (store, route) => {
  if (
    Number(store.state.campaignContext?.campaignId) !== routeCampaignId(route)
  ) {
    return false;
  }
  if (!requires(route, "requiresGm")) return true;
  const capabilities = store.state.campaignContext?.capabilities || {};
  return capabilities.canManage === true || capabilities.canOpenShop === true;
};

export const createCampaignAuthorization = (store) => async (route) => {
  if (!routeCampaignId(route)) return false;
  try {
    await ensureCampaignContextForRoute(store, route);
    return campaignCanManageRoute(store, route);
  } catch (error) {
    if (error?.status === 401) throw error;
    return false;
  }
};

export const createCampaignSessionGuard = (store) => async (to, from) => {
  const nextCampaignId = routeCampaignId(to);
  const previousCampaignId = routeCampaignId(from);
  if (!nextCampaignId) {
    if (previousCampaignId) await leaveCampaignSession(store);
    return true;
  }

  try {
    const campaign = await ensureCampaignRouteSession(store, to);
    if (!campaign) return false;
    return campaignCanManageRoute(store, to) ? true : { name: "forbidden" };
  } catch (error) {
    await leaveCampaignSession(store);
    if (error?.status === 401) {
      return { name: "home", query: { redirect: to.fullPath } };
    }
    return { name: "forbidden" };
  }
};

export { routeCampaignId };
