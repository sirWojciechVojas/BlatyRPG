export const createShopGetters = (deps) => {
  const {
    GM_MODES,
    OWNER_CODES,
    SLOT_CODES,
    aggregateTradeItems,
    findById,
    isDefaultStackInventoryItem,
    isNonTrashInventoryItem,
    pricedTradeCollection,
    resolveOwnerCode,
    sumBrass,
    t,
  } = deps;
  return {
    buyItems: (state) => {
      if (!state.isGM) {
        return pricedTradeCollection({
          state,
          items: aggregateTradeItems(state.shopItems),
          mode: "buy",
        });
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return state.inventoryItems.filter(isDefaultStackInventoryItem);
      }
      return state.templateItems;
    },
    sellItems: (state) => {
      if (!state.isGM) {
        const ownerCode = resolveOwnerCode(state);
        return pricedTradeCollection({
          state,
          items: aggregateTradeItems(
            state.inventoryItems.filter((item) => {
              const owner = String(item?.OWNER || "").toUpperCase();
              const ownerOpt = String(item?.OWNER_OPT || "").toUpperCase();
              return owner === ownerCode || ownerOpt === ownerCode;
            }),
          ),
          mode: "sell",
        });
      }
      if (state.gmMode === GM_MODES.TEMPLATES) {
        return [];
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return state.trashItems;
      }
      if (state.gmMode === GM_MODES.INVENTORY) {
        return state.inventoryItems.filter(isNonTrashInventoryItem);
      }
      return state.trashItems;
    },
    selectedTemplate: (state) =>
      findById(state.templateItems, state.selectedTemplateId),
    selectedTrash: (state) => findById(state.trashItems, state.selectedTrashId),
    selectedInventory: (state) =>
      findById(state.inventoryItems, state.selectedInventoryId),
    activeShopProfile: (state) =>
      state.shopProfiles?.[Number(state.activeShopId)] || null,
    slotCodeLabels: () => ({
      [SLOT_CODES.STOISKO]: t("shop.dataLabels.slots.STOISKO"),
    }),
    ownerCodeLabels: () => ({
      [OWNER_CODES.DEFAULT]: t("shop.dataLabels.owners.DEFAULT"),
      [OWNER_CODES.TRASH]: t("shop.dataLabels.owners.TRASH"),
      [OWNER_CODES.BG1]: t("shop.dataLabels.owners.BG1"),
      [OWNER_CODES.BG2]: t("shop.dataLabels.owners.BG2"),
      [OWNER_CODES.BG3]: t("shop.dataLabels.owners.BG3"),
    }),
    selectedShopSuggestions: (state) => {
      const selected = new Set(
        state.shopEditorState?.selectedSuggestionIds || [],
      );
      return (state.shopSuggestions || []).filter((entry) =>
        selected.has(entry.suggestionId),
      );
    },
    buyListTitle: (state) => {
      if (!state.isGM) {
        return t("shop.modules.shop");
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return t("shop.modules.defaultStackTitle");
      }
      return t("shop.modules.other");
    },
    sellListTitle: (state) => {
      if (!state.isGM) {
        const ownerCode = resolveOwnerCode(state);
        const actor = (state.actors || []).find(
          (entry) =>
            String(entry?.ownerCode || entry?.code || "").toUpperCase() ===
            ownerCode,
        );
        return t("shop.modules.characterInventoryTitle", {
          name: actor?.name || ownerCode,
        });
      }
      if (state.gmMode === GM_MODES.TEMPLATES) {
        return t("shop.modules.templatesTitle");
      }
      if (state.gmMode === GM_MODES.INVENTORY) {
        return t("shop.modules.defaultStackTitle");
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return t("shop.modules.trashTitle");
      }
      return t("shop.modules.otherTitle");
    },
    buyButtonLabel: (state) => {
      if (!state.isGM) {
        return t("actions.buy");
      }
      if (state.gmMode === GM_MODES.TEMPLATES) {
        return t("actions.clone");
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return t("actions.restore");
      }
      return t("actions.personalize");
    },
    sellButtonLabel: (state) => {
      if (!state.isGM) {
        return t("actions.sell");
      }
      if (state.gmMode === GM_MODES.TEMPLATES) {
        return t("actions.save");
      }
      if (state.gmMode === GM_MODES.INVENTORY) {
        return t("actions.trash");
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return t("actions.deletePermanent");
      }
      return t("actions.delete");
    },
    buyTotalBrass: (state, getters) => {
      if (!state.isGM) {
        if (!state.selectedBuyIds.length) {
          return 0;
        }
        return sumBrass(
          getters.buyItems || [],
          state.selectedBuyIds,
          state.selectedBuyQuantities,
        );
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return getters.selectedInventory?.PRIZE || 0;
      }
      return getters.selectedTemplate?.PRIZE || 0;
    },
    sellTotalBrass: (state, getters) => {
      if (!state.isGM) {
        if (!state.selectedSellIds.length) {
          return 0;
        }
        return sumBrass(
          getters.sellItems || [],
          state.selectedSellIds,
          state.selectedSellQuantities,
        );
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return getters.selectedTrash?.PRIZE || 0;
      }
      if (state.gmMode === GM_MODES.INVENTORY) {
        return getters.selectedInventory?.PRIZE || 0;
      }
      return getters.selectedTemplate?.PRIZE || 0;
    },
    buyHasSelection: (state, getters) => {
      if (!state.isGM) {
        return state.selectedBuyIds.length > 0;
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return !!getters.selectedInventory;
      }
      return !!getters.selectedTemplate;
    },
    sellHasSelection: (state, getters) => {
      if (!state.isGM) {
        return state.selectedSellIds.length > 0;
      }
      if (state.gmMode === GM_MODES.TRASH) {
        return !!getters.selectedTrash;
      }
      if (state.gmMode === GM_MODES.INVENTORY) {
        return !!getters.selectedInventory;
      }
      return !!getters.selectedTemplate;
    },
  };
};
