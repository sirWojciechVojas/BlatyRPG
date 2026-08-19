const activeWalletCurrencyCode = (state = {}) =>
  String(
    state.shopProfiles?.[Number(state.activeShopId)]?.pricingConfig
      ?.currencyPolicy?.settlementCurrencyCode ||
      state.walletCurrencyCode ||
      state.currencyDefinitions?.defaultCurrencyCode ||
      "generic",
  ).toLowerCase();

export const createTradeMutations = () => ({
  setTradeLoading(state, payload = {}) {
    const side = String(payload?.side || "both");
    const value = payload?.loading === true;
    if (side === "buy" || side === "both") {
      state.loadingBuy = value;
    }
    if (side === "sell" || side === "both") {
      state.loadingSell = value;
    }
  },
  setTradeLoadError(state, payload = {}) {
    const side = String(payload?.side || "both");
    const error = String(payload?.error || "");
    if (side === "buy" || side === "both") {
      state.errorBuy = error;
    }
    if (side === "sell" || side === "both") {
      state.errorSell = error;
    }
  },
  adjustWalletBrass(state, delta) {
    const currencyCode = activeWalletCurrencyCode(state);
    const current = Number(
      state.walletBalances?.[currencyCode] ?? state.bgWalletBrass,
    );
    const balance = Math.max(0, (Number(current) || 0) + Number(delta || 0));
    state.walletBalances = {
      ...(state.walletBalances || {}),
      [currencyCode]: balance,
    };
    state.walletCurrencyCode = currencyCode;
    state.bgWalletBrass = balance;
  },
  setWalletBrass(state, value) {
    if (Number.isFinite(Number(value))) {
      const currencyCode = activeWalletCurrencyCode(state);
      const balance = Math.max(0, Number(value));
      state.bgWalletBrass = balance;
      state.walletCurrencyCode = currencyCode;
      state.walletBalances = {
        ...(state.walletBalances || {}),
        [currencyCode]: balance,
      };
    }
  },
  setWalletBalance(state, payload = {}) {
    const currencyCode = String(
      payload.currencyCode || activeWalletCurrencyCode(state),
    ).toLowerCase();
    const balance = Number(payload.balance);
    if (!currencyCode || !Number.isFinite(balance)) {
      return;
    }
    state.walletBalances = {
      ...(state.walletBalances || {}),
      [currencyCode]: Math.max(0, balance),
    };
    state.walletCurrencyCode = currencyCode;
    if (currencyCode === activeWalletCurrencyCode(state)) {
      state.bgWalletBrass = Math.max(0, balance);
    }
  },
  setWalletBalances(state, balances = {}) {
    const normalized = {};
    if (Array.isArray(balances)) {
      balances.forEach((entry) => {
        const currencyCode = String(entry?.currencyCode || "").toLowerCase();
        const balance = Number(entry?.balance);
        if (currencyCode && Number.isFinite(balance)) {
          normalized[currencyCode] = Math.max(0, balance);
        }
      });
    } else if (balances && typeof balances === "object") {
      Object.entries(balances).forEach(([currencyCode, value]) => {
        const balance = Number(value);
        if (currencyCode && Number.isFinite(balance)) {
          normalized[String(currencyCode).toLowerCase()] = Math.max(0, balance);
        }
      });
    }
    state.walletBalances = normalized;
    const activeCurrencyCode = activeWalletCurrencyCode(state);
    state.walletCurrencyCode = activeCurrencyCode;
    state.bgWalletBrass = Number(normalized[activeCurrencyCode] || 0);
  },
});
