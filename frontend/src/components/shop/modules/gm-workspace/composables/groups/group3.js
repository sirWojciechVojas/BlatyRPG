import { useShopEditorViewModel } from "@/components/shop/composables/useShopEditorViewModel";
import { computed } from "vue";
export const installWorkspaceGroup3 = (deps) => {
  const {
    pricingModifierOptions,
    pricingItemClassOptions,
    pricingItemGenreOptions,
    pricingCurrencyOptions,
    pricingRules,
    previewTemplateId: pricingPreviewTemplateId,
    previewTemplateOptions: pricingPreviewTemplateOptions,
    pricePreview: pricingPricePreview,
    previewQuantity: pricingPreviewQuantity,
    previewCondition: pricingPreviewCondition,
    previewReputation: pricingPreviewReputation,
    previewMode: pricingPreviewMode,
    previewTemporaryModifier: pricingPreviewTemporaryModifier,
    previewQuickMode: pricingPreviewQuickMode,
    previewLoading: pricingPreviewLoading,
    previewError: pricingPreviewError,
    updatePreviewTemplate: updatePricingPreviewTemplate,
    updatePreviewInput: updatePricingPreviewInput,
    updatePricingModifier,
    updatePricingPolicyField,
    applyPricingPreset,
    addPricingRule,
    updatePricingRule,
    removePricingRule,
    savePolicyPreset,
    applyPolicyPreset,
    removePolicyPreset,
  } = useShopEditorViewModel(deps.pricingEditorContext);
  const shopSuggestions = computed(
    () => deps.shopState.value.shopSuggestions || [],
  );
  const itemDisplayName = (item) => {
    const name = String(item?.NAME || "").trim();
    if (name) return name;
    const personalized = String(item?.PERSONAL_PSEU || "").trim();
    if (personalized && !["Sklepowy", "Shop item"].includes(personalized)) {
      return personalized;
    }
    return item?.ID ? `#${item.ID}` : deps.t("shop.workspace.unnamedItem");
  };
  const offerGroupingKey = (item) =>
    JSON.stringify([
      Number(item?.INV_ID || 0),
      itemDisplayName(item),
      String(item?.DESCRIPTION || item?.PERSONAL_DESC || ""),
      String(item?.DETAILS || ""),
      String(item?.ITEM_CLASS || ""),
      String(item?.ITEM_GENRE || ""),
      String(item?.IMG_CLASS || ""),
      Number(item?.ACTIVE_PRICE ?? item?.PERSONAL_COST ?? item?.PRIZE ?? 0),
      String(item?.CURRENCY || ""),
      Number(item?.CHARGE || 0),
      [...(item?.ATTRIBUTES || [])].map(String).sort(),
      item?.WEAPON || {},
    ]);
  const groupedOffer = computed(() => {
    const groups = new Map();
    deps.activeOffer.value.forEach((item) => {
      const key = offerGroupingKey(item);
      const instanceIds = Array.isArray(item.INSTANCE_IDS)
        ? item.INSTANCE_IDS.map(Number).filter(Number.isFinite)
        : [Number(item.ID)].filter(Number.isFinite);
      if (!groups.has(key)) {
        groups.set(key, {
          ...item,
          OFFER_KEY: `offer:${item.ID}`,
          INSTANCE_IDS: instanceIds,
          QUANTITY: Math.max(
            1,
            Number(item.QUANTITY || instanceIds.length || 1),
          ),
        });
        return;
      }
      const group = groups.get(key);
      group.INSTANCE_IDS.push(...instanceIds);
      group.QUANTITY += Math.max(
        1,
        Number(item.QUANTITY || instanceIds.length || 1),
      );
    });
    return Array.from(groups.values());
  });
  const stockDiff = computed(() => {
    const current = new Map(
      deps.activeOffer.value.map((item) => [
        String(item.INV_ID ?? item.ID),
        {
          key: String(item.INV_ID ?? item.ID),
          name: item.NAME || item.PERSONAL_PSEU || "#" + item.ID,
          quantity: item.QUANTITY,
        },
      ]),
    );
    const next = new Map(
      deps.stockPreview.value.map((item) => [
        String(item.templateId ?? item.suggestionId),
        {
          key: String(item.templateId ?? item.suggestionId),
          name: item.displayName || item.templateName || item.label,
          quantity: Number(item.quantity || 1),
        },
      ]),
    );
    const added = [];
    const changed = [];
    const removed = [];
    next.forEach((item, key) => {
      const before = current.get(key);
      if (!before) {
        added.push(item);
      } else if (Number(before.quantity) !== Number(item.quantity)) {
        changed.push({
          ...item,
          before: before.quantity ?? "∞",
          after: item.quantity,
        });
      }
    });
    current.forEach((item, key) => {
      if (!next.has(key)) removed.push(item);
    });
    return {
      added,
      changed,
      removed,
    };
  });
  const filterItems = (items, query, type) => {
    const needle = String(query || "").toLocaleLowerCase(deps.locale.value);
    return items.filter((item) => {
      const text =
        `${item.NAME || ""} ${item.PERSONAL_PSEU || ""} ${item.DESCRIPTION || ""} ${item.PERSONAL_DESC || ""}`.toLocaleLowerCase(
          deps.locale.value,
        );
      return (
        (!needle || text.includes(needle)) &&
        (!type || item.ITEM_CLASS === type)
      );
    });
  };
  const filteredOffer = computed(() =>
    filterItems(
      groupedOffer.value,
      deps.deferredOfferQuery.value,
      deps.offerType.value,
    ).sort((a, b) =>
      deps.offerSort.value === "price"
        ? Number(a.PRIZE || 0) - Number(b.PRIZE || 0)
        : deps.offerSort.value === "availability"
          ? Number(b.QUANTITY || 0) - Number(a.QUANTITY || 0)
          : String(a.NAME || a.PERSONAL_PSEU || "").localeCompare(
              String(b.NAME || b.PERSONAL_PSEU || ""),
              deps.locale.value,
            ),
    ),
  );
  const filteredOfferKeys = computed(() =>
    filteredOffer.value.map((item) => String(item.OFFER_KEY)),
  );
  const allFilteredOfferSelected = computed(
    () =>
      filteredOfferKeys.value.length > 0 &&
      filteredOfferKeys.value.every((key) =>
        deps.offerSelection.some((selected) => String(selected) === key),
      ),
  );
  const someFilteredOfferSelected = computed(() =>
    filteredOfferKeys.value.some((key) =>
      deps.offerSelection.some((selected) => String(selected) === key),
    ),
  );
  const selectedOfferQuantity = computed(() => {
    const selected = new Set(deps.offerSelection.map(String));
    return groupedOffer.value
      .filter((item) => selected.has(String(item.OFFER_KEY)))
      .reduce(
        (sum, item) =>
          sum +
          Math.max(1, Number(item.INSTANCE_IDS?.length || item.QUANTITY || 1)),
        0,
      );
  });
  const filteredTemplates = computed(() =>
    filterItems(
      deps.templateItems.value,
      deps.deferredCatalogQuery.value,
      deps.catalogType.value,
    ),
  );
  const instancesForLocationFilter = computed(() => {
    if (deps.instanceLocationFilter.value === "all")
      return deps.allItemInstances.value;
    const kindByFilter = {
      unassigned: "UNASSIGNED",
      character: "CHARACTER",
      shop: "SHOP",
      trash: "TRASH",
    };
    const kind = kindByFilter[deps.instanceLocationFilter.value];
    return deps.instanceIndexes.forLocation(kind);
  });
  const filteredInventory = computed(() => {
    const needle = String(
      deps.deferredWarehouseQuery.value || "",
    ).toLocaleLowerCase(deps.locale.value);
    return instancesForLocationFilter.value.filter(
      (item) =>
        !needle ||
        `${item.NAME || ""} ${item.PERSONAL_PSEU || ""} ${item.DESCRIPTION || ""} ${item.CONTAINER_NAME || ""} ${item.LOCATION_OWNER_NAME || ""} ${item.LOCATION_OWNER_CODE || ""} ${item.LOCATION_SHOP_NAME || ""}`
          .toLocaleLowerCase(deps.locale.value)
          .includes(needle),
    );
  });
  Object.assign(deps, {
    pricingModifierOptions,
    pricingItemClassOptions,
    pricingItemGenreOptions,
    pricingCurrencyOptions,
    pricingRules,
    pricingPreviewTemplateId,
    pricingPreviewTemplateOptions,
    pricingPricePreview,
    pricingPreviewQuantity,
    pricingPreviewCondition,
    pricingPreviewReputation,
    pricingPreviewMode,
    pricingPreviewTemporaryModifier,
    pricingPreviewQuickMode,
    pricingPreviewLoading,
    pricingPreviewError,
    updatePricingPreviewTemplate,
    updatePricingPreviewInput,
    updatePricingModifier,
    updatePricingPolicyField,
    applyPricingPreset,
    addPricingRule,
    updatePricingRule,
    removePricingRule,
    savePolicyPreset,
    applyPolicyPreset,
    removePolicyPreset,
    shopSuggestions,
    itemDisplayName,
    offerGroupingKey,
    groupedOffer,
    stockDiff,
    filterItems,
    filteredOffer,
    filteredOfferKeys,
    allFilteredOfferSelected,
    someFilteredOfferSelected,
    selectedOfferQuantity,
    filteredTemplates,
    instancesForLocationFilter,
    filteredInventory,
  });
};
