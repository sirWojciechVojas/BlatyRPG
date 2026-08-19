export const createMutationGroup5 = (deps) => {
  const {
    buildShopItems,
    cloneItem,
    normalizeAssortment,
    normalizeLegacyInventoryRecord,
    normalizeLegacyTemplateRecord,
    normalizeLegacyTrashRecord,
    resolveTemplateId,
    shopEntriesFor,
    toNonNegativeNumber,
    touchTradeCacheState,
  } = deps;
  return {
    setSellSelectionQuantity(state, payload) {
      const id = Number(payload?.id);
      if (!Number.isFinite(id)) {
        return;
      }
      const max = Math.max(1, Number(payload?.max || 1));
      const raw = Number(payload?.quantity);
      const nextQty = Number.isFinite(raw)
        ? Math.max(1, Math.min(max, Math.round(raw)))
        : 1;
      state.selectedSellQuantities = {
        ...state.selectedSellQuantities,
        [id]: nextQty,
      };
      if (!state.selectedSellIds.includes(id)) {
        state.selectedSellIds.push(id);
      }
    },
    setSelectedTemplateId(state, id) {
      state.selectedTemplateId = id;
    },
    setSelectedTrashId(state, id) {
      state.selectedTrashId = id;
    },
    setSelectedInventoryId(state, id) {
      state.selectedInventoryId = id;
    },
    setInventoryItems(state, items) {
      state.inventoryItems = Array.isArray(items)
        ? items.map((item) => normalizeLegacyInventoryRecord(cloneItem(item)))
        : [];
      touchTradeCacheState(state);
    },
    setTrashItems(state, items) {
      state.trashItems = Array.isArray(items)
        ? items.map((item) => normalizeLegacyTrashRecord(cloneItem(item)))
        : [];
      touchTradeCacheState(state);
    },
    clearSelections(state) {
      state.selectedBuyIds = [];
      state.selectedSellIds = [];
      state.selectedBuyQuantities = {};
      state.selectedSellQuantities = {};
      state.selectedTemplateId = null;
      state.selectedTrashId = null;
      state.selectedInventoryId = null;
    },
    clearBuySelection(state) {
      state.selectedBuyIds = [];
      state.selectedBuyQuantities = {};
    },
    clearSellSelection(state) {
      state.selectedSellIds = [];
      state.selectedSellQuantities = {};
    },
    restorePlayerTradeUi(state, snapshot = {}) {
      state.shops = Array.isArray(snapshot.shops)
        ? cloneItem(snapshot.shops)
        : state.shops;
      state.shopItems = Array.isArray(snapshot.shopItems)
        ? cloneItem(snapshot.shopItems)
        : state.shopItems;
      state.inventoryItems = Array.isArray(snapshot.inventoryItems)
        ? cloneItem(snapshot.inventoryItems)
        : state.inventoryItems;
      if (Number.isFinite(Number(snapshot.walletBrass))) {
        state.bgWalletBrass = Math.max(0, Number(snapshot.walletBrass));
      }
      if (
        snapshot.walletBalances &&
        typeof snapshot.walletBalances === "object"
      ) {
        state.walletBalances = cloneItem(snapshot.walletBalances);
      }
      if (snapshot.walletCurrencyCode) {
        state.walletCurrencyCode = String(
          snapshot.walletCurrencyCode,
        ).toLowerCase();
      }
      state.selectedBuyIds = Array.isArray(snapshot.selectedBuyIds)
        ? [...snapshot.selectedBuyIds]
        : [];
      state.selectedSellIds = Array.isArray(snapshot.selectedSellIds)
        ? [...snapshot.selectedSellIds]
        : [];
      state.selectedBuyQuantities = {
        ...(snapshot.selectedBuyQuantities || {}),
      };
      state.selectedSellQuantities = {
        ...(snapshot.selectedSellQuantities || {}),
      };
      touchTradeCacheState(state);
    },
    addInventoryItem(state, item) {
      state.inventoryItems.push(
        normalizeLegacyInventoryRecord(cloneItem(item)),
      );
      touchTradeCacheState(state);
    },
    addInventoryStackItem(state, item) {
      if (!item) {
        return;
      }
      const templateId = resolveTemplateId(item);
      if (!Number.isFinite(templateId)) {
        state.inventoryItems.push(
          normalizeLegacyInventoryRecord(cloneItem(item)),
        );
        touchTradeCacheState(state);
        return;
      }
      const qty = Math.max(1, toNonNegativeNumber(item.QUANTITY, 1));
      const stackIndex = state.inventoryItems.findIndex(
        (entry) =>
          Number(resolveTemplateId(entry)) === Number(templateId) &&
          String(entry.OWNER_OPT || "DEFAULT") ===
            String(item.OWNER_OPT || "DEFAULT"),
      );
      if (stackIndex < 0) {
        state.inventoryItems.push({
          ...normalizeLegacyInventoryRecord(cloneItem(item)),
          INV_ID: templateId,
          QUANTITY: qty,
        });
        touchTradeCacheState(state);
        return;
      }
      const current = Number(state.inventoryItems[stackIndex].QUANTITY);
      state.inventoryItems[stackIndex] = {
        ...state.inventoryItems[stackIndex],
        QUANTITY: (Number.isFinite(current) ? current : 1) + qty,
      };
      touchTradeCacheState(state);
    },
    addTemplateItem(state, item) {
      state.templateItems.push(normalizeLegacyTemplateRecord(cloneItem(item)));
      touchTradeCacheState(state);
    },
    removeTemplateItem(state, templateIdRaw) {
      const templateId = Number(templateIdRaw);
      if (!Number.isFinite(templateId)) {
        return;
      }

      state.templateItems = state.templateItems.filter(
        (entry) => Number(entry.ID) !== templateId,
      );

      state.shops.forEach((shop) => {
        normalizeAssortment(shop);
        const entries = shopEntriesFor(shop);
        const filteredEntries = entries.filter(
          (entry) => Number(resolveTemplateId(entry)) !== templateId,
        );
        shop.shopEntries = filteredEntries;
        shop.itemIds = filteredEntries
          .map((entry) => resolveTemplateId(entry))
          .filter((id) => Number.isFinite(id));
        Object.keys(shop.assortment || {}).forEach((ownerKey) => {
          const source = Array.isArray(shop.assortment?.[ownerKey])
            ? shop.assortment[ownerKey]
            : [];
          shop.assortment[ownerKey] = source.filter(
            (id) => Number(id) !== templateId,
          );
        });
        shop.assortment.DEFAULT = Array.from(new Set(shop.itemIds));
        shop.items = buildShopItems(shop.shopEntries, state.templateItems);
      });

      const activeShop = state.shops.find(
        (entry) => Number(entry.id) === Number(state.activeShopId),
      );
      if (activeShop) {
        state.shopItems = activeShop.items.map(cloneItem);
      } else {
        state.shopItems = [];
      }
      touchTradeCacheState(state);
    },
    addTrashItem(state, item) {
      state.trashItems.push(normalizeLegacyTrashRecord(cloneItem(item)));
      touchTradeCacheState(state);
    },
  };
};
