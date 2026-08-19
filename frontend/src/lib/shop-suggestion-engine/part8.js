export const createRuntimePart8 = (runtime) => {
  const selectBalancedSuggestions = ({
    entries = [],
    node,
    suggestionTarget = runtime.SUGGESTIONS_TARGET_DEFAULT,
  }) => {
    const rankedEntries = runtime.rankSuggestionCandidates(entries);
    if (!rankedEntries.length || suggestionTarget <= 0) {
      return [];
    }
    const requiredClasses = new Set(
      (node?.suggestionRules?.requiredItemClasses || []).map(runtime.toUpper),
    );
    const preferredClasses = new Set(
      (node?.suggestionRules?.preferredItemClasses || []).map(runtime.toUpper),
    );
    const preferredGenres = new Set(
      (node?.suggestionRules?.preferredGenres || []).map(runtime.toUpper),
    );
    const preferredSegments = runtime.preferredSegmentsForNode(
      node,
      rankedEntries,
    );
    const selected = [];
    const selectedIds = new Set();
    const classCounts = new Map();
    const genreCounts = new Map();
    const segmentCounts = new Map();
    const actionCounts = new Map();
    const register = (
      entry,
      recommendation = runtime.representativeSuggestionRecommendation,
    ) => {
      const id = runtime.suggestionEntryId(entry);
      if (!id || selectedIds.has(id)) {
        return false;
      }
      const normalized = runtime.promoteSuggestionRecommendation(
        entry,
        recommendation,
      );
      selected.push(normalized);
      selectedIds.add(id);
      runtime.mapIncrement(classCounts, normalized?.classKey);
      runtime.mapIncrement(genreCounts, normalized?.genreKey);
      runtime.mapIncrement(segmentCounts, normalized?.segment);
      runtime.mapIncrement(actionCounts, normalized?.action);
      return true;
    };
    const addFirstMatch = (
      predicate,
      recommendation = runtime.representativeSuggestionRecommendation,
    ) => {
      const match = rankedEntries.find(
        (entry) =>
          !selectedIds.has(runtime.suggestionEntryId(entry)) &&
          predicate(entry),
      );
      if (match) {
        register(match, recommendation);
      }
    };
    requiredClasses.forEach((classKey) =>
      addFirstMatch((entry) => runtime.toUpper(entry?.classKey) === classKey),
    );
    preferredClasses.forEach((classKey) =>
      addFirstMatch((entry) => runtime.toUpper(entry?.classKey) === classKey),
    );
    preferredGenres.forEach((genreKey) =>
      addFirstMatch((entry) => runtime.toUpper(entry?.genreKey) === genreKey),
    );
    preferredSegments.forEach((segment) =>
      addFirstMatch((entry) => runtime.toLower(entry?.segment) === segment),
    );
    if (!selected.length) {
      register(rankedEntries[0], runtime.fallbackSuggestionRecommendation);
    }
    while (
      selected.length < suggestionTarget &&
      selectedIds.size < rankedEntries.length
    ) {
      let bestEntry = null;
      let bestValue = Number.NEGATIVE_INFINITY;
      rankedEntries.forEach((entry, index) => {
        const id = runtime.suggestionEntryId(entry);
        if (!id || selectedIds.has(id)) {
          return;
        }
        const classKey = runtime.toUpper(entry?.classKey);
        const genreKey = runtime.toUpper(entry?.genreKey);
        const segmentKey = runtime.toLower(entry?.segment);
        const actionKey = String(entry?.action || "");
        let value =
          Number(entry?.scoreRaw ?? entry?.score ?? 0) +
          Number(entry?.recommendationWeight || 0) * 90 -
          index * 0.015;
        if (requiredClasses.has(classKey) && !classCounts.get(classKey)) {
          value += 72;
        }
        if (preferredClasses.has(classKey) && !classCounts.get(classKey)) {
          value += 38;
        }
        if (preferredGenres.has(genreKey) && !genreCounts.get(genreKey)) {
          value += 28;
        }
        if (segmentKey && !segmentCounts.get(segmentKey)) {
          value += 34;
        }
        value -= Number(classCounts.get(classKey) || 0) * 18;
        value -= Number(genreCounts.get(genreKey) || 0) * 10;
        value -= Number(segmentCounts.get(segmentKey) || 0) * 16;
        value -= Number(actionCounts.get(actionKey) || 0) * 6;
        if (actionKey === "use_existing") {
          value += 4;
        }
        if (value > bestValue) {
          bestValue = value;
          bestEntry = entry;
        }
      });
      if (!bestEntry) {
        break;
      }
      register(bestEntry, runtime.balancedSuggestionRecommendation);
    }
    return selected.slice(0, suggestionTarget);
  };
  Object.assign(runtime, {
    selectBalancedSuggestions,
  });
  const buildPersonalizedVariants = ({
    suggestionId,
    baseName,
    baseDescription,
    basePrice,
    itemClass,
    itemGenre,
    profile,
    score,
  }) => {
    const count = 2 + (runtime.hashSeed(`${suggestionId}:count`) % 5);
    const wealthInfluence = {
      nedzny: -0.12,
      biedny: -0.07,
      standard: 0,
      bogaty: 0.06,
      elitarny: 0.11,
      luksusowy: 0.16,
    };
    const reputationInfluence = {
      fatalna: -0.08,
      zla: -0.05,
      podejrzana: -0.02,
      neutralna: 0,
      dobra: 0.03,
      znakomita: 0.07,
    };
    const wealthDelta = Number(
      wealthInfluence[
        String(profile?.wealthTier || "standard").toLowerCase()
      ] ?? 0,
    );
    const reputationDelta = Number(
      reputationInfluence[
        String(profile?.reputation || "neutralna").toLowerCase()
      ] ?? 0,
    );
    const scoreDelta = runtime.clamp(Number(score || 0) / 600, -0.08, 0.14);
    const fixedPrice = Math.max(1, Number(basePrice || 1));
    const safeBaseName = runtime.isSingleItemName(baseName)
      ? runtime.normalizeDisplayName(baseName)
      : "Przedmiot";
    const classLabel = String(itemClass || "towar").toLowerCase();
    const genreLabel = String(itemGenre || "").toLowerCase();
    return Array.from(
      {
        length: count,
      },
      (_, index) => {
        const salt = `${suggestionId}:variant:${index}`;
        const qualifier =
          runtime.personalizedVariantQualifiers[
            Math.floor(
              runtime.seededFloat(salt, "qualifier") *
                runtime.personalizedVariantQualifiers.length,
            )
          ];
        const jitter = (runtime.seededFloat(salt, "jitter") - 0.5) * 0.2;
        const factor = 1 + wealthDelta + reputationDelta + scoreDelta + jitter;
        const personalCost = Math.max(1, Math.round(fixedPrice * factor));
        const material = runtime.materialEntryForVariant(suggestionId, index);
        const personalPseu = runtime.buildMaterializedVariantName(
          safeBaseName,
          material,
        );
        return {
          variantId: `${suggestionId}:v${index + 1}`,
          personalPseu,
          personalDesc: `${baseDescription || safeBaseName}. Wersja ${qualifier}; wykonanie ${material?.prepositional || "standardowe"} dla klasy ${classLabel}${genreLabel ? ` / genre ${genreLabel}` : ""}.`,
          personalCost,
          quantity: 1,
        };
      },
    );
  };
  Object.assign(runtime, {
    buildPersonalizedVariants,
  });
  return {
    selectBalancedSuggestions,
    buildPersonalizedVariants,
  };
};
