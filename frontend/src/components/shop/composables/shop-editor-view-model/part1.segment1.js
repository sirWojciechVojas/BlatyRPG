export const createEditorViewModelPart1Segment1 = (runtime) => {
  const attemptedSuggestionGeneration = runtime.ref(false);
  Object.assign(runtime, {
    attemptedSuggestionGeneration,
  });
  const t = (key, values = {}) => runtime.i18n.global.t(key, values);
  Object.assign(runtime, {
    t,
  });
  const autoTagItems = runtime.computed(() =>
    String(runtime.ctx.shopAutoTagsText || "")
      .split(/[;,|]/)
      .map((entry) => entry.trim())
      .filter(Boolean),
  );
  Object.assign(runtime, {
    autoTagItems,
  });
  const missingSuggestionFields = runtime.computed(() => {
    const missing = [];
    if (!Number.isFinite(Number(runtime.ctx.activeShopId))) {
      missing.push({
        key: "activeShopId",
        label: runtime.t("shop.shopEditor.editedShop"),
      });
    }
    if (!String(runtime.ctx.shopEditorForm?.typeId || "").trim()) {
      missing.push({
        key: "typeId",
        label: runtime.t("shop.shopEditor.shopType"),
      });
    }
    return missing;
  });
  Object.assign(runtime, {
    missingSuggestionFields,
  });
  const missingSuggestionFieldLabels = runtime.computed(() =>
    runtime.missingSuggestionFields.value.map((entry) => entry.label),
  );
  Object.assign(runtime, {
    missingSuggestionFieldLabels,
  });
  const missingSuggestionFieldSet = runtime.computed(
    () =>
      new Set(runtime.missingSuggestionFields.value.map((entry) => entry.key)),
  );
  Object.assign(runtime, {
    missingSuggestionFieldSet,
  });
  const counterfeitRisk = runtime.computed(() =>
    Math.max(
      0,
      Math.min(100, Number(runtime.ctx.shopEditorForm?.counterfeitRisk || 0)),
    ),
  );
  Object.assign(runtime, {
    counterfeitRisk,
  });
  const previewTemplateId = runtime.ref(null);
  const previewQuantity = runtime.ref(1);
  const previewCondition = runtime.ref("good");
  const previewReputation = runtime.ref("neutralna");
  const previewMode = runtime.ref("buy");
  const previewTemporaryModifier = runtime.ref(0);
  const previewQuickMode = runtime.ref(false);
  const previewLoading = runtime.ref(false);
  const previewError = runtime.ref("");
  Object.assign(runtime, {
    previewTemplateId,
    previewQuantity,
    previewCondition,
    previewReputation,
    previewMode,
    previewTemporaryModifier,
    previewQuickMode,
    previewLoading,
    previewError,
  });
  const counterfeitRiskTone = runtime.computed(() => {
    if (runtime.counterfeitRisk.value <= 25) {
      return "low";
    }
    if (runtime.counterfeitRisk.value <= 60) {
      return "medium";
    }
    return "high";
  });
  Object.assign(runtime, {
    counterfeitRiskTone,
  });
  const showValidationBanner = runtime.computed(
    () =>
      runtime.attemptedSuggestionGeneration.value &&
      runtime.missingSuggestionFieldLabels.value.length > 0,
  );
  Object.assign(runtime, {
    showValidationBanner,
  });
  const pricingModifierOptions = runtime.computed(() =>
    runtime.PRICE_MODIFIER_KEYS.map((key) => ({
      key,
      label: runtime.t(`shop.shopEditor.pricing.toggles.${key}`),
      description: runtime.t(
        `shop.shopEditor.pricing.toggleDescriptions.${key}`,
      ),
    })),
  );
  Object.assign(runtime, {
    pricingModifierOptions,
  });
  const pricingRules = runtime.computed(
    () =>
      runtime.normalizeShopPricingConfig(
        runtime.ctx.shopEditorForm?.pricingConfig ||
          runtime.ctx.activeShopProfile?.pricingConfig,
      ).rules,
  );
  Object.assign(runtime, {
    pricingRules,
  });
  const pricingCurrencyOptions = runtime.computed(() => {
    const definitions = Array.isArray(
      runtime.ctx.currencyDefinitions?.currencies,
    )
      ? runtime.ctx.currencyDefinitions.currencies
      : [];
    return definitions.map((currency) => ({
      value: String(currency?.code || ""),
      label: String(
        currency?.labelPl || currency?.labelEn || currency?.code || "",
      ),
    }));
  });
  Object.assign(runtime, {
    pricingCurrencyOptions,
  });
  const previewTemplateOptions = runtime.computed(() => {
    const templates = Array.isArray(runtime.ctx.templateItems)
      ? runtime.ctx.templateItems
      : [];
    const activeShopItems = Array.isArray(runtime.ctx.shopItems)
      ? runtime.ctx.shopItems
      : [];
    const stockedIds = new Set(
      activeShopItems
        .map((entry) => Number(entry?.INV_ID ?? entry?.ID))
        .filter((value) => Number.isFinite(value)),
    );
    return templates
      .map((template) => {
        const templateId = Number(template?.ID);
        return {
          value: templateId,
          label: String(
            template?.NAME ||
              runtime.t("shop.itemDetailDialog.fallbackItemName"),
          ).trim(),
          basePrice: Number(template?.PRIZE || 0),
          stocked: stockedIds.has(templateId),
        };
      })
      .sort((left, right) => {
        if (left.stocked !== right.stocked) {
          return left.stocked ? -1 : 1;
        }
        return left.label.localeCompare(right.label, "pl");
      });
  });
  Object.assign(runtime, {
    previewTemplateOptions,
  });
  const pricingItemClassOptions = runtime.computed(() =>
    Array.from(
      new Set(
        (Array.isArray(runtime.ctx.templateItems)
          ? runtime.ctx.templateItems
          : []
        )
          .map((template) => String(template?.ITEM_CLASS || "").trim())
          .filter(Boolean),
      ),
    ).sort((left, right) => left.localeCompare(right, "pl")),
  );
  Object.assign(runtime, {
    pricingItemClassOptions,
  });
  const pricingItemGenreOptions = runtime.computed(() =>
    Array.from(
      new Set(
        (Array.isArray(runtime.ctx.templateItems)
          ? runtime.ctx.templateItems
          : []
        )
          .map((template) => String(template?.ITEM_GENRE || "").trim())
          .filter(Boolean),
      ),
    ).sort((left, right) => left.localeCompare(right, "pl")),
  );
  Object.assign(runtime, {
    pricingItemGenreOptions,
  });
  return {
    attemptedSuggestionGeneration,
    t,
    autoTagItems,
    missingSuggestionFields,
    missingSuggestionFieldLabels,
    missingSuggestionFieldSet,
    counterfeitRisk,
    previewTemplateId,
    previewQuantity,
    previewCondition,
    previewReputation,
    previewMode,
    previewTemporaryModifier,
    previewQuickMode,
    previewLoading,
    previewError,
    counterfeitRiskTone,
    showValidationBanner,
    pricingModifierOptions,
    pricingRules,
    pricingCurrencyOptions,
    previewTemplateOptions,
    pricingItemClassOptions,
    pricingItemGenreOptions,
  };
};
