const tokenIds = (values = []) => [
  ...new Set(
    values.map(Number).filter((id) => Number.isSafeInteger(id) && id > 0),
  ),
];

export const tokenSelectionMutations = {
  SELECT_TOKEN(state, selection) {
    const value =
      selection && typeof selection === "object"
        ? selection.tokenId
        : selection;
    if (value === null || value === undefined || value === "") {
      state.selectedTokenId = null;
      state.selectedTokenIds = [];
      return;
    }
    const tokenId = Number(value);
    if (!Number.isFinite(tokenId)) return;
    if (selection?.additive === true) {
      const selected = state.selectedTokenIds.includes(tokenId);
      state.selectedTokenIds = selected
        ? state.selectedTokenIds.filter((id) => id !== tokenId)
        : [...state.selectedTokenIds, tokenId];
      state.selectedTokenId = selected
        ? (state.selectedTokenIds.at(-1) ?? null)
        : tokenId;
      return;
    }
    state.selectedTokenId = tokenId;
    state.selectedTokenIds = [tokenId];
  },
  SELECT_TOKENS(state, selection = {}) {
    const selected = tokenIds(selection.tokenIds);
    state.selectedTokenIds = selection.additive
      ? tokenIds([...state.selectedTokenIds, ...selected])
      : selected;
    state.selectedTokenId = state.selectedTokenIds.at(-1) ?? null;
  },
};
