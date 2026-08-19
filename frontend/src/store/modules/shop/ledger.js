export const createLedgerMutations = ({ cloneItem }) => ({
  setLastTradeReceipt(state, payload) {
    state.lastTradeReceipt = payload ? cloneItem(payload) : null;
  },
  setPlayerTransactions(state, payload = []) {
    state.playerTransactions = Array.isArray(payload)
      ? payload.map(cloneItem)
      : [];
  },
  setExportedShopSnapshot(state, payload) {
    state.exportedShopSnapshot = payload || null;
  },
});
