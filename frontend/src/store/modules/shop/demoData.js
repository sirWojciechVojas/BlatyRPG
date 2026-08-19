let demoDataPromise;
let suggestionEnginePromise;

export const loadDemoShopData = () => {
  if (!demoDataPromise) {
    demoDataPromise = Promise.all([
      import(/* webpackChunkName: "shop-demo-data" */ "@/mock/shopData"),
      import(
        /* webpackChunkName: "shop-demo-data" */ "@/mock/shopCatalogNetwork"
      ),
      import(/* webpackChunkName: "shop-demo-data" */ "@/mock/worldProfiles"),
      import(
        /* webpackChunkName: "shop-demo-data" */ "@/lib/shopCatalogValidation"
      ),
    ]).then(([data, catalog, profiles, validation]) => ({
      mockTemplates: data.mockTemplates,
      mockShops: data.mockShops,
      mockInventoryItems: data.mockInventoryItems,
      mockTrashItems: data.mockTrashItems,
      shopCatalogNetwork: catalog.shopCatalogNetwork,
      worldProfiles: profiles.worldProfiles,
      validateShopCatalog: validation.validateShopCatalog,
    }));
  }
  return demoDataPromise;
};

export const loadDemoSuggestionEngine = () => {
  if (!suggestionEnginePromise) {
    suggestionEnginePromise = import(
      /* webpackChunkName: "shop-suggestions" */ "@/lib/shopSuggestionEngine"
    ).then((module) => module.generateShopSuggestionBundle);
  }
  return suggestionEnginePromise;
};
