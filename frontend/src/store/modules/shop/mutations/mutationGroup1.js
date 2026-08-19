export const createMutationGroup1 = (deps) => {
  const {
    GM_MODES,
    buildShopItems,
    cloneItem,
    createDefaultShopProfile,
    createCatalogMutations,
    createContainerMutations,
    createLedgerMutations,
    createSessionMutations,
    createTradeMutations,
    isShopActive,
    normalizeAssortment,
    normalizeLegacyInventoryRecord,
    normalizeLegacyTemplateRecord,
    normalizeLegacyTrashRecord,
    normalizeShopPricingConfig,
    normalizeShopProfile,
    normalizeSuggestionCollection,
    shopEntriesFor,
    touchTradeCacheState,
  } = deps;
  return {
    ...createSessionMutations({
      touchTradeCacheState,
      gmModes: GM_MODES,
    }),
    ...createTradeMutations(),
    ...createContainerMutations(),
    ...createCatalogMutations({ cloneItem, normalizeLegacyTemplateRecord }),
    ...createLedgerMutations({ cloneItem }),
    setTradingData(state, payload) {
      const normalizedShops = Array.isArray(payload.shops)
        ? payload.shops.map((shop) => ({
            ...shop,
            isActive: isShopActive(shop),
          }))
        : [];
      state.shops = normalizedShops;
      const requestedActiveShopId = Number(payload.activeShopId);
      const resolvedActiveShop =
        normalizedShops.find(
          (shop) => Number(shop.id) === Number(requestedActiveShopId),
        ) ||
        normalizedShops.find((shop) => isShopActive(shop)) ||
        normalizedShops[0] ||
        null;
      state.activeShopId = resolvedActiveShop?.id ?? null;
      state.shopName =
        payload.shopName || resolvedActiveShop?.name || state.shopName;
      state.templateItems = Array.isArray(payload.templateItems)
        ? payload.templateItems.map((item) =>
            normalizeLegacyTemplateRecord(item),
          )
        : [];
      state.inventoryItems = Array.isArray(payload.inventoryItems)
        ? payload.inventoryItems.map((item) =>
            normalizeLegacyInventoryRecord(item),
          )
        : [];
      state.trashItems = Array.isArray(payload.trashItems)
        ? payload.trashItems.map((item) => normalizeLegacyTrashRecord(item))
        : [];
      state.catalogNodes = payload.catalogNodes || state.catalogNodes || [];
      state.itemDictionaries = {
        icon_categories: Array.isArray(
          payload.itemDictionaries?.icon_categories,
        )
          ? payload.itemDictionaries.icon_categories
          : [],
        icon_subcategories: Array.isArray(
          payload.itemDictionaries?.icon_subcategories,
        )
          ? payload.itemDictionaries.icon_subcategories
          : [],
        classes: Array.isArray(payload.itemDictionaries?.classes)
          ? payload.itemDictionaries.classes
          : [],
        genres: Array.isArray(payload.itemDictionaries?.genres)
          ? payload.itemDictionaries.genres
          : [],
        attributes: Array.isArray(payload.itemDictionaries?.attributes)
          ? payload.itemDictionaries.attributes
          : [],
      };
      state.currencyDefinitions = {
        systemCode:
          payload.currencyDefinitions?.systemCode ||
          state.context?.systemCode ||
          "generic",
        defaultCurrencyCode:
          payload.currencyDefinitions?.defaultCurrencyCode || "generic",
        currencies: Array.isArray(payload.currencyDefinitions?.currencies)
          ? payload.currencyDefinitions.currencies
          : [],
      };
      state.mechanics = {
        ...state.mechanics,
        ...(payload.mechanics || {}),
        encumbrance: {
          ...state.mechanics.encumbrance,
          ...(payload.mechanics?.encumbrance || {}),
          presets: Array.isArray(payload.mechanics?.encumbrance?.presets)
            ? payload.mechanics.encumbrance.presets
            : [],
        },
      };
      state.allItemInstances = Array.isArray(payload.allItemInstances)
        ? payload.allItemInstances
        : [];
      state.shopTypes = Array.isArray(payload.shopTypes)
        ? payload.shopTypes
        : state.shopTypes;
      state.worldProfiles = payload.worldProfiles || state.worldProfiles || [];
      const normalizedProfiles = Object.entries(
        payload.shopProfiles || state.shopProfiles || {},
      ).reduce((acc, [shopId, profile]) => {
        const shop =
          normalizedShops.find(
            (entry) => Number(entry.id) === Number(shopId),
          ) || null;
        acc[Number(shopId)] = normalizeShopProfile(profile, shop || {});
        return acc;
      }, {});
      normalizedShops.forEach((shop) => {
        const shopId = Number(shop.id);
        if (!normalizedProfiles[shopId]) {
          normalizedProfiles[shopId] = normalizeShopProfile(
            createDefaultShopProfile(shop),
            shop,
          );
        }
      });
      state.shopProfiles = normalizedProfiles;
      if (resolvedActiveShop) {
        normalizeAssortment(resolvedActiveShop);
        resolvedActiveShop.items = buildShopItems(
          shopEntriesFor(resolvedActiveShop),
          state.templateItems,
        );
        state.shopItems = resolvedActiveShop.items.map(cloneItem);
      } else {
        state.shopItems = [];
      }
      if (Number.isFinite(Number(payload.walletBrass))) {
        state.bgWalletBrass = Math.max(0, Number(payload.walletBrass));
      }
      if (
        Array.isArray(payload.walletBalances) ||
        (payload.walletBalances && typeof payload.walletBalances === "object")
      ) {
        const normalizedWalletBalances = Array.isArray(payload.walletBalances)
          ? payload.walletBalances.reduce((acc, entry) => {
              const currencyCode = String(
                entry?.currencyCode || "",
              ).toLowerCase();
              if (currencyCode && Number.isFinite(Number(entry?.balance))) {
                acc[currencyCode] = Math.max(0, Number(entry.balance));
              }
              return acc;
            }, {})
          : Object.entries(payload.walletBalances).reduce(
              (acc, [currencyCode, balance]) => {
                if (currencyCode && Number.isFinite(Number(balance))) {
                  acc[String(currencyCode).toLowerCase()] = Math.max(
                    0,
                    Number(balance),
                  );
                }
                return acc;
              },
              {},
            );
        state.walletBalances = normalizedWalletBalances;
        state.walletCurrencyCode = String(
          payload.walletCurrencyCode ||
            state.shopProfiles?.[Number(state.activeShopId)]?.pricingConfig
              ?.currencyPolicy?.settlementCurrencyCode ||
            state.currencyDefinitions?.defaultCurrencyCode ||
            "generic",
        ).toLowerCase();
        state.bgWalletBrass = Number(
          normalizedWalletBalances[state.walletCurrencyCode] || 0,
        );
      }
      state.shopSuggestions = [];
      state.shopTemplateRecommendations = [];
    },
    setShopSuggestions(state, suggestions) {
      state.shopSuggestions = normalizeSuggestionCollection(suggestions);
      const allowed = new Set(
        state.shopSuggestions.map((entry) => entry.suggestionId),
      );
      state.shopEditorState.selectedSuggestionIds = (
        state.shopEditorState.selectedSuggestionIds || []
      ).filter((id) => allowed.has(id));
    },
    setShopTemplateRecommendations(state, recommendations) {
      state.shopTemplateRecommendations =
        normalizeSuggestionCollection(recommendations);
    },
    setShopEditorState(state, payload) {
      const next = {
        ...state.shopEditorState,
        ...(payload || {}),
      };
      next.pricingConfig = normalizeShopPricingConfig(next.pricingConfig);
      if (
        Object.prototype.hasOwnProperty.call(next, "signboardAltNames") &&
        !Object.prototype.hasOwnProperty.call(next, "signboardAltNamesText")
      ) {
        next.signboardAltNamesText = Array.isArray(next.signboardAltNames)
          ? next.signboardAltNames.join(", ")
          : String(next.signboardAltNames || "");
      }
      if (
        Object.prototype.hasOwnProperty.call(next, "categoryTags") &&
        !Object.prototype.hasOwnProperty.call(next, "categoryTagsText")
      ) {
        next.categoryTagsText = Array.isArray(next.categoryTags)
          ? next.categoryTags.join(", ")
          : String(next.categoryTags || "");
      }
      if (!Array.isArray(next.selectedSuggestionIds)) {
        next.selectedSuggestionIds = [];
      }
      state.shopEditorState = next;
    },
    toggleShopSuggestionSelection(state, suggestionId) {
      const id = String(suggestionId || "");
      if (!id) {
        return;
      }
      const selected = new Set(
        state.shopEditorState.selectedSuggestionIds || [],
      );
      if (selected.has(id)) {
        selected.delete(id);
      } else {
        selected.add(id);
      }
      state.shopEditorState = {
        ...state.shopEditorState,
        selectedSuggestionIds: Array.from(selected),
      };
    },
  };
};
