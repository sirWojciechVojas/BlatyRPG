export const createMutationGroup6 = (deps) => {
  const {
    OWNER_CODES,
    SLOT_CODES,
    buildShopEntryFromInventoryItem,
    buildShopItems,
    cloneItem,
    defaultShopkeeperLabel,
    findById,
    normalizeLegacyInventoryRecord,
    normalizeLegacyTemplateRecord,
    normalizeLegacyTrashRecord,
    resolveTemplateId,
    shopEntriesFor,
    touchTradeCacheState,
  } = deps;
  return {
    updateTemplateItem(state, item) {
      const index = state.templateItems.findIndex(
        (entry) => Number(entry.ID) === Number(item.ID),
      );
      if (index >= 0) {
        state.templateItems.splice(
          index,
          1,
          normalizeLegacyTemplateRecord(cloneItem(item)),
        );
        const templateId = Number(item.ID);
        state.shops.forEach((shop) => {
          const entries = shopEntriesFor(shop);
          const includesTemplate = entries.some(
            (entry) => Number(resolveTemplateId(entry)) === templateId,
          );
          if (includesTemplate) {
            shop.items = buildShopItems(entries, state.templateItems);
          }
        });
        const activeShop = state.shops.find(
          (shop) => Number(shop.id) === Number(state.activeShopId),
        );
        if (activeShop) {
          activeShop.items = buildShopItems(
            shopEntriesFor(activeShop),
            state.templateItems,
          );
          state.shopItems = activeShop.items.map(cloneItem);
        }
        touchTradeCacheState(state);
      }
    },
    updateInventoryItem(state, item) {
      const index = state.inventoryItems.findIndex(
        (entry) => Number(entry.ID) === Number(item.ID),
      );
      if (index >= 0) {
        state.inventoryItems.splice(
          index,
          1,
          normalizeLegacyInventoryRecord(cloneItem(item)),
        );
        touchTradeCacheState(state);
      }
    },
    updateTrashItem(state, item) {
      const index = state.trashItems.findIndex(
        (entry) => Number(entry.ID) === Number(item.ID),
      );
      if (index >= 0) {
        state.trashItems.splice(
          index,
          1,
          normalizeLegacyTrashRecord(cloneItem(item)),
        );
        touchTradeCacheState(state);
      }
    },
    removeInventoryItems(state, ids) {
      state.inventoryItems = state.inventoryItems.filter(
        (item) => !ids.includes(item.ID),
      );
      touchTradeCacheState(state);
    },
    sellInventorySelectionToActiveShop(state, selections) {
      const normalizedSelections = (selections || [])
        .map((entry) => {
          if (entry && typeof entry === "object") {
            return {
              id: Number(entry.id),
              quantity: Math.max(1, Number(entry.quantity || 1)),
              sourceIds: Array.isArray(entry.item?.AGGREGATED_ITEM_IDS)
                ? entry.item.AGGREGATED_ITEM_IDS.map(Number).filter(
                    Number.isFinite,
                  )
                : [],
            };
          }
          return { id: Number(entry), quantity: 1, sourceIds: [] };
        })
        .filter(
          (entry) =>
            Number.isFinite(entry.id) && Number.isFinite(entry.quantity),
        );
      if (!normalizedSelections.length) {
        return;
      }
      const activeShop = state.shops.find(
        (entry) => Number(entry.id) === Number(state.activeShopId),
      );
      if (!activeShop) {
        return;
      }

      const entries = Array.isArray(activeShop.shopEntries)
        ? activeShop.shopEntries
        : Array.isArray(activeShop.items)
          ? cloneItem(activeShop.items)
          : Array.isArray(activeShop.itemIds)
            ? [...activeShop.itemIds]
            : [];

      normalizedSelections.forEach(
        ({ id: selectedId, quantity: selectedQty, sourceIds }) => {
          const inventoryIndex = state.inventoryItems.findIndex(
            (entry) => Number(entry.ID) === selectedId,
          );
          if (inventoryIndex < 0) {
            return;
          }
          const inventoryItem = state.inventoryItems[inventoryIndex];
          const templateId = resolveTemplateId(inventoryItem);
          if (!Number.isFinite(templateId)) {
            return;
          }

          let remaining = selectedQty;
          const idsToConsume = sourceIds.length ? sourceIds : [selectedId];
          idsToConsume.forEach((sourceId) => {
            if (remaining <= 0) {
              return;
            }
            const index = state.inventoryItems.findIndex(
              (entry) => Number(entry.ID) === sourceId,
            );
            if (index < 0) {
              return;
            }
            const entry = state.inventoryItems[index];
            const quantity = Number(entry.QUANTITY);
            const available = Number.isFinite(quantity)
              ? Math.max(1, quantity)
              : 1;
            const consumed = Math.min(remaining, available);
            if (available <= consumed) {
              state.inventoryItems.splice(index, 1);
            } else {
              state.inventoryItems[index] = {
                ...entry,
                QUANTITY: available - consumed,
              };
            }
            remaining -= consumed;
          });

          const qtyToSell = Math.max(1, selectedQty - remaining);

          const shopEntryIndex = entries.findIndex(
            (entry) => Number(resolveTemplateId(entry)) === Number(templateId),
          );
          if (shopEntryIndex < 0) {
            entries.push({
              ...buildShopEntryFromInventoryItem(
                inventoryItem,
                state.templateItems,
              ),
              QUANTITY: qtyToSell,
            });
            return;
          }
          const currentEntry = entries[shopEntryIndex];
          if (!currentEntry || typeof currentEntry !== "object") {
            entries[shopEntryIndex] = {
              INV_ID: templateId,
              QUANTITY: 2,
              ITEM_PLACE: SLOT_CODES.STOISKO,
              SLOT: SLOT_CODES.STOISKO,
              PERSONAL_PSEU:
                inventoryItem.NAME ||
                findById(state.templateItems, templateId)?.NAME ||
                defaultShopkeeperLabel(),
              PERSONAL_DESC: "",
              PERSONAL_COST: 0,
              OWNER_OPT: OWNER_CODES.DEFAULT,
            };
            return;
          }
          const currentQty = Number(currentEntry.QUANTITY);
          entries[shopEntryIndex] = {
            ...currentEntry,
            QUANTITY:
              (Number.isFinite(currentQty) ? currentQty : 1) + qtyToSell,
          };
        },
      );

      activeShop.shopEntries = entries;
      activeShop.items = buildShopItems(entries, state.templateItems);
      activeShop.itemIds = entries
        .map((entry) => resolveTemplateId(entry))
        .filter((id) => Number.isFinite(id));
      activeShop.assortment = activeShop.assortment || {};
      activeShop.assortment.DEFAULT = [...activeShop.itemIds];
      state.shopItems = activeShop.items.map(cloneItem);
      touchTradeCacheState(state);
    },
    removeTrashItem(state, id) {
      state.trashItems = state.trashItems.filter(
        (item) => Number(item.ID) !== Number(id),
      );
      touchTradeCacheState(state);
    },
  };
};
