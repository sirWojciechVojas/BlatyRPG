let modulePromise;

export const ensureRealtimeModule = async (store) => {
  if (store.hasModule("realtime")) return store.state.realtime;
  if (!modulePromise) {
    modulePromise = import(
      /* webpackChunkName: "realtime-store" */ "./realtime"
    ).then((module) => module.default);
  }
  const module = await modulePromise;
  if (!store.hasModule("realtime")) store.registerModule("realtime", module);
  return store.state.realtime;
};

export const connectRealtimeForRoute = async (store, to) => {
  await ensureRealtimeModule(store);
  const campaignId = Number(to.params.campaignId);
  if (campaignId > 0) await store.dispatch("realtime/connect", campaignId);
};
