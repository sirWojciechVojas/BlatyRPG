let modulePromise;

export const ensureCampaignContextModule = async (store) => {
  if (store.hasModule("campaignContext")) return store.state.campaignContext;
  if (!modulePromise) {
    modulePromise = import(
      /* webpackChunkName: "campaign-context-store" */ "./campaignContext"
    ).then((module) => module.default);
  }
  const module = await modulePromise;
  if (!store.hasModule("campaignContext")) {
    store.registerModule("campaignContext", module);
  }
  return store.state.campaignContext;
};

export const ensureCampaignContextForRoute = async (store, to) => {
  await ensureCampaignContextModule(store);
  const campaignId = Number(to.params.campaignId);
  const context = store.state.campaignContext;
  const hasReadyCampaign =
    Number(context.currentCampaign?.id) === campaignId &&
    context.phase === "ready" &&
    context.unauthorized !== true &&
    !context.error;
  if (campaignId > 0 && !hasReadyCampaign) {
    await store.dispatch("campaignContext/selectCampaign", campaignId);
  }
};
