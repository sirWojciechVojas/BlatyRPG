export const createLedgerActions = ({
  cloneItem,
  resolveOwnerCode,
  resolveShopApiConfig,
  shopApiClient,
  shouldUseShopApi,
}) => ({
  async loadPlayerTradeLedger({ state, commit }, payload = {}) {
    if (!shouldUseShopApi()) {
      return [];
    }
    const ownerCode = resolveOwnerCode(state, payload.ownerCode);
    try {
      const response = await shopApiClient.listTradeLedger(
        resolveShopApiConfig(state, { ownerCode }),
        {
          ownerCode,
          page: Number(payload.page || 1),
          pageSize: Math.min(20, Number(payload.pageSize || 10)),
        },
      );
      commit("setPlayerTransactions", response?.items || []);
      return response?.items || [];
    } catch (error) {
      return [];
    }
  },
  exportShopSnapshot({ state, commit }) {
    const payload = {
      exportedAt: new Date().toISOString(),
      shops: cloneItem(state.shops || []),
      templateItems: cloneItem(state.templateItems || []),
      shopProfiles: cloneItem(state.shopProfiles || {}),
      catalogNodes: cloneItem(state.catalogNodes || []),
      worldProfiles: cloneItem(state.worldProfiles || []),
    };
    commit("setExportedShopSnapshot", payload);
    return payload;
  },
});
