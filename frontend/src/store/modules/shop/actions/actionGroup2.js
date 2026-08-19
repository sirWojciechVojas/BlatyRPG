export const createActionGroup2 = (deps) => {
  const {
    isRecoverableShopApiError,
    isShopActive,
    isShopApiAuthorizationError,
    normalizeShopApiError,
    resolveShopApiConfig,
    shopApiClient,
    shopEntriesFor,
    shouldAllowShopMockFallback,
    shouldUseShopApi,
  } = deps;
  return {
    async duplicateShop({ state, dispatch }, payload = {}) {
      const shopId = Number(payload.shopId ?? state.activeShopId);
      if (!Number.isFinite(shopId) || !shouldUseShopApi()) {
        return null;
      }
      try {
        const config = resolveShopApiConfig(state, {
          ownerCode: payload.ownerCode,
        });
        const response = await shopApiClient.duplicateShop(config, shopId, {
          name: payload.name,
          copyMode: payload.copyMode || "profile",
        });
        await dispatch("loadTradingData", {
          forceReload: true,
          ownerCode: config.ownerCode,
        });
        return response?.shop || null;
      } catch (error) {
        return null;
      }
    },
    async deleteShop({ state, commit, dispatch }, payload = {}) {
      const shopId = Number(payload?.shopId);
      if (shouldUseShopApi() && Number.isFinite(shopId)) {
        try {
          const config = resolveShopApiConfig(state);
          await shopApiClient.deleteShop(config, shopId);
          await dispatch("loadTradingData", {
            forceReload: true,
            ownerCode: config.ownerCode,
          });
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

      const before = state.shops.length;
      commit("removeShop", payload);
      const removed = state.shops.length < before;
      if (removed) {
        dispatch("persistTradingData");
      }
      return removed;
    },
    async updateShopActivation({ state, commit, dispatch }, payload = {}) {
      const shopId = Number(payload?.shopId);
      const requested = payload?.isActive !== false;
      if (!Number.isFinite(shopId)) {
        return { ok: false, reason: "invalid_shop" };
      }

      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state);
          const response = await shopApiClient.updateShopActivation(
            config,
            shopId,
            requested,
          );
          await dispatch("loadTradingData", {
            forceReload: true,
            ownerCode: config.ownerCode,
          });
          return {
            ok: Boolean(response?.ok),
            changed: true,
          };
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            (!isRecoverableShopApiError(error) &&
              !isShopApiAuthorizationError(error))
          ) {
            const normalized = normalizeShopApiError(error);
            return {
              ok: false,
              reason: normalized.code || "api_error",
              status: normalized.status,
            };
          }
        }
      }

      const shop = state.shops.find((entry) => Number(entry.id) === shopId);
      if (!shop) {
        return { ok: false, reason: "shop_not_found" };
      }
      const wasActive = isShopActive(shop);
      if (wasActive === requested) {
        return { ok: true, changed: false };
      }
      commit("setShopActiveFlag", {
        shopId,
        isActive: requested,
      });
      if (!requested && Number(state.activeShopId) === shopId) {
        const fallbackShop =
          state.shops.find((entry) => isShopActive(entry)) || state.shops[0];
        if (fallbackShop) {
          commit("setActiveShop", fallbackShop.id);
        }
      }
      dispatch("persistTradingData");
      return { ok: true, changed: true };
    },
    async saveShopAssortment({ state, dispatch }, payload = {}) {
      const shopId = Number(payload?.shopId ?? state.activeShopId);
      if (!Number.isFinite(shopId)) {
        return { ok: false, reason: "invalid_shop" };
      }

      const shop = state.shops.find((entry) => Number(entry.id) === shopId);
      if (!shop) {
        return { ok: false, reason: "shop_not_found" };
      }

      const shopEntries = Array.isArray(payload?.shopEntries)
        ? payload.shopEntries
        : shopEntriesFor(shop);

      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state, {
            ownerCode: payload?.ownerCode,
          });
          const response = await shopApiClient.replaceAssortment(
            config,
            shopId,
            {
              ownerCode: config.ownerCode,
              shopEntries,
              inventoryItems: state.inventoryItems,
              trashItems: state.trashItems,
            },
          );
          await dispatch("loadTradingData", {
            forceReload: true,
            ownerCode: config.ownerCode,
          });
          return { ok: true, data: response };
        } catch (error) {
          const normalized = normalizeShopApiError(error);
          return {
            ok: false,
            reason: normalized.code || "api_error",
            status: normalized.status,
            error: normalized,
          };
        }
      }

      dispatch("persistTradingData");
      return { ok: true, fallback: true };
    },
  };
};
