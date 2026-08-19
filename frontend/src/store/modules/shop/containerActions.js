export const createContainerActions = ({
  normalizeShopApiError,
  resolveItemIconClass,
  resolveShopApiConfig,
  shopApiClient,
  shouldUseShopApi,
}) => {
  const resolveInstanceIcon = (payload = {}) => {
    const explicit = String(
      payload.imgClass || payload.IMG_CLASS || payload.icon || "",
    )
      .trim()
      .toLowerCase();
    return /^v\d{4}$/u.test(explicit)
      ? explicit
      : resolveItemIconClass(payload);
  };

  return {
    async createItemInstanceRecord({ state, commit, dispatch }, payload = {}) {
      const templateId = Number(payload.templateId);
      const containerId = Number(payload.containerId);
      if (
        !Number.isFinite(templateId) ||
        templateId <= 0 ||
        !Number.isFinite(containerId) ||
        containerId <= 0 ||
        !shouldUseShopApi()
      ) {
        return null;
      }
      commit("setFormStatus", { scope: "instance", status: "saving" });
      try {
        const ownerCode = String(payload.ownerCode || "BG1").toUpperCase();
        const requestPayload = {
          ...payload,
          imgClass: resolveInstanceIcon(payload),
        };
        const response = await shopApiClient.createItemInstance(
          resolveShopApiConfig(state, { ownerCode }),
          requestPayload,
        );
        if (response?.containerState) {
          commit("setContainerState", response.containerState);
        }
        await dispatch("loadTradingData", {
          campaignId: state.campaignId,
          ownerCode,
          forceReload: true,
        });
        commit("setFormStatus", { scope: "instance", status: "clean" });
        return response?.item || response?.itemInstance || null;
      } catch (error) {
        commit("setFormStatus", { scope: "instance", status: "error" });
        return null;
      }
    },
    async transferAssortmentMoves({ state, commit }, payload = {}) {
      const shopId = Number(payload.shopId ?? state.activeShopId);
      const moves = Array.isArray(payload.moves) ? payload.moves : [];
      if (!Number.isFinite(shopId) || !moves.length || !shouldUseShopApi()) {
        return { ok: false, code: "invalid_payload" };
      }
      try {
        const response = await shopApiClient.transferAssortment(
          resolveShopApiConfig(state, { ownerCode: payload.ownerCode }),
          shopId,
          moves,
        );
        if (response?.containerState) {
          commit("setContainerState", response.containerState);
        }
        return response;
      } catch (error) {
        return { ok: false, ...normalizeShopApiError(error) };
      }
    },
    async saveItemInstance({ state, commit }, payload = {}) {
      const instanceId = Number(payload.id ?? payload.instanceId);
      const formScope = String(payload.formScope || "instance");
      if (!Number.isFinite(instanceId) || !shouldUseShopApi()) {
        return null;
      }
      commit("setFormStatus", { scope: formScope, status: "saving" });
      try {
        const requestPayload = {
          ...payload,
          imgClass: resolveInstanceIcon(payload),
        };
        delete requestPayload.formScope;
        const response = await shopApiClient.updateItemInstance(
          resolveShopApiConfig(state, { ownerCode: payload.ownerCode }),
          instanceId,
          requestPayload,
        );
        if (response?.containerState) {
          commit("setContainerState", response.containerState);
        }
        commit("setFormStatus", { scope: formScope, status: "clean" });
        return response?.itemInstance || null;
      } catch (error) {
        commit("setFormStatus", { scope: formScope, status: "error" });
        return null;
      }
    },
    async moveContainerItems({ state, commit }, payload = {}) {
      const moves = Array.isArray(payload.moves) ? payload.moves : [];
      if (!moves.length || !shouldUseShopApi()) {
        return { ok: false, code: "invalid_payload" };
      }
      try {
        const response = await shopApiClient.moveContainerItems(
          resolveShopApiConfig(state, { ownerCode: payload.ownerCode }),
          moves,
        );
        if (response?.containerState) {
          commit("setContainerState", response.containerState);
        }
        return response;
      } catch (error) {
        return { ok: false, ...normalizeShopApiError(error) };
      }
    },
    async setContainerQuantities({ state, commit }, payload = {}) {
      const changes = Array.isArray(payload.changes) ? payload.changes : [];
      if (!changes.length || !shouldUseShopApi()) {
        return { ok: false, code: "invalid_payload" };
      }
      try {
        const response = await shopApiClient.setContainerQuantities(
          resolveShopApiConfig(state, { ownerCode: payload.ownerCode }),
          changes,
        );
        if (response?.containerState) {
          commit("setContainerState", response.containerState);
        }
        return response;
      } catch (error) {
        return { ok: false, ...normalizeShopApiError(error) };
      }
    },
  };
};
