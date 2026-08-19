let shopModulePromise;

export const ensureShopStoreModule = async (store) => {
  if (store.hasModule("shop")) {
    return store.state.shop;
  }
  if (!shopModulePromise) {
    shopModulePromise = import(
      /* webpackChunkName: "shop-store" */ "./shop"
    ).then((module) => module.default);
  }
  const shopModule = await shopModulePromise;
  if (!store.hasModule("shop")) {
    store.registerModule("shop", shopModule);
  }
  return store.state.shop;
};

export const ensureShopStoreModuleForRoute = async (store) => {
  await ensureShopStoreModule(store);
};
