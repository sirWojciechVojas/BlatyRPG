export const createMutationGroup4 = (deps) => {
  const {
    buildShopItems,
    cloneItem,
    normalizeAssortment,
    resolveTemplateId,
    touchTradeCacheState,
  } = deps;
  return {
    moveShopAssortmentItem(state, payload) {
      const shop = state.shops.find((entry) => entry.id === payload.shopId);
      if (!shop) {
        return;
      }
      normalizeAssortment(shop);
      const owners = Object.keys(shop.assortment || {});
      const itemId = Number(payload.itemId);
      if (!Number.isFinite(itemId)) {
        return;
      }
      owners.forEach((owner) => {
        shop.assortment[owner] = shop.assortment[owner].filter(
          (id) => Number(id) !== itemId,
        );
      });
      if (!shop.assortment[payload.toOwner]) {
        shop.assortment[payload.toOwner] = [];
      }
      shop.assortment[payload.toOwner].push(itemId);
      shop.assortment[payload.toOwner] = Array.from(
        new Set(shop.assortment[payload.toOwner]),
      );
      shop.itemIds = [...shop.assortment.DEFAULT];
      shop.shopEntries = [...shop.itemIds];
      shop.items = buildShopItems(shop.shopEntries, state.templateItems);
      if (state.activeShopId === shop.id) {
        state.shopItems = shop.items.map(cloneItem);
        state.shopName = shop.name;
      }
      touchTradeCacheState(state);
    },
    consumeShopSelection(state, selections) {
      const normalizedSelections = (selections || [])
        .map((entry) => {
          if (entry && typeof entry === "object") {
            return {
              id: Number(entry.id),
              quantity: Math.max(1, Number(entry.quantity || 1)),
              sourceIds: Array.isArray(entry.sourceIds)
                ? entry.sourceIds.map(Number).filter(Number.isFinite)
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
          ? activeShop.items
          : Array.isArray(activeShop.itemIds)
            ? activeShop.itemIds
            : [];

      normalizedSelections.forEach(
        ({ id: selectedId, quantity: selectedQty, sourceIds }) => {
          const itemIndex = state.shopItems.findIndex(
            (entry) => Number(entry.ID) === selectedId,
          );
          if (itemIndex < 0) {
            return;
          }
          const selectedItem = state.shopItems[itemIndex];
          const templateId = resolveTemplateId(selectedItem);
          if (sourceIds.length > 1) {
            let remaining = selectedQty;
            sourceIds.forEach((sourceId) => {
              if (remaining <= 0) {
                return;
              }
              const index = state.shopItems.findIndex(
                (entry) => Number(entry.ID) === sourceId,
              );
              if (index < 0) {
                return;
              }
              const entry = state.shopItems[index];
              const quantity = Number(entry.QUANTITY);
              const available = Number.isFinite(quantity)
                ? Math.max(1, quantity)
                : 1;
              const consumed = Math.min(remaining, available);
              if (available <= consumed) {
                state.shopItems.splice(index, 1);
              } else {
                state.shopItems[index] = {
                  ...entry,
                  QUANTITY: available - consumed,
                };
              }
              remaining -= consumed;
            });
          } else {
            const quantity =
              selectedItem.QUANTITY === null
                ? Number.NaN
                : Number(selectedItem.QUANTITY);
            const hasQuantity = Number.isFinite(quantity);

            const qtyToConsume = !hasQuantity
              ? 1
              : Math.max(1, Math.min(selectedQty, quantity));
            if (!hasQuantity || quantity <= qtyToConsume) {
              state.shopItems.splice(itemIndex, 1);
            } else {
              state.shopItems[itemIndex] = {
                ...selectedItem,
                QUANTITY: quantity - qtyToConsume,
              };
            }
          }

          let sourceQuantityRemaining = selectedQty;
          while (sourceQuantityRemaining > 0) {
            const sourceIndex = entries.findIndex((entry) => {
              if (sourceIds.length) {
                return sourceIds.includes(Number(entry?.ID ?? entry));
              }
              const entryTemplateId = resolveTemplateId(entry);
              return Number(entryTemplateId) === Number(templateId);
            });
            if (sourceIndex < 0) {
              break;
            }
            const sourceEntry = entries[sourceIndex];
            if (!sourceEntry || typeof sourceEntry !== "object") {
              entries.splice(sourceIndex, 1);
              sourceQuantityRemaining -= 1;
              continue;
            }
            const sourceQty = Number(sourceEntry.QUANTITY);
            const available = Number.isFinite(sourceQty)
              ? Math.max(1, sourceQty)
              : 1;
            const consumed = Math.min(sourceQuantityRemaining, available);
            if (available <= consumed) {
              entries.splice(sourceIndex, 1);
            } else {
              entries[sourceIndex] = {
                ...sourceEntry,
                QUANTITY: available - consumed,
              };
            }
            sourceQuantityRemaining -= consumed;
          }
        },
      );

      activeShop.shopEntries = entries;
      activeShop.items = state.shopItems.map(cloneItem);
      activeShop.itemIds = entries
        .map((entry) => resolveTemplateId(entry))
        .filter((id) => Number.isFinite(id));
      activeShop.assortment = activeShop.assortment || {};
      activeShop.assortment.DEFAULT = [...activeShop.itemIds];
      touchTradeCacheState(state);
    },
    toggleBuySelection(state, id) {
      const index = state.selectedBuyIds.indexOf(id);
      if (index >= 0) {
        state.selectedBuyIds.splice(index, 1);
        if (
          Object.prototype.hasOwnProperty.call(state.selectedBuyQuantities, id)
        ) {
          const next = { ...state.selectedBuyQuantities };
          delete next[id];
          state.selectedBuyQuantities = next;
        }
      } else {
        state.selectedBuyIds.push(id);
        state.selectedBuyQuantities = {
          ...state.selectedBuyQuantities,
          [id]: 1,
        };
      }
    },
    setBuySelectionQuantity(state, payload) {
      const id = Number(payload?.id);
      if (!Number.isFinite(id)) {
        return;
      }
      const max = Math.max(1, Number(payload?.max || 1));
      const raw = Number(payload?.quantity);
      const nextQty = Number.isFinite(raw)
        ? Math.max(1, Math.min(max, Math.round(raw)))
        : 1;
      state.selectedBuyQuantities = {
        ...state.selectedBuyQuantities,
        [id]: nextQty,
      };
      if (!state.selectedBuyIds.includes(id)) {
        state.selectedBuyIds.push(id);
      }
    },
    toggleSellSelection(state, id) {
      const index = state.selectedSellIds.indexOf(id);
      if (index >= 0) {
        state.selectedSellIds.splice(index, 1);
        if (
          Object.prototype.hasOwnProperty.call(state.selectedSellQuantities, id)
        ) {
          const next = { ...state.selectedSellQuantities };
          delete next[id];
          state.selectedSellQuantities = next;
        }
      } else {
        state.selectedSellIds.push(id);
        state.selectedSellQuantities = {
          ...state.selectedSellQuantities,
          [id]: 1,
        };
      }
    },
  };
};
