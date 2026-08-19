let vttModulePromise;

export const ensureVttStoreModule = async (store) => {
  if (store.hasModule("vtt")) return store.state.vtt;
  if (!vttModulePromise) {
    vttModulePromise = import(/* webpackChunkName: "vtt-store" */ "./vtt").then(
      (module) => module.default,
    );
  }
  const vttModule = await vttModulePromise;
  if (!store.hasModule("vtt")) store.registerModule("vtt", vttModule);
  return store.state.vtt;
};

export const ensureVttStoreModuleForRoute = async (store) => {
  await ensureVttStoreModule(store);
};
