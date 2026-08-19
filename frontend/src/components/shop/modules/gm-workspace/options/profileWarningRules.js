const add = (warnings, condition, tone, key) => {
  if (condition) warnings.push({ tone, key });
};

export const buildShopProfileWarnings = (draft = {}, recommendedRisk = 0) => {
  const warnings = [];
  const settings = draft.marketSettings || {};
  add(warnings, !draft.typeId, "required", "missingType");
  add(
    warnings,
    ["elitarny", "luksusowy"].includes(draft.wealthTier) &&
      ["wies", "trakt", "przedmiescie"].includes(draft.locationType),
    "warning",
    "wealthLocation",
  );
  add(
    warnings,
    ["grey", "illegal"].includes(draft.legalStatus) &&
      Number(draft.counterfeitRisk || 0) < 30,
    "warning",
    "lowRiskIllegal",
  );
  add(
    warnings,
    draft.legalStatus === "illegal" &&
      ["dobra", "znakomita"].includes(draft.reputation),
    "notice",
    "reputableIllegal",
  );
  add(
    warnings,
    draft.seasonality === "jarmark" &&
      !["jarmark", "rynek", "trakt"].includes(draft.locationType),
    "notice",
    "fairLocation",
  );
  add(
    warnings,
    Math.abs(Number(draft.counterfeitRisk || 0) - recommendedRisk) >= 20,
    "notice",
    "riskMismatch",
  );
  add(
    warnings,
    Math.abs(Number(settings.availabilityBias || 0)) >= 35,
    "warning",
    "extremeAvailability",
  );
  add(
    warnings,
    Number(settings.buybackBudget || 0) > 0 &&
      Number(settings.maxBuybackItemValue || 0) >
        Number(settings.buybackBudget),
    "notice",
    "buybackLimits",
  );
  const local = new Set(settings.localCategories || []);
  add(
    warnings,
    (settings.importedCategories || []).some((key) => local.has(key)),
    "warning",
    "localImportConflict",
  );
  add(
    warnings,
    (draft.marketEvents || []).some(
      (event) =>
        event.enabled &&
        (Number(event.multiplier || 1) <= 0.35 ||
          Number(event.multiplier || 1) >= 3),
    ),
    "warning",
    "extremeMarketEvent",
  );
  return warnings;
};
