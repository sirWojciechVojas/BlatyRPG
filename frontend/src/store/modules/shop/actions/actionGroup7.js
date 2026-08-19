export const createActionGroup7 = (deps) => {
  const {
    buildShopItems,
    cloneItem,
    createDefaultShopProfile,
    isShopActive,
    loadDemoShopData,
    loadPersistedTradeData,
    normalizeAssortment,
    normalizeLegacyInventoryRecord,
    normalizeLegacyTemplateRecord,
    normalizeLegacyTrashRecord,
    normalizeShopApiError,
    normalizeShopProfile,
    resolveShopApiConfig,
    shopApiClient,
    shopEntriesFor,
    shouldAllowShopMockFallback,
    shouldUseShopApi,
    t,
    tradeDataCacheKey,
    tradingDataLoadPromise: initialTradingDataLoadPromise,
    yieldToUiThread,
  } = deps;
  let tradingDataLoadPromise = initialTradingDataLoadPromise;
  return {
    async loadTradingData({ state, commit, dispatch }, payload = {}) {
      if (Number.isFinite(Number(payload?.campaignId))) {
        commit("setCampaignId", Number(payload.campaignId));
      }
      const forceReload = payload?.forceReload === true;
      const requestedCacheKey = tradeDataCacheKey(state, payload);
      if (
        state.tradeDataLoaded &&
        state.tradeDataCacheKey === requestedCacheKey &&
        !forceReload
      ) {
        commit("setTradeLoading", { side: "both", loading: false });
        commit("setTradeLoadError", { side: "both", error: "" });
        return { ok: true, cached: true };
      }
      if (tradingDataLoadPromise && !forceReload) {
        return tradingDataLoadPromise;
      }
      commit("setApiStatus", "loading");
      commit("setShopApiError", null);
      commit("setTradeLoading", { side: "both", loading: true });
      commit("setTradeLoadError", { side: "both", error: "" });

      tradingDataLoadPromise = (async () => {
        await yieldToUiThread();

        if (shouldUseShopApi()) {
          try {
            const config = resolveShopApiConfig(state, {
              ownerCode: payload?.ownerCode,
              campaignId: payload?.campaignId,
              viewMode: payload?.viewMode,
            });
            const bootstrap = await shopApiClient.bootstrap(config);
            commit("setShopSession", bootstrap || {});
            commit("setContainerState", bootstrap?.containerState || {});
            commit("setTradingData", {
              shops: Array.isArray(bootstrap?.shops) ? bootstrap.shops : [],
              activeShopId: bootstrap?.activeShopId ?? null,
              shopName: bootstrap?.shopName || "",
              shopItems: Array.isArray(bootstrap?.shopItems)
                ? bootstrap.shopItems
                : [],
              templateItems: Array.isArray(bootstrap?.templateItems)
                ? bootstrap.templateItems
                : [],
              inventoryItems: Array.isArray(bootstrap?.inventoryItems)
                ? bootstrap.inventoryItems
                : [],
              trashItems: Array.isArray(bootstrap?.trashItems)
                ? bootstrap.trashItems
                : [],
              catalogNodes: Array.isArray(bootstrap?.catalogNodes)
                ? bootstrap.catalogNodes
                : [],
              itemDictionaries: bootstrap?.itemDictionaries || {},
              currencyDefinitions: bootstrap?.currencyDefinitions || {},
              mechanics: bootstrap?.mechanics || {},
              allItemInstances: Array.isArray(bootstrap?.allItemInstances)
                ? bootstrap.allItemInstances
                : [],
              shopTypes: Array.isArray(bootstrap?.shopTypes)
                ? bootstrap.shopTypes
                : [],
              worldProfiles: Array.isArray(bootstrap?.worldProfiles)
                ? bootstrap.worldProfiles
                : [],
              shopProfiles:
                bootstrap?.shopProfiles &&
                typeof bootstrap.shopProfiles === "object"
                  ? bootstrap.shopProfiles
                  : {},
              walletBrass: Number(bootstrap?.walletBrass ?? NaN),
              walletBalances:
                bootstrap?.walletBalanceMap || bootstrap?.walletBalances || {},
              walletCurrencyCode: bootstrap?.walletCurrencyCode || "",
            });
            commit("setShopSuggestions", bootstrap?.shopSuggestions || []);
            commit(
              "setShopTemplateRecommendations",
              bootstrap?.shopTemplateRecommendations || [],
            );
            commit("setApiStatus", "ready");
            commit("setShopApiError", null);
            if (!state.isGM) {
              dispatch("loadPlayerTradeLedger", {
                ownerCode: bootstrap?.context?.ownerCode,
              }).catch(() => {});
            }
            await yieldToUiThread();
            commit("markTradeDataReady", tradeDataCacheKey(state, payload));
            return { ok: true };
          } catch (error) {
            if (!shouldAllowShopMockFallback()) {
              const normalized = normalizeShopApiError(error);
              const message = t("shop.tradeModal.loadError");
              commit("setApiStatus", "error");
              commit("setShopApiError", normalized);
              commit("setTradeLoading", { side: "both", loading: false });
              commit("setTradeLoadError", { side: "both", error: message });
              return { ok: false, error: normalized };
            }
          }
        }

        try {
          const {
            mockTemplates,
            mockShops,
            mockInventoryItems,
            mockTrashItems,
            shopCatalogNetwork,
            worldProfiles,
            validateShopCatalog,
          } = await loadDemoShopData();
          const validation = validateShopCatalog(shopCatalogNetwork);
          if (!validation.valid) {
            // Keep UI operational even if some nodes are malformed.
            // eslint-disable-next-line no-console
            console.warn(
              "shopCatalogNetwork validation errors:",
              validation.errors,
            );
          }
          const persisted = loadPersistedTradeData();
          const persistedTemplates = Array.isArray(persisted?.templateItems)
            ? persisted.templateItems
            : null;
          const persistedShops = Array.isArray(persisted?.shops)
            ? persisted.shops
            : null;
          const sourceTemplates = (persistedTemplates || mockTemplates).map(
            (item) => normalizeLegacyTemplateRecord(cloneItem(item)),
          );
          const sourceShops = (persistedShops || mockShops).map(cloneItem);
          const shops = sourceShops.map((shop) => {
            const entries = shopEntriesFor(shop);
            const next = {
              ...shop,
              isActive: isShopActive(shop),
              shopEntries: cloneItem(entries),
              items: buildShopItems(entries),
            };
            normalizeAssortment(next);
            next.items = buildShopItems(next.shopEntries, sourceTemplates);
            return next;
          });
          const persistedProfiles =
            persisted?.shopProfiles &&
            typeof persisted.shopProfiles === "object"
              ? persisted.shopProfiles
              : {};
          const shopProfiles = {};
          shops.forEach((shop) => {
            const fromPersisted = persistedProfiles?.[Number(shop.id)];
            shopProfiles[Number(shop.id)] = normalizeShopProfile(
              fromPersisted || createDefaultShopProfile(shop),
              shop,
            );
          });
          const persistedActiveShopId = Number(persisted?.activeShopId);
          const requestedActiveShop = shops.find(
            (shop) => Number(shop.id) === persistedActiveShopId,
          );
          const activeShop =
            (requestedActiveShop && isShopActive(requestedActiveShop)
              ? requestedActiveShop
              : null) ||
            shops.find((shop) => isShopActive(shop)) ||
            requestedActiveShop ||
            shops[0];
          commit("setTradingData", {
            shops,
            activeShopId: activeShop?.id ?? null,
            shopName: activeShop?.name ?? "",
            shopItems: activeShop ? activeShop.items.map(cloneItem) : [],
            templateItems: sourceTemplates,
            inventoryItems: Array.isArray(persisted?.inventoryItems)
              ? persisted.inventoryItems.map((item) =>
                  normalizeLegacyInventoryRecord(cloneItem(item)),
                )
              : mockInventoryItems.map((item) =>
                  normalizeLegacyInventoryRecord(cloneItem(item)),
                ),
            trashItems: Array.isArray(persisted?.trashItems)
              ? persisted.trashItems.map((item) =>
                  normalizeLegacyTrashRecord(cloneItem(item)),
                )
              : mockTrashItems.map((item) =>
                  normalizeLegacyTrashRecord(cloneItem(item)),
                ),
            catalogNodes: cloneItem(shopCatalogNetwork),
            worldProfiles: cloneItem(worldProfiles),
            shopProfiles,
          });
          commit("setApiStatus", "ready");
          commit("setShopApiError", null);
          await yieldToUiThread();
          commit("markTradeDataReady", tradeDataCacheKey(state, payload));
          return { ok: true };
        } catch (error) {
          const message = t("shop.tradeModal.loadError");
          commit("setApiStatus", "error");
          commit("setShopApiError", normalizeShopApiError(error));
          commit("setTradeLoading", { side: "both", loading: false });
          commit("setTradeLoadError", { side: "both", error: message });
          return { ok: false, error };
        }
      })();

      try {
        return await tradingDataLoadPromise;
      } finally {
        tradingDataLoadPromise = null;
      }
    },
  };
};
