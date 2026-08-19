export const createActionsRuntimePart1 = (runtime) => {
  const t = (key, values = {}) => runtime.i18n.global.t(key, values);
  Object.assign(runtime, {
    t,
  });
  const formatTradeCoins = (amount, currencyCode = "wfrp_empire") => {
    const definition = runtime.resolveCurrencyDefinition(
      [
        ...runtime.WFRP_CURRENCY_DEFINITIONS,
        ...runtime.COC_CURRENCY_DEFINITIONS,
        runtime.GENERIC_CURRENCY_DEFINITION,
      ],
      currencyCode,
    );
    const locale =
      typeof runtime.i18n.global.locale === "string"
        ? runtime.i18n.global.locale
        : runtime.i18n.global.locale.value;
    return runtime.formatCurrencyAmount(amount, definition, locale);
  };
  Object.assign(runtime, {
    formatTradeCoins,
  });
  const tradeItemName = (item) =>
    String(
      item?.PERSONAL_PSEU || item?.NAME || item?.DESCRIPTION || "przedmiot",
    )
      .trim()
      .replace(/\s+/g, " ");
  Object.assign(runtime, {
    tradeItemName,
  });
  const tradeSelectionSummary = (entries) => {
    if (!entries.length) {
      return "przedmiot";
    }
    const first = entries[0];
    const firstName = runtime.tradeItemName(first.item);
    const quantity = Number(first.quantity || 1);
    const suffix = quantity > 1 ? ` x${quantity}` : "";
    if (entries.length === 1) {
      return `${firstName}${suffix}`;
    }
    return `${firstName}${suffix} +${entries.length - 1}`;
  };
  Object.assign(runtime, {
    tradeSelectionSummary,
  });
  const cloneTradeUiValue = (value) =>
    JSON.parse(JSON.stringify(value ?? null));
  Object.assign(runtime, {
    cloneTradeUiValue,
  });
  const capturePlayerTradeUi = (vm) => ({
    shops: runtime.cloneTradeUiValue(vm.shops || []),
    shopItems: runtime.cloneTradeUiValue(vm.shopItems || []),
    inventoryItems: runtime.cloneTradeUiValue(vm.inventoryItems || []),
    walletBrass: Number(vm.bgWalletBrass || 0),
    walletBalances: runtime.cloneTradeUiValue(vm.walletBalances || {}),
    walletCurrencyCode: String(vm.activeSettlementCurrencyCode || "generic"),
    selectedBuyIds: [...(vm.selectedBuyIds || [])],
    selectedSellIds: [...(vm.selectedSellIds || [])],
    selectedBuyQuantities: {
      ...(vm.selectedBuyQuantities || {}),
    },
    selectedSellQuantities: {
      ...(vm.selectedSellQuantities || {}),
    },
  });
  Object.assign(runtime, {
    capturePlayerTradeUi,
  });
  const applyBuyToUi = (vm, selectedItems, totalCost) => {
    const nextId = vm.nextInventoryId();
    selectedItems.forEach(({ item, quantity }, index) => {
      vm.addInventoryStackItem({
        ID: nextId + index,
        INV_ID: item.INV_ID ?? item.ID,
        ITEM_PLACE: "PLECY",
        SLOT: "PLECY",
        PERSONAL_PSEU: "Nowy",
        PERSONAL_DESC: item.DESCRIPTION,
        PERSONAL_COST: 0,
        OWNER_OPT: vm.activeBgOwner || runtime.OWNER_CODES.BG1,
        NAME: item.NAME,
        DESCRIPTION: item.DESCRIPTION,
        IMG_CLASS: item.IMG_CLASS,
        PRIZE: item.PRIZE,
        CURRENCY: item.CURRENCY || item.currency || "wfrp_empire",
        CHARGE: vm.resolveItemCharge(item),
        QUANTITY: quantity,
      });
    });
    vm.consumeShopSelection(
      selectedItems.map(({ id, item, quantity }) => ({
        id,
        quantity,
        sourceIds: item.AGGREGATED_ITEM_IDS,
      })),
    );
    vm.adjustWalletBrass(-totalCost);
    vm.clearBuySelection();
  };
  Object.assign(runtime, {
    applyBuyToUi,
  });
  const applySellToUi = (vm, selectedItems, total) => {
    vm.sellInventorySelectionToActiveShop(selectedItems);
    vm.adjustWalletBrass(total);
    vm.clearSellSelection();
  };
  Object.assign(runtime, {
    applySellToUi,
  });
  const notifyTradeSuccess = ({
    zone,
    action,
    entries,
    total,
    currencyCode,
  }) => {
    runtime.notifyShop({
      zone,
      type: "success",
      title: "Transakcja zakończona",
      message: `${action}: ${runtime.tradeSelectionSummary(entries)}`,
      details: `Cena: ${runtime.formatTradeCoins(total, currencyCode)}`,
    });
  };
  Object.assign(runtime, {
    notifyTradeSuccess,
  });
  const notifyTradeProblem = ({
    zone,
    type = "error",
    title,
    message,
    details,
  }) => {
    runtime.notifyShop({
      zone,
      type,
      title,
      message,
      details,
    });
  };
  Object.assign(runtime, {
    notifyTradeProblem,
  });
  return {
    t,
    formatTradeCoins,
    tradeItemName,
    tradeSelectionSummary,
    cloneTradeUiValue,
    capturePlayerTradeUi,
    applyBuyToUi,
    applySellToUi,
    notifyTradeSuccess,
    notifyTradeProblem,
  };
};
