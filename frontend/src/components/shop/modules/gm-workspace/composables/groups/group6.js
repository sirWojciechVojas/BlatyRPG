import {
  normalizeShopPricingConfig,
  createDefaultShopPricingConfig,
} from "@/lib/trade/shopPriceCalculator";
export const installWorkspaceGroup6 = (deps) => {
  let offerRefreshRequested = false;
  let offerRefreshPromise = null;

  function hasPendingSuggestionOperations() {
    return (
      deps.addingAllSuggestions.value ||
      Object.keys(deps.suggestionOperations).length > 0
    );
  }

  function flushOfferRefresh() {
    if (
      !offerRefreshRequested ||
      hasPendingSuggestionOperations() ||
      offerRefreshPromise
    ) {
      return;
    }
    offerRefreshRequested = false;
    offerRefreshPromise = Promise.resolve(
      deps.store.dispatch("shop/loadTradingData", {
        forceReload: true,
        ownerCode:
          deps.profileDraft.ownerCode ||
          deps.activeShop.value?.ownerCode ||
          "BG1",
      }),
    )
      .catch(() => null)
      .finally(() => {
        offerRefreshPromise = null;
        flushOfferRefresh();
      });
  }

  function queueOfferRefresh() {
    offerRefreshRequested = true;
    flushOfferRefresh();
  }

  function pricingConfigForDraft(value) {
    const config = normalizeShopPricingConfig(
      value || createDefaultShopPricingConfig(),
    );
    if (!config.currencyPolicy.settlementCurrencyCode) {
      config.currencyPolicy.settlementCurrencyCode = String(
        deps.defaultCurrencyCode.value || "generic",
      ).toLowerCase();
    }
    return config;
  }
  function hydrateProfile() {
    const profile =
      deps.shopState.value.shopProfiles?.[Number(deps.activeShopId.value)] ||
      {};
    Object.keys(deps.profileDraft).forEach(
      (key) => delete deps.profileDraft[key],
    );
    Object.assign(
      deps.profileDraft,
      JSON.parse(
        JSON.stringify({
          signboardName: deps.activeShop.value?.name || "",
          ownerCode:
            deps.activeShop.value?.ownerCode ||
            deps.actorOptions.value[0]?.ownerCode ||
            "BG1",
          ownerName: deps.activeShop.value?.ownerName || "",
          typeId: "",
          worldProfileId: "standard",
          locationType: "miasto",
          legalStatus: "legal",
          wealthTier: "standard",
          reputation: "neutralna",
          seasonality: "caloroczny",
          counterfeitRisk: 10,
          marketSettings: {
            demandLevel: "normal",
            availabilityBias: 0,
            buybackBudget: null,
            maxBuybackItemValue: null,
            expensiveStockLimit: null,
            localCategories: [],
            importedCategories: [],
            reputationByActor: {},
          },
          marketEvents: [],
          customPresets: { profiles: [], policies: [] },
          ...profile,
          pricingConfig: pricingConfigForDraft(profile.pricingConfig),
          signboardAltNamesText: Array.isArray(profile.signboardAltNames)
            ? profile.signboardAltNames.join(", ")
            : profile.signboardAltNamesText || "",
        }),
      ),
    );
    const legacyType = deps.typeOptions.value.find(
      (type) =>
        String(type.databaseId) === String(deps.profileDraft.typeId) &&
        String(type.id) !== String(deps.profileDraft.typeId),
    );
    if (legacyType) deps.profileDraft.typeId = legacyType.id;
    deps.activeDraft.value = deps.activeShop.value?.isActive !== false;
    deps.store.commit("shop/setFormStatus", {
      scope: "shop",
      status: "clean",
    });
  }
  function selectShop(id) {
    if (
      deps.formStatus.value.shop === "dirty" &&
      !window.confirm(deps.t("shop.workspace.unsavedQuestion"))
    )
      return;
    deps.store.commit("shop/setActiveShop", Number(id));
  }
  function markShopDirty() {
    deps.store.commit("shop/setFormStatus", {
      scope: "shop",
      status: "dirty",
    });
  }
  function markTemplateDirty() {
    deps.store.commit("shop/setFormStatus", {
      scope: "template",
      status: "dirty",
    });
  }
  async function saveProfile() {
    const validateProfile = deps.shopSubtab?.value === "profile";
    if (validateProfile && deps.profileSaveAttempted) {
      deps.profileSaveAttempted.value = true;
    }
    if (validateProfile && deps.canSaveProfile && !deps.canSaveProfile.value) {
      return null;
    }
    const saved = await deps.store.dispatch("shop/saveShopProfile", {
      ...JSON.parse(JSON.stringify(deps.profileDraft)),
      shopId: deps.activeShopId.value,
      signboardAltNames: String(deps.profileDraft.signboardAltNamesText || "")
        .split(",")
        .map((value) => value.trim())
        .filter(Boolean),
      categoryTags: deps.automaticTags.value,
    });
    if (saved && deps.profileSaveAttempted) {
      deps.profileSaveAttempted.value = false;
    }
    return saved;
  }
  async function toggleActive() {
    const result = await deps.store.dispatch("shop/updateShopActivation", {
      shopId: deps.activeShopId.value,
      isActive: deps.activeDraft.value,
    });
    if (!result?.ok) deps.activeDraft.value = !deps.activeDraft.value;
  }
  function toggleSelection(list, id) {
    const numeric = Number(id);
    const normalized =
      String(id ?? "").trim() !== "" && Number.isFinite(numeric)
        ? numeric
        : String(id);
    const index = list.findIndex(
      (entry) => String(entry) === String(normalized),
    );
    if (index >= 0) list.splice(index, 1);
    else list.push(normalized);
  }
  function showDetails(item) {
    deps.detailItem.value = item;
  }
  function activateModule(module) {
    deps.activeModule.value = module.id;
    deps.activeTab.value = module.target;
    if (module.shopSubtab) deps.shopSubtab.value = module.shopSubtab;
    if (module.warehouseTab === "items") deps.warehouseTab.value = "items";
    if (module.warehouseTab === "archive") deps.openArchive();
  }
  function activateShopSubtab(subtab) {
    deps.shopSubtab.value = subtab;
    deps.activeModule.value = subtab === "offer" ? "assortment" : "shopEditor";
  }
  function activateWarehouseItems() {
    deps.warehouseTab.value = "items";
    deps.activeModule.value = "inventory";
  }
  async function generateSuggestions() {
    deps.suggestionsOpen.value = true;
    await deps.store.dispatch("shop/generateShopSuggestions", {
      shopId: deps.activeShopId.value,
    });
    deps.shopSubtab.value = "offer";
  }
  async function toggleSuggestions() {
    deps.suggestionsOpen.value = !deps.suggestionsOpen.value;
    if (deps.suggestionsOpen.value && !deps.shopSuggestions.value.length) {
      await generateSuggestions();
    }
  }
  function suggestionName(suggestion) {
    return (
      suggestion?.displayName ||
      suggestion?.templateName ||
      suggestion?.label ||
      deps.t("shop.workspace.unnamedItem")
    );
  }
  function suggestionReason(suggestion) {
    return (
      suggestion?.recommendationReasonPl ||
      suggestion?.reason?.[0] ||
      deps.t("shop.workspace.suggestionPanel.defaultReason")
    );
  }
  function suggestionIconItem(suggestion) {
    return {
      IMG_CLASS:
        suggestion?.imgClass || suggestion?.draftTemplate?.IMG_CLASS || "v0001",
    };
  }
  function suggestionHasTemplate(suggestion) {
    return (
      Number.isFinite(Number(suggestion?.templateId)) &&
      Number(suggestion?.templateId) > 0
    );
  }
  function suggestionOperation(suggestion) {
    return deps.suggestionOperations[String(suggestion?.suggestionId || "")];
  }
  function isSuggestionBusy(suggestion) {
    return Boolean(suggestionOperation(suggestion));
  }
  async function createSuggestionTemplate(suggestion) {
    const suggestionId = String(suggestion?.suggestionId || "");
    if (
      !suggestionId ||
      suggestionHasTemplate(suggestion) ||
      isSuggestionBusy(suggestion) ||
      deps.addingAllSuggestions.value
    ) {
      return null;
    }
    deps.suggestionOperations[suggestionId] = "template";
    try {
      return await deps.store.dispatch("shop/materializeShopSuggestion", {
        shopId: deps.activeShopId.value,
        suggestionId,
        mode: "template_only",
        refresh: false,
      });
    } finally {
      delete deps.suggestionOperations[suggestionId];
      queueOfferRefresh();
    }
  }
  async function applySingleSuggestion(suggestion) {
    const suggestionId = String(suggestion?.suggestionId || "");
    if (
      !suggestionId ||
      isSuggestionBusy(suggestion) ||
      deps.addingAllSuggestions.value
    ) {
      return null;
    }
    deps.suggestionOperations[suggestionId] = "item";
    try {
      return await deps.store.dispatch("shop/materializeShopSuggestion", {
        shopId: deps.activeShopId.value,
        suggestionId,
        mode: suggestionHasTemplate(suggestion)
          ? "item_only"
          : "template_plus_item",
        refresh: false,
      });
    } finally {
      delete deps.suggestionOperations[suggestionId];
      queueOfferRefresh();
    }
  }
  async function applyAllSuggestions() {
    const suggestionIds = deps.shopSuggestions.value
      .map((suggestion) => String(suggestion?.suggestionId || ""))
      .filter(Boolean);
    if (
      !suggestionIds.length ||
      deps.addingAllSuggestions.value ||
      Object.keys(deps.suggestionOperations).length
    ) {
      return 0;
    }
    deps.addingAllSuggestions.value = true;
    try {
      return await deps.store.dispatch("shop/applyShopSuggestions", {
        shopId: deps.activeShopId.value,
        suggestionIds,
        replaceExisting: false,
        refresh: false,
      });
    } finally {
      deps.addingAllSuggestions.value = false;
      queueOfferRefresh();
    }
  }
  async function previewStock() {
    const result = await deps.store.dispatch("shop/rollShopAssortment", {
      shopId: deps.activeShopId.value,
      dryRun: true,
      clearExisting: true,
      targetInstances: 24,
      uniqueItems: 20,
    });
    deps.stockPreview.value = result?.suggestions || [];
  }
  async function applyStockPreview() {
    await deps.store.dispatch("shop/applyShopSuggestions", {
      shopId: deps.activeShopId.value,
      suggestions: deps.stockPreview.value,
      replaceExisting: true,
    });
    deps.stockPreview.value = [];
  }
  Object.assign(deps, {
    pricingConfigForDraft,
    hydrateProfile,
    selectShop,
    markShopDirty,
    markTemplateDirty,
    saveProfile,
    toggleActive,
    toggleSelection,
    showDetails,
    activateModule,
    activateShopSubtab,
    activateWarehouseItems,
    generateSuggestions,
    toggleSuggestions,
    suggestionName,
    suggestionReason,
    suggestionIconItem,
    suggestionHasTemplate,
    suggestionOperation,
    isSuggestionBusy,
    createSuggestionTemplate,
    applySingleSuggestion,
    applyAllSuggestions,
    previewStock,
    applyStockPreview,
  });
};
