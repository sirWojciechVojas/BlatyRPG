export const createTradeActions = ({
  buildTradeIdempotencyKey,
  isRecoverableShopApiError,
  isShopApiAuthorizationError,
  normalizeShopApiError,
  resolveOwnerCode,
  resolveShopApiConfig,
  shopApiClient,
  shouldAllowShopMockFallback,
  shouldUseShopApi,
}) => ({
  async playerQuoteBuyPayment({ state }, payload = {}) {
    if (!shouldUseShopApi()) {
      return { handled: false };
    }
    const ownerCode = resolveOwnerCode(state, payload?.ownerCode);
    const shopId = Number(payload?.shopId ?? state.activeShopId);
    const selections = (
      Array.isArray(payload?.selections) ? payload.selections : []
    ).map((entry) => ({
      templateId: Number(entry?.templateId),
      instanceId: Number(entry?.instanceId ?? entry?.clientId ?? entry?.id),
      quantity: Number(entry?.quantity || 1),
      clientId: Number(entry?.clientId ?? entry?.id),
    }));
    try {
      const response = await shopApiClient.quoteTradeBuyPayment(
        resolveShopApiConfig(state, { ownerCode }),
        {
          ownerCode,
          shopId,
          selections,
          selectedCurrencyCodes: payload?.selectedCurrencyCodes,
        },
      );
      return { handled: true, ok: true, data: response };
    } catch (error) {
      return {
        handled: true,
        ok: false,
        error: normalizeShopApiError(error),
      };
    }
  },
  async playerBuyFromShop({ state, commit, dispatch }, payload = {}) {
    if (!shouldUseShopApi()) {
      return { handled: false };
    }

    const ownerCode = resolveOwnerCode(state, payload?.ownerCode);
    const shopId = Number(payload?.shopId ?? state.activeShopId);
    const selections = Array.isArray(payload?.selections)
      ? payload.selections
          .map((entry) => ({
            templateId: Number(entry?.templateId),
            instanceId: Number(
              entry?.instanceId ?? entry?.clientId ?? entry?.id,
            ),
            quantity: Number(entry?.quantity || 1),
            clientId: Number(entry?.clientId ?? entry?.id),
          }))
          .filter(
            (entry) =>
              Number.isFinite(entry.templateId) &&
              Number.isFinite(entry.quantity) &&
              entry.quantity > 0,
          )
      : [];

    if (!Number.isFinite(shopId) || !selections.length) {
      return { handled: true, ok: false, error: { code: "invalid_payload" } };
    }

    try {
      const config = resolveShopApiConfig(state, { ownerCode });
      const actorName =
        state.actors.find(
          (actor) => String(actor.ownerCode || "").toUpperCase() === ownerCode,
        )?.name || ownerCode;
      const response = await shopApiClient.tradeBuy(
        config,
        {
          ownerCode,
          actorName,
          shopId,
          selections,
          encumbrance: payload?.encumbrance || null,
          payment: payload?.payment || null,
        },
        buildTradeIdempotencyKey("buy"),
      );
      if (response?.containerState) {
        commit("setContainerState", response.containerState);
      }
      if (response?.walletBalances) {
        commit("setWalletBalances", response.walletBalances);
      }
      commit("setWalletBalance", {
        currencyCode: response?.walletCurrencyCode || response?.currency,
        balance: response?.walletBalance ?? response?.walletBrass,
      });
      commit("setLastTradeReceipt", {
        ...response,
        transactionType: "BUY",
        createdAt: new Date().toISOString(),
      });
      dispatch("loadPlayerTradeLedger", { ownerCode }).catch(() => {});
      return { handled: true, ok: true, data: response };
    } catch (error) {
      const normalized = normalizeShopApiError(error);
      if (
        shouldAllowShopMockFallback() &&
        (isRecoverableShopApiError(normalized) ||
          isShopApiAuthorizationError(normalized))
      ) {
        return { handled: false, error: normalized };
      }
      return { handled: true, ok: false, error: normalized };
    }
  },
  async playerSellToShop({ state, commit, dispatch }, payload = {}) {
    if (!shouldUseShopApi()) {
      return { handled: false };
    }

    const ownerCode = resolveOwnerCode(state, payload?.ownerCode);
    const shopId = Number(payload?.shopId ?? state.activeShopId);
    const selections = Array.isArray(payload?.selections)
      ? payload.selections
          .map((entry) => ({
            templateId: Number(entry?.templateId),
            instanceId: Number(
              entry?.instanceId ?? entry?.clientId ?? entry?.id,
            ),
            quantity: Number(entry?.quantity || 1),
            clientId: Number(entry?.clientId ?? entry?.id),
          }))
          .filter(
            (entry) =>
              Number.isFinite(entry.templateId) &&
              Number.isFinite(entry.quantity) &&
              entry.quantity > 0,
          )
      : [];

    if (!Number.isFinite(shopId) || !selections.length) {
      return { handled: true, ok: false, error: { code: "invalid_payload" } };
    }

    try {
      const config = resolveShopApiConfig(state, { ownerCode });
      const actorName =
        state.actors.find(
          (actor) => String(actor.ownerCode || "").toUpperCase() === ownerCode,
        )?.name || ownerCode;
      const response = await shopApiClient.tradeSell(
        config,
        { ownerCode, actorName, shopId, selections },
        buildTradeIdempotencyKey("sell"),
      );
      if (response?.containerState) {
        commit("setContainerState", response.containerState);
      }
      if (response?.walletBalances) {
        commit("setWalletBalances", response.walletBalances);
      }
      commit("setWalletBalance", {
        currencyCode: response?.walletCurrencyCode || response?.currency,
        balance: response?.walletBalance ?? response?.walletBrass,
      });
      commit("setLastTradeReceipt", {
        ...response,
        transactionType: "SELL",
        createdAt: new Date().toISOString(),
      });
      dispatch("loadPlayerTradeLedger", { ownerCode }).catch(() => {});
      return { handled: true, ok: true, data: response };
    } catch (error) {
      const normalized = normalizeShopApiError(error);
      if (
        shouldAllowShopMockFallback() &&
        (isRecoverableShopApiError(normalized) ||
          isShopApiAuthorizationError(normalized))
      ) {
        return { handled: false, error: normalized };
      }
      return { handled: true, ok: false, error: normalized };
    }
  },
});
