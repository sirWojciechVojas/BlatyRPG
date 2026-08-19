export const createMutationGroup2 = (deps) => {
  const {
    buildEditorStateFromProfile,
    buildShopItems,
    cloneItem,
    createDefaultShopProfile,
    isShopActive,
    newShopNameWithId,
    nextShopId,
    nextTemplateId,
    normalizeAssortment,
    normalizeLegacyTemplateRecord,
    normalizeShopProfile,
    shopEntriesFor,
    touchTradeCacheState,
  } = deps;
  return {
    createOrUpdateShopProfile(state, payload) {
      if (!payload || !Number.isFinite(Number(payload.shopId))) {
        return;
      }
      const shop = state.shops.find(
        (entry) => Number(entry.id) === Number(payload.shopId),
      );
      const normalized = normalizeShopProfile(payload, shop);
      state.shopProfiles = {
        ...state.shopProfiles,
        [normalized.shopId]: normalized,
      };
      if (!shop) {
        return;
      }
      if (normalized.signboardName) {
        shop.name = normalized.signboardName;
      }
      if (normalized.ownerCode) {
        shop.ownerCode = normalized.ownerCode;
      }
      if (normalized.ownerName) {
        shop.ownerName = normalized.ownerName;
      }
      if (Number(state.activeShopId) === Number(shop.id)) {
        state.shopName = shop.name;
      }
      touchTradeCacheState(state);
    },
    createShop(state, payload = {}) {
      const shopId = nextShopId(state.shops);
      const candidateName = String(payload?.name || "").trim();
      const shopName = candidateName || newShopNameWithId(shopId);
      const ownerCode = String(payload?.ownerCode || "BG1");
      const ownerName = String(payload?.ownerName || "");
      const shop = {
        id: shopId,
        name: shopName,
        ownerCode,
        ownerName,
        isActive: true,
        shopEntries: [],
        items: [],
        itemIds: [],
      };
      normalizeAssortment(shop);
      shop.items = buildShopItems(shop.shopEntries, state.templateItems);
      state.shops.push(shop);

      const profile = normalizeShopProfile(
        {
          ...createDefaultShopProfile(shop),
          ...(payload?.profile || {}),
          shopId,
          typeId: payload?.typeId || payload?.profile?.typeId || "",
          signboardName: shopName,
          ownerCode,
          ownerName,
        },
        shop,
      );
      state.shopProfiles = {
        ...state.shopProfiles,
        [shopId]: profile,
      };
      state.activeShopId = shopId;
      state.shopName = shopName;
      state.shopItems = shop.items.map(cloneItem);
      state.shopEditorState = buildEditorStateFromProfile(
        profile,
        shop,
        state.shopEditorState,
      );
      state.shopSuggestions = [];
      state.shopTemplateRecommendations = [];
      touchTradeCacheState(state);
    },
    removeShop(state, payload = {}) {
      const shopId = Number(payload?.shopId ?? payload);
      if (!Number.isFinite(shopId) || state.shops.length <= 1) {
        return;
      }
      const index = state.shops.findIndex(
        (entry) => Number(entry.id) === Number(shopId),
      );
      if (index < 0) {
        return;
      }
      const wasActive = Number(state.activeShopId) === Number(shopId);
      state.shops.splice(index, 1);
      const nextProfiles = { ...(state.shopProfiles || {}) };
      delete nextProfiles[Number(shopId)];
      state.shopProfiles = nextProfiles;
      state.shopSuggestions = [];
      state.shopTemplateRecommendations = [];
      state.shopEditorState = {
        ...state.shopEditorState,
        selectedSuggestionIds: [],
      };

      const activeStillExists = state.shops.some(
        (shop) => Number(shop.id) === Number(state.activeShopId),
      );
      if (!wasActive && activeStillExists) {
        touchTradeCacheState(state);
        return;
      }
      const fallbackShop =
        state.shops.find((entry) => isShopActive(entry)) ||
        state.shops[index] ||
        state.shops[index - 1] ||
        state.shops[0] ||
        null;
      if (!fallbackShop) {
        state.activeShopId = null;
        state.shopName = "";
        state.shopItems = [];
        touchTradeCacheState(state);
        return;
      }
      normalizeAssortment(fallbackShop);
      fallbackShop.items = buildShopItems(
        shopEntriesFor(fallbackShop),
        state.templateItems,
      );
      state.activeShopId = fallbackShop.id;
      state.shopName = fallbackShop.name;
      state.shopItems = fallbackShop.items.map(cloneItem);
      const existingProfile =
        state.shopProfiles?.[Number(fallbackShop.id)] ||
        createDefaultShopProfile(fallbackShop);
      const normalizedProfile = normalizeShopProfile(
        existingProfile,
        fallbackShop,
      );
      state.shopProfiles = {
        ...state.shopProfiles,
        [Number(fallbackShop.id)]: normalizedProfile,
      };
      state.shopEditorState = buildEditorStateFromProfile(
        normalizedProfile,
        fallbackShop,
        state.shopEditorState,
      );
      touchTradeCacheState(state);
    },
    createDraftTemplate(state, payload) {
      if (!payload || !payload.NAME) {
        return;
      }
      const id = Number(payload.ID);
      const safeId =
        Number.isFinite(id) && id > 0
          ? id
          : nextTemplateId(state.templateItems);
      const exists = state.templateItems.some(
        (entry) => Number(entry.ID) === Number(safeId),
      );
      const next = {
        ...payload,
        ID: exists ? nextTemplateId(state.templateItems) : safeId,
        DRAFT: true,
      };
      state.templateItems.push(normalizeLegacyTemplateRecord(next));
      touchTradeCacheState(state);
    },
  };
};
