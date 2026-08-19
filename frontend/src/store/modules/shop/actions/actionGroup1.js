export const createActionGroup1 = (deps) => {
  const {
    buildPersistedTradePayload,
    buildTradeIdempotencyKey,
    cloneItem,
    createCatalogActions,
    createContainerActions,
    createLedgerActions,
    createTradeActions,
    isRecoverableShopApiError,
    isShopApiAuthorizationError,
    nextIdFromItems,
    normalizeShopApiError,
    persistTradeData,
    resolveOwnerCode,
    resolveItemIconClass,
    resolveShopApiConfig,
    shopApiClient,
    shouldAllowShopMockFallback,
    shouldUseShopApi,
  } = deps;
  const resolveTemplateIcon = (item = {}) => {
    const explicit = String(item.IMG_CLASS || item.imgClass || "")
      .trim()
      .toLowerCase();
    return /^v\d{4}$/u.test(explicit) ? explicit : resolveItemIconClass(item);
  };
  return {
    ...createCatalogActions({
      resolveShopApiConfig,
      shopApiClient,
      shouldUseShopApi,
    }),
    ...createContainerActions({
      normalizeShopApiError,
      resolveItemIconClass,
      resolveShopApiConfig,
      shopApiClient,
      shouldUseShopApi,
    }),
    ...createLedgerActions({
      cloneItem,
      resolveOwnerCode,
      resolveShopApiConfig,
      shopApiClient,
      shouldUseShopApi,
    }),
    ...createTradeActions({
      buildTradeIdempotencyKey,
      isRecoverableShopApiError,
      isShopApiAuthorizationError,
      nextIdFromItems,
      normalizeShopApiError,
      resolveOwnerCode,
      resolveShopApiConfig,
      shopApiClient,
      shouldAllowShopMockFallback,
      shouldUseShopApi,
    }),
    persistTradingData({ state }) {
      const payload = buildPersistedTradePayload({
        savedAt: new Date().toISOString(),
        shops: cloneItem(state.shops || []),
        templateItems: cloneItem(state.templateItems || []),
        inventoryItems: cloneItem(state.inventoryItems || []),
        trashItems: cloneItem(state.trashItems || []),
        shopProfiles: cloneItem(state.shopProfiles || {}),
        activeShopId: state.activeShopId,
      });
      persistTradeData(payload);
      return payload;
    },
    async createTemplateRecord({ state, commit, dispatch }, item = {}) {
      const resolvedItem = {
        ...item,
        IMG_CLASS: resolveTemplateIcon(item),
      };
      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state);
          const response = await shopApiClient.createTemplate(
            config,
            resolvedItem,
          );
          const createdTemplate = {
            ...(response?.template || resolvedItem),
            IMG_CLASS: resolveTemplateIcon(response?.template || resolvedItem),
          };
          commit("addTemplateItem", createdTemplate);
          return createdTemplate;
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return null;
          }
        }
      }

      commit("addTemplateItem", resolvedItem);
      dispatch("persistTradingData");
      return resolvedItem;
    },
    async saveTemplateRecord({ state, commit, dispatch }, item = {}) {
      const templateId = Number(item?.ID);
      if (!Number.isFinite(templateId)) {
        return null;
      }
      const resolvedItem = {
        ...item,
        IMG_CLASS: resolveTemplateIcon(item),
      };

      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state);
          const response = await shopApiClient.updateTemplate(
            config,
            templateId,
            resolvedItem,
          );
          const savedTemplate = {
            ...(response?.template || resolvedItem),
            IMG_CLASS: resolveTemplateIcon(response?.template || resolvedItem),
          };
          commit("updateTemplateItem", savedTemplate);
          return savedTemplate;
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return null;
          }
        }
      }

      commit("updateTemplateItem", resolvedItem);
      dispatch("persistTradingData");
      return resolvedItem;
    },
    async deleteTemplateRecord({ state, commit, dispatch }, templateIdRaw) {
      const templateId = Number(templateIdRaw);
      if (!Number.isFinite(templateId)) {
        return false;
      }

      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state);
          await shopApiClient.deleteTemplate(config, templateId);
          commit("removeTemplateItem", templateId);
          return true;
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return false;
          }
        }
      }

      commit("removeTemplateItem", templateId);
      dispatch("persistTradingData");
      return true;
    },
    async saveShopProfile({ state, commit, dispatch }, payload = {}) {
      const shopId = Number(payload?.shopId);
      if (!Number.isFinite(shopId)) {
        return null;
      }

      if (shouldUseShopApi()) {
        commit("setFormStatus", { scope: "shop", status: "saving" });
        try {
          const config = resolveShopApiConfig(state, {
            ownerCode: payload?.ownerCode,
          });
          const response = await shopApiClient.saveShopProfile(
            config,
            shopId,
            payload,
          );
          await shopApiClient.updateShop(config, shopId, {
            name: payload.signboardName,
            ownerCode: payload.ownerCode,
            ownerName: payload.ownerName,
          });
          const saved = response?.profile || payload;
          commit("createOrUpdateShopProfile", saved);
          commit("setFormStatus", { scope: "shop", status: "clean" });
          return saved;
        } catch (error) {
          commit("setFormStatus", { scope: "shop", status: "error" });
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return null;
          }
        }
      }

      commit("createOrUpdateShopProfile", payload);
      dispatch("persistTradingData");
      commit("setFormStatus", { scope: "shop", status: "clean" });
      return state.shopProfiles?.[shopId] || payload;
    },
    async createShop({ state, commit, dispatch }, payload = {}) {
      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state, {
            ownerCode: payload?.ownerCode,
          });
          const response = await shopApiClient.createShop(config, payload);
          await dispatch("loadTradingData", {
            forceReload: true,
            ownerCode: config.ownerCode,
          });
          return Number(response?.shop?.id ?? state.activeShopId ?? 0) || null;
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return null;
          }
        }
      }

      const before = state.shops.length;
      commit("createShop", payload);
      if (state.shops.length <= before) {
        return null;
      }
      dispatch("persistTradingData");
      return state.activeShopId;
    },
  };
};
