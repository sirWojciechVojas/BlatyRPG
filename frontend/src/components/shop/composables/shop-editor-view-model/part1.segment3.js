export const createEditorViewModelPart1Segment3 = (runtime) => {
  const currentPricingConfig = () =>
    (() => {
      const config = runtime.normalizeShopPricingConfig(
        runtime.ctx.shopEditorForm?.pricingConfig ||
          runtime.ctx.activeShopProfile?.pricingConfig,
      );
      if (!config.currencyPolicy.settlementCurrencyCode) {
        config.currencyPolicy.settlementCurrencyCode = String(
          runtime.ctx.currencyDefinitions?.defaultCurrencyCode || "generic",
        ).toLowerCase();
      }
      return config;
    })();
  Object.assign(runtime, {
    currentPricingConfig,
  });
  return {
    currentPricingConfig,
  };
};
