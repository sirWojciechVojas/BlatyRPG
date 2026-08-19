export const createMutationGroup3 = (deps) => {
  const {
    OWNER_CODES,
    SLOT_CODES,
    buildEditorStateFromProfile,
    buildShopEntryFromSuggestion,
    buildShopItems,
    cloneItem,
    createDefaultShopProfile,
    defaultShopkeeperLabel,
    findById,
    isPersonalizedShopEntry,
    nextTemplateId,
    normalizeAssortment,
    normalizeLegacyTemplateRecord,
    normalizeShopProfile,
    resolveItemPlace,
    resolveSuggestionVariant,
    resolveTemplateId,
    shopEntriesFor,
    toNonNegativeNumber,
    touchTradeCacheState,
  } = deps;
  return {
    applyShopSuggestions(state, payload) {
      const shopId = Number(payload?.shopId ?? state.activeShopId);
      const shop = state.shops.find((entry) => Number(entry.id) === shopId);
      if (!shop) {
        return;
      }
      const suggestions = Array.isArray(payload?.suggestions)
        ? payload.suggestions
        : [];
      if (!suggestions.length) {
        return;
      }
      normalizeAssortment(shop);
      const entries = Array.isArray(shop.shopEntries)
        ? cloneItem(shop.shopEntries)
        : [];
      suggestions.forEach((suggestion) => {
        let templateId = Number(suggestion?.templateId);
        if (
          suggestion?.action === "create_draft" &&
          suggestion?.draftTemplate &&
          !Number.isFinite(templateId)
        ) {
          const draftId = Number(suggestion.draftTemplate.ID);
          const finalId =
            Number.isFinite(draftId) && draftId > 0
              ? draftId
              : nextTemplateId(state.templateItems);
          const exists = state.templateItems.some(
            (entry) => Number(entry.ID) === Number(finalId),
          );
          const draftTemplate = {
            ...suggestion.draftTemplate,
            ID: exists ? nextTemplateId(state.templateItems) : finalId,
            DRAFT: true,
          };
          state.templateItems.push(
            normalizeLegacyTemplateRecord(draftTemplate),
          );
          templateId = Number(draftTemplate.ID);
        }
        if (!Number.isFinite(templateId)) {
          return;
        }
        const selectedVariant =
          suggestion?.selectedVariant ||
          suggestion?.personalizedVariant ||
          (suggestion?.variantId
            ? resolveSuggestionVariant(suggestion, suggestion?.variantId)
            : null);
        if (selectedVariant) {
          entries.push(
            buildShopEntryFromSuggestion(
              state,
              suggestion,
              templateId,
              selectedVariant,
            ),
          );
          return;
        }
        const qty = Math.max(1, Number(suggestion?.quantity || 1));
        const existingIndex = entries.findIndex((row) => {
          if (!row || typeof row !== "object") {
            return Number(resolveTemplateId(row)) === Number(templateId);
          }
          const rowTemplateId = Number(resolveTemplateId(row));
          if (rowTemplateId !== Number(templateId)) {
            return false;
          }
          const template = findById(state.templateItems, rowTemplateId) || {};
          return !isPersonalizedShopEntry(row, template);
        });
        if (existingIndex < 0) {
          entries.push(
            buildShopEntryFromSuggestion(state, suggestion, templateId),
          );
          return;
        }
        const current = entries[existingIndex];
        const currentQty = Number(current?.QUANTITY);
        entries[existingIndex] = {
          ...current,
          QUANTITY: (Number.isFinite(currentQty) ? currentQty : 0) + qty,
        };
      });
      shop.shopEntries = entries;
      shop.itemIds = entries
        .map((entry) => resolveTemplateId(entry))
        .filter((id) => Number.isFinite(id));
      shop.items = buildShopItems(entries, state.templateItems);
      if (Number(state.activeShopId) === Number(shop.id)) {
        state.shopItems = shop.items.map(cloneItem);
        state.shopName = shop.name;
      }
      touchTradeCacheState(state);
    },
    setShopActiveFlag(state, payload = {}) {
      const shopId = Number(payload?.shopId);
      if (!Number.isFinite(shopId)) {
        return;
      }
      const shop = state.shops.find((entry) => Number(entry.id) === shopId);
      if (!shop) {
        return;
      }
      shop.isActive = payload?.isActive !== false;
      touchTradeCacheState(state);
    },
    setActiveShop(state, shopId) {
      const normalizedShopId = Number(shopId);
      if (!Number.isFinite(normalizedShopId)) {
        return;
      }
      const shop = state.shops.find(
        (entry) => Number(entry.id) === normalizedShopId,
      );
      if (!shop) {
        return;
      }
      normalizeAssortment(shop);
      state.activeShopId = shop.id;
      state.shopName = shop.name;
      shop.items = buildShopItems(shopEntriesFor(shop), state.templateItems);
      state.shopItems = shop.items.map(cloneItem);
      const existingProfile =
        state.shopProfiles?.[Number(shop.id)] || createDefaultShopProfile(shop);
      const normalizedProfile = normalizeShopProfile(existingProfile, shop);
      state.shopProfiles = {
        ...state.shopProfiles,
        [Number(shop.id)]: normalizedProfile,
      };
      state.shopEditorState = buildEditorStateFromProfile(
        normalizedProfile,
        shop,
        state.shopEditorState,
      );
      state.shopSuggestions = [];
      state.shopTemplateRecommendations = [];
      touchTradeCacheState(state);
    },
    setShopAssortment(state, payload) {
      const shop = state.shops.find((entry) => entry.id === payload.shopId);
      if (!shop) {
        return;
      }
      normalizeAssortment(shop);
      const hasShopEntries = Array.isArray(payload.shopEntries);
      if (hasShopEntries) {
        const normalizedEntries = payload.shopEntries
          .map((entry) => {
            if (!entry || typeof entry !== "object") {
              return null;
            }
            const templateId = resolveTemplateId(entry);
            if (!Number.isFinite(templateId)) {
              return null;
            }
            const quantity = toNonNegativeNumber(entry.QUANTITY, 1);
            if (quantity <= 0) {
              return null;
            }
            return {
              INV_ID: templateId,
              ITEM_PLACE: resolveItemPlace(entry, SLOT_CODES.STOISKO),
              SLOT: resolveItemPlace(entry, SLOT_CODES.STOISKO),
              PERSONAL_PSEU:
                entry.PERSONAL_PSEU ||
                entry.NAME ||
                findById(state.templateItems, templateId)?.NAME ||
                defaultShopkeeperLabel(),
              PERSONAL_DESC: entry.PERSONAL_DESC || "",
              PERSONAL_COST: toNonNegativeNumber(entry.PERSONAL_COST, 0),
              QUANTITY: quantity,
              OWNER_OPT: entry.OWNER_OPT || OWNER_CODES.DEFAULT,
            };
          })
          .filter(Boolean);
        shop.shopEntries = normalizedEntries;
        const itemIds = normalizedEntries
          .map((entry) => resolveTemplateId(entry))
          .filter((id) => Number.isFinite(id));
        shop.itemIds = [...itemIds];
        shop.assortment.DEFAULT = Array.from(new Set(itemIds));
      } else {
        const uniqueIds = Array.from(
          new Set(
            (payload.itemIds || [])
              .map((id) => Number(id))
              .filter((id) => Number.isFinite(id)),
          ),
        );
        shop.assortment.DEFAULT = uniqueIds;
        shop.itemIds = [...uniqueIds];
        shop.shopEntries = [...uniqueIds];
      }
      shop.items = buildShopItems(shop.shopEntries, state.templateItems);
      if (state.activeShopId === shop.id) {
        state.shopItems = shop.items.map(cloneItem);
        state.shopName = shop.name;
      }
      touchTradeCacheState(state);
    },
  };
};
