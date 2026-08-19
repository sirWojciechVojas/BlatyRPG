export const createRuntimePart7 = (runtime) => {
  const wealthChargeTargets = {
    nedzny: 18,
    biedny: 24,
    standard: 36,
    bogaty: 52,
    elitarny: 68,
    luksusowy: 82,
  };
  Object.assign(runtime, {
    wealthChargeTargets,
  });
  const priceAndChargeFitScore = ({ item, profile }) => {
    const wealthKey = String(profile?.wealthTier || "standard").toLowerCase();
    const targetPrice = Number(
      runtime.wealthPriceTargets[wealthKey] ||
        runtime.wealthPriceTargets.standard,
    );
    const targetCharge = Number(
      runtime.wealthChargeTargets[wealthKey] ||
        runtime.wealthChargeTargets.standard,
    );
    const itemPrice = Math.max(1, Number(item?.PRIZE || 1));
    const itemCharge = Math.max(1, Number(item?.CHARGE || 1));
    const logDistance = Math.abs(Math.log((itemPrice + 1) / (targetPrice + 1)));
    const priceFit = runtime.clamp(28 - logDistance * 18, -34, 28);
    const chargeDistance =
      Math.abs(itemCharge - targetCharge) / Math.max(10, targetCharge);
    const chargeFit = runtime.clamp(14 - chargeDistance * 15, -16, 14);
    return priceFit + chargeFit;
  };
  Object.assign(runtime, {
    priceAndChargeFitScore,
  });
  const profileParameterSignature = (profile = {}) =>
    [
      String(profile?.typeId || ""),
      String(profile?.worldProfileId || ""),
      String(profile?.locationType || ""),
      String(profile?.legalStatus || ""),
      String(profile?.wealthTier || ""),
      String(profile?.reputation || ""),
      String(profile?.seasonality || ""),
    ].join("|");
  Object.assign(runtime, {
    profileParameterSignature,
  });
  const scoreTieBreakerFor = (suggestionId, profile) =>
    ((runtime.hashSeed(
      `${String(suggestionId || "")}:${runtime.profileParameterSignature(profile)}`,
    ) %
      97) +
      1) /
    100;
  Object.assign(runtime, {
    scoreTieBreakerFor,
  });
  const suggestionLabelForSort = (entry) =>
    String(
      entry?.label ||
        entry?.displayName ||
        entry?.templateName ||
        entry?.draftTemplate?.NAME ||
        "",
    ).trim();
  Object.assign(runtime, {
    suggestionLabelForSort,
  });
  const suggestionScoreComparator = (a, b) =>
    Number(b?.score || 0) - Number(a?.score || 0) ||
    Number(b?.scoreTieBreaker || 0) - Number(a?.scoreTieBreaker || 0) ||
    runtime
      .suggestionLabelForSort(a)
      .localeCompare(runtime.suggestionLabelForSort(b), "pl");
  Object.assign(runtime, {
    suggestionScoreComparator,
  });
  const suggestionEntryId = (entry) => String(entry?.suggestionId || "");
  Object.assign(runtime, {
    suggestionEntryId,
  });
  const promoteSuggestionRecommendation = (
    entry,
    recommendation = runtime.representativeSuggestionRecommendation,
  ) => {
    if (!entry || typeof entry !== "object") {
      return entry;
    }
    const currentCode = String(entry?.recommendationCode || "").toLowerCase();
    if (currentCode && currentCode !== "skip") {
      return entry;
    }
    return {
      ...entry,
      recommendationCode: recommendation.code,
      recommendationLabelPl: recommendation.labelPl,
      recommendationReasonPl: recommendation.reasonPl,
      recommendationWeight: recommendation.weight,
    };
  };
  Object.assign(runtime, {
    promoteSuggestionRecommendation,
  });
  const seedSourceWeights = Object.freeze({
    type_seed: 42,
    article_seed: 36,
    class_seed: 18,
    genre_seed: 14,
    example_seed: 10,
    universal_seed: 4,
    fallback_seed: -8,
  });
  Object.assign(runtime, {
    seedSourceWeights,
  });
  const mapIncrement = (map, key) => {
    const safeKey = String(key || "").trim();
    if (!safeKey) {
      return;
    }
    map.set(safeKey, Number(map.get(safeKey) || 0) + 1);
  };
  Object.assign(runtime, {
    mapIncrement,
  });
  const rankSuggestionCandidates = (entries = []) =>
    [...entries].sort(
      (a, b) =>
        Number(b?.recommendationWeight || 0) -
          Number(a?.recommendationWeight || 0) ||
        runtime.suggestionScoreComparator(a, b),
    );
  Object.assign(runtime, {
    rankSuggestionCandidates,
  });
  const suggestionDistinctKey = (entry) => {
    const labelKey = runtime.normalizeNameKey(
      runtime.suggestionLabelForSort(entry),
    );
    const classKey = runtime.toUpper(entry?.classKey);
    const genreKey = runtime.toUpper(entry?.genreKey);
    return `${classKey}::${genreKey}::${labelKey}`;
  };
  Object.assign(runtime, {
    suggestionDistinctKey,
  });
  const pickBetterSuggestionEntry = (current, candidate) => {
    const currentAction = String(current?.action || "");
    const candidateAction = String(candidate?.action || "");
    if (currentAction !== candidateAction) {
      if (candidateAction === "use_existing") {
        return candidate;
      }
      if (currentAction === "use_existing") {
        return current;
      }
    }
    return runtime.suggestionScoreComparator(candidate, current) < 0
      ? current
      : candidate;
  };
  Object.assign(runtime, {
    pickBetterSuggestionEntry,
  });
  const dedupeSuggestionEntries = (entries = []) => {
    const byKey = new Map();
    (Array.isArray(entries) ? entries : []).forEach((entry) => {
      const key = runtime.suggestionDistinctKey(entry);
      if (!key || key.endsWith("::")) {
        return;
      }
      const existing = byKey.get(key);
      if (!existing) {
        byKey.set(key, entry);
        return;
      }
      byKey.set(key, runtime.pickBetterSuggestionEntry(existing, entry));
    });
    return Array.from(byKey.values()).sort(
      (a, b) =>
        Number(b?.recommendationWeight || 0) -
          Number(a?.recommendationWeight || 0) ||
        runtime.suggestionScoreComparator(a, b),
    );
  };
  Object.assign(runtime, {
    dedupeSuggestionEntries,
  });
  const preferredSegmentsForNode = (node, entries = []) => {
    const available = new Set(
      entries
        .map((entry) => String(entry?.segment || "").toLowerCase())
        .filter(Boolean),
    );
    const classSet = new Set(
      [
        ...(node?.suggestionRules?.requiredItemClasses || []),
        ...(node?.suggestionRules?.preferredItemClasses || []),
      ].map(runtime.toUpper),
    );
    const ordered = [];
    if (
      classSet.has("FOOD") ||
      classSet.has("POTION") ||
      classSet.has("ALCHEMY")
    ) {
      ordered.push("products", "ingredients", "equipment");
    } else if (
      classSet.has("TOOL") ||
      classSet.has("WEAPON") ||
      classSet.has("ARMOR") ||
      classSet.has("GADGET")
    ) {
      ordered.push("equipment", "products", "ingredients");
    } else {
      ordered.push("products", "equipment", "ingredients");
    }
    return runtime
      .uniqueArray(ordered)
      .filter((segment) => available.has(segment));
  };
  Object.assign(runtime, {
    preferredSegmentsForNode,
  });
  return {
    wealthChargeTargets,
    priceAndChargeFitScore,
    profileParameterSignature,
    scoreTieBreakerFor,
    suggestionLabelForSort,
    suggestionScoreComparator,
    suggestionEntryId,
    promoteSuggestionRecommendation,
    seedSourceWeights,
    mapIncrement,
    rankSuggestionCandidates,
    suggestionDistinctKey,
    pickBetterSuggestionEntry,
    dedupeSuggestionEntries,
    preferredSegmentsForNode,
  };
};
