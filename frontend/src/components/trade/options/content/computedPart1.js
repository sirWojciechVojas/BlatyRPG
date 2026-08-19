export const createContentComputedPart1 = () => ({
  visiblePlayerWallets() {
    const activeCurrencyCode = String(
      this.activeSettlementCurrencyCode || "generic",
    ).toLowerCase();
    const defaultCurrencyCode = String(
      this.currencyDefinitions?.defaultCurrencyCode || "generic",
    ).toLowerCase();
    const definitions = Array.isArray(this.currencyDefinitions?.currencies)
      ? this.currencyDefinitions.currencies
      : [];
    const isPolish = String(this.$i18n?.locale || "pl").startsWith("pl");
    const knownCodes = new Set(
      definitions.map((definition) =>
        String(definition?.code || "").toLowerCase(),
      ),
    );
    Object.keys(this.walletBalances || {}).forEach((currencyCode) =>
      knownCodes.add(String(currencyCode).toLowerCase()),
    );
    knownCodes.add(activeCurrencyCode);
    return Array.from(knownCodes)
      .map((currencyCode) => {
        const definition = definitions.find(
          (entry) => String(entry?.code || "").toLowerCase() === currencyCode,
        );
        const balance = Math.max(
          0,
          Number(
            this.walletBalances?.[currencyCode] ??
              (currencyCode === activeCurrencyCode ? this.bgWalletBrass : 0),
          ) || 0,
        );
        return {
          currencyCode,
          balance,
          label:
            (isPolish ? definition?.labelPl : definition?.labelEn) ||
            definition?.labelPl ||
            definition?.labelEn ||
            definition?.code ||
            currencyCode,
          isSettlementCurrency: currencyCode === activeCurrencyCode,
          isDefaultCurrency: currencyCode === defaultCurrencyCode,
        };
      })
      .filter((wallet) => wallet.balance > 0 || wallet.isSettlementCurrency)
      .sort((left, right) => {
        if (left.isSettlementCurrency !== right.isSettlementCurrency) {
          return left.isSettlementCurrency ? -1 : 1;
        }
        return left.label.localeCompare(right.label, "pl");
      });
  },
  assortmentSourceOptions() {
    const systemKeys = new Set(["DEFAULT", "TRASH"]);
    return (this.containerSelectOptions || []).filter((entry) => {
      if (entry?.type !== "SYSTEM") {
        return false;
      }
      return systemKeys.has(String(entry?.container?.systemKey || ""));
    });
  },
  assortmentShopOptions() {
    return (this.containerSelectOptions || []).filter(
      (entry) => entry?.type === "SHOP",
    );
  },
  canMoveAssortmentToShop() {
    return (
      Number.isFinite(Number(this.assortmentLeftContainerId)) &&
      Number.isFinite(Number(this.assortmentRightContainerId)) &&
      this.assortmentLeftSelectedKeys.length > 0
    );
  },
  canMoveAssortmentToStack() {
    return (
      Number.isFinite(Number(this.assortmentLeftContainerId)) &&
      Number.isFinite(Number(this.assortmentRightContainerId)) &&
      this.assortmentRightSelectedKeys.length > 0
    );
  },
  assortmentLeftContainerModel: {
    get() {
      return this.assortmentLeftContainerId;
    },
    set(value) {
      this.$emit(
        "update:assortmentLeftContainerId",
        this.toNumberOrNull(value),
      );
    },
  },
  assortmentSearchModel: {
    get() {
      return this.assortmentSearch;
    },
    set(value) {
      this.$emit("update:assortmentSearch", String(value ?? ""));
    },
  },
  gmMoveItemKeyModel: {
    get() {
      return this.gmMoveItemKey;
    },
    set(value) {
      this.$emit("update:gmMoveItemKey", String(value ?? ""));
    },
  },
  gmMoveTargetContainerModel: {
    get() {
      return this.gmMoveTargetContainerId;
    },
    set(value) {
      this.$emit("update:gmMoveTargetContainerId", this.toNumberOrNull(value));
    },
  },
  gmMoveQuantityModel: {
    get() {
      return this.gmMoveQuantity;
    },
    set(value) {
      this.$emit("update:gmMoveQuantity", value);
    },
  },
  assortmentRightContainerModel: {
    get() {
      return this.assortmentRightContainerId;
    },
    set(value) {
      this.$emit(
        "update:assortmentRightContainerId",
        this.toNumberOrNull(value),
      );
    },
  },
  shopBuyContainerModel: {
    get() {
      return this.shopBuyContainerId;
    },
    set(value) {
      this.$emit("update:shopBuyContainerId", this.toNumberOrNull(value));
    },
  },
  shopBuyItemKeyModel: {
    get() {
      return this.shopBuyItemKey;
    },
    set(value) {
      this.$emit("update:shopBuyItemKey", String(value ?? ""));
    },
  },
  shopBuyTargetContainerModel: {
    get() {
      return this.shopBuyTargetContainerId;
    },
    set(value) {
      this.$emit("update:shopBuyTargetContainerId", this.toNumberOrNull(value));
    },
  },
  shopBuyQuantityModel: {
    get() {
      return this.shopBuyQuantity;
    },
    set(value) {
      this.$emit("update:shopBuyQuantity", value);
    },
  },
  trashZoneOwnerCodeModel: {
    get() {
      return this.trashZoneOwnerCode || "TRASH";
    },
    set(value) {
      this.$emit("update:trashZoneOwnerCode", String(value || "TRASH"));
    },
  },
  inventoryOwnerCodeFilterModel: {
    get() {
      return this.inventoryOwnerCodeFilter || "all";
    },
    set(value) {
      this.$emit("update:inventoryOwnerCodeFilter", String(value || "all"));
    },
  },
  iconSizeModel: {
    get() {
      const parsed = Number(this.iconSize);
      if (!Number.isFinite(parsed)) {
        return Number(this.iconSizeDefault) || 34;
      }
      return parsed;
    },
    set(value) {
      const parsed = Number(value);
      if (!Number.isFinite(parsed)) {
        return;
      }
      this.$emit("update:iconSize", parsed);
    },
  },
});
