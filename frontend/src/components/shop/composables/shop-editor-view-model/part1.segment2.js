export const createEditorViewModelPart1Segment2 = (runtime) => {
  runtime.watch(
    runtime.previewTemplateOptions,
    (options) => {
      if (!options.length) {
        runtime.previewTemplateId.value = null;
        return;
      }
      const current = Number(runtime.previewTemplateId.value);
      if (!options.some((entry) => entry.value === current)) {
        runtime.previewTemplateId.value = options[0].value;
      }
    },
    { immediate: true },
  );
  const previewProfile = runtime.computed(() => {
    const active = runtime.ctx.activeShopProfile || {};
    const form = runtime.ctx.shopEditorForm || {};
    const pricingConfig = runtime.normalizeShopPricingConfig(
      form.pricingConfig ||
        active.pricingConfig ||
        runtime.createDefaultShopPricingConfig(),
    );
    if (!pricingConfig.currencyPolicy.settlementCurrencyCode) {
      pricingConfig.currencyPolicy.settlementCurrencyCode = String(
        runtime.ctx.currencyDefinitions?.defaultCurrencyCode || "generic",
      ).toLowerCase();
    }
    return { ...active, ...form, pricingConfig };
  });
  const previewPricingSignature = runtime.computed(() => {
    const profile = previewProfile.value;
    return JSON.stringify({
      typeId: profile.typeId,
      worldProfileId: profile.worldProfileId,
      locationType: profile.locationType,
      legalStatus: profile.legalStatus,
      wealthTier: profile.wealthTier,
      reputation: profile.reputation,
      seasonality: profile.seasonality,
      counterfeitRisk: profile.counterfeitRisk,
      marketSettings: profile.marketSettings,
      marketEvents: profile.marketEvents,
      pricingConfig: profile.pricingConfig,
    });
  });
  const representativeTemplateIds = runtime.computed(() => {
    const selected = Number(runtime.previewTemplateId.value);
    const seenClasses = new Set();
    const ids = [];
    (runtime.ctx.templateItems || []).forEach((template) => {
      const itemClass = String(template?.ITEM_CLASS || "generic");
      const id = Number(template?.ID);
      if (!Number.isFinite(id) || seenClasses.has(itemClass) || ids.length >= 6)
        return;
      seenClasses.add(itemClass);
      ids.push(id);
    });
    if (Number.isFinite(selected) && !ids.includes(selected))
      ids.unshift(selected);
    return ids.slice(0, 6);
  });
  const previewResult = runtime.ref(null);
  let timer = null;
  let requestSequence = 0;
  const loadPreview = () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const templateId = Number(runtime.previewTemplateId.value);
      if (!Number.isFinite(templateId) || !runtime.ctx.requestPricingPreview) {
        previewResult.value = null;
        return;
      }
      const sequence = ++requestSequence;
      runtime.previewLoading.value = true;
      runtime.previewError.value = "";
      try {
        const result = await runtime.ctx.requestPricingPreview({
          templateIds: runtime.previewQuickMode.value
            ? representativeTemplateIds.value
            : [templateId],
          quantity: Math.max(1, Number(runtime.previewQuantity.value || 1)),
          condition: runtime.previewCondition.value,
          reputation: runtime.previewReputation.value,
          mode: runtime.previewMode.value,
          temporaryModifier: Number(
            runtime.previewTemporaryModifier.value || 0,
          ),
          draftProfile: JSON.parse(JSON.stringify(previewProfile.value)),
        });
        if (sequence === requestSequence) previewResult.value = result;
      } catch (error) {
        if (sequence === requestSequence) {
          runtime.previewError.value = String(error?.message || error);
        }
      } finally {
        if (sequence === requestSequence) runtime.previewLoading.value = false;
      }
    }, 180);
  };
  runtime.watch(
    [
      runtime.previewTemplateId,
      runtime.previewQuantity,
      runtime.previewCondition,
      runtime.previewReputation,
      runtime.previewMode,
      runtime.previewTemporaryModifier,
      runtime.previewQuickMode,
      previewPricingSignature,
    ],
    loadPreview,
    { immediate: true },
  );
  const pricePreview = runtime.computed(() => previewResult.value);
  const shouldShowFieldError = (field) =>
    runtime.attemptedSuggestionGeneration.value &&
    runtime.missingSuggestionFieldSet.value.has(field);
  const updateField = (field, value) =>
    runtime.ctx.handleShopEditorFieldUpdate({ field, value });
  const updatePricingModifier = (modifierKey, enabled) => {
    const current = runtime.normalizeShopPricingConfig(
      runtime.ctx.shopEditorForm?.pricingConfig ||
        runtime.ctx.activeShopProfile?.pricingConfig,
    );
    updateField("pricingConfig", {
      ...current,
      policyId: "custom",
      enabledModifiers: {
        ...current.enabledModifiers,
        [modifierKey]: enabled !== false,
      },
    });
  };
  Object.assign(runtime, {
    previewProfile,
    representativeTemplateIds,
    previewResult,
    pricePreview,
    shouldShowFieldError,
    updateField,
    updatePricingModifier,
  });
  return {
    previewProfile,
    representativeTemplateIds,
    pricePreview,
    shouldShowFieldError,
    updateField,
    updatePricingModifier,
  };
};
