export const createRuntimePart4 = (runtime) => {
  const seasonalScore = (seasonality, tagsText, node, ancestryIds = []) => {
    const key = String(seasonality || "caloroczny").toLowerCase();
    const keywords = runtime.seasonKeywordMap[key] || [];
    let score = 0;
    keywords.forEach((word) => {
      if (word && tagsText.includes(word)) {
        score += 14;
      }
    });
    if (score > 40) {
      score = 40;
    }
    const inSeasonalDomain =
      String(node?.parentId || "")
        .toLowerCase()
        .includes("seasonal") ||
      ancestryIds.some((id) => String(id).toLowerCase().includes("seasonal"));
    if (inSeasonalDomain && key !== "caloroczny") {
      score += 18;
    }
    if (key === "caloroczny") {
      score += 4;
    }
    return score;
  };
  Object.assign(runtime, {
    seasonalScore,
  });
  const legalSignalScore = (tagsText) => {
    let illegalHits = 0;
    let legalHits = 0;
    let greyHits = 0;
    runtime.legalSignals.illegal.forEach((tag) => {
      if (tag && tagsText.includes(tag)) {
        illegalHits += 1;
      }
    });
    runtime.legalSignals.legal.forEach((tag) => {
      if (tag && tagsText.includes(tag)) {
        legalHits += 1;
      }
    });
    runtime.legalSignals.grey.forEach((tag) => {
      if (tag && tagsText.includes(tag)) {
        greyHits += 1;
      }
    });
    return {
      illegalHits,
      legalHits,
      greyHits,
    };
  };
  Object.assign(runtime, {
    legalSignalScore,
  });
  const worldProfileMetaFor = (worldProfileId) =>
    runtime.worldProfileMap.get(String(worldProfileId || "standard")) || null;
  Object.assign(runtime, {
    worldProfileMetaFor,
  });
  const profileAliasTokens = (profile) =>
    runtime.uniqueArray([
      ...runtime.tokenize(profile?.signboardName),
      ...String(profile?.signboardAltNamesText || "")
        .split(",")
        .flatMap((entry) => runtime.tokenize(entry)),
      ...(Array.isArray(profile?.signboardAltNames)
        ? profile.signboardAltNames.flatMap((entry) => runtime.tokenize(entry))
        : []),
    ]);
  Object.assign(runtime, {
    profileAliasTokens,
  });
  const applyCounterfeitRisk = (risk, itemClass, itemGenre, tagsText) => {
    const value = Number(risk || 0);
    if (!Number.isFinite(value)) {
      return 0;
    }
    let score = 0;
    const hotClasses = new Set(["JEWELLERY", "ALCHEMY", "MAGIC", "POTION"]);
    if (
      value >= 65 &&
      (hotClasses.has(itemClass) || hotClasses.has(itemGenre))
    ) {
      score += 10;
    }
    const suspiciousTags = ["falsz", "podejrz", "nielegal", "truciz"];
    if (value <= 25) {
      suspiciousTags.forEach((tag) => {
        if (tagsText.includes(tag)) {
          score -= 8;
        }
      });
    }
    return score;
  };
  Object.assign(runtime, {
    applyCounterfeitRisk,
  });
  const keywordPreferenceScore = (
    tagsText,
    config = {},
    positiveWeight = 6,
    negativeWeight = 8,
  ) => {
    let score = 0;
    (config?.prefer || []).forEach((token) => {
      if (token && tagsText.includes(runtime.toLower(token))) {
        score += positiveWeight;
      }
    });
    (config?.avoid || []).forEach((token) => {
      if (token && tagsText.includes(runtime.toLower(token))) {
        score -= negativeWeight;
      }
    });
    return score;
  };
  Object.assign(runtime, {
    keywordPreferenceScore,
  });
  const locationContextScore = (profile, tagsText, node) => {
    const locationKey = String(profile?.locationType || "").toLowerCase();
    if (!locationKey) {
      return 0;
    }
    const locationBuckets = runtime.resolveLocationBuckets(locationKey);
    const locations = Array.isArray(node?.typicalLocations)
      ? node.typicalLocations
      : [];
    let score = 0;
    if (locationBuckets.some((bucket) => locations.includes(bucket))) {
      score += 34;
    } else if (locations.length) {
      score -= 10;
    }
    score += runtime.keywordPreferenceScore(
      tagsText,
      {
        prefer: runtime.locationKeywordMap[locationKey] || [],
      },
      7,
      0,
    );
    return score;
  };
  Object.assign(runtime, {
    locationContextScore,
  });
  const wealthContextScore = (profile, tagsText) => {
    const wealthKey = String(profile?.wealthTier || "standard").toLowerCase();
    return runtime.keywordPreferenceScore(
      tagsText,
      runtime.wealthKeywordMap[wealthKey] || {},
      7,
      9,
    );
  };
  Object.assign(runtime, {
    wealthContextScore,
  });
  const reputationContextScore = (profile, tagsText) => {
    const reputationKey = String(
      profile?.reputation || "neutralna",
    ).toLowerCase();
    return runtime.keywordPreferenceScore(
      tagsText,
      runtime.reputationKeywordMap[reputationKey] || {},
      7,
      8,
    );
  };
  Object.assign(runtime, {
    reputationContextScore,
  });
  const legalContextScore = (profileLegal, nodeLegal, legalHits) => {
    let score =
      Number(
        runtime.legalCompatibilityScore?.[profileLegal]?.[nodeLegal] ?? 0,
      ) * 1.6;
    if (profileLegal === "legal" || profileLegal === "licensed") {
      score += legalHits.legalHits * 8;
      score -= legalHits.illegalHits * 14;
      score -= legalHits.greyHits * 4;
      return score;
    }
    if (profileLegal === "grey" || profileLegal === "mixed") {
      score += legalHits.greyHits * 10;
      score += legalHits.legalHits * 4;
      score -= legalHits.illegalHits * 3;
      return score;
    }
    if (profileLegal === "illegal") {
      score += legalHits.illegalHits * 12;
      score += legalHits.greyHits * 6;
      score -= legalHits.legalHits * 6;
    }
    return score;
  };
  Object.assign(runtime, {
    legalContextScore,
  });
  const collectExampleSeedsForNode = (node) => {
    const required = (node?.suggestionRules?.requiredItemClasses || []).map(
      runtime.toUpper,
    );
    const preferred = (node?.suggestionRules?.preferredItemClasses || []).map(
      runtime.toUpper,
    );
    const genres = (node?.suggestionRules?.preferredGenres || []).map(
      runtime.toUpper,
    );
    const classSet = new Set([...required, ...preferred]);
    const items = [];
    Object.entries(runtime.classGenreExamples).forEach(([key, names]) => {
      const [itemClass, itemGenre] = key.split(":").map(runtime.toUpper);
      const classMatch = classSet.size ? classSet.has(itemClass) : true;
      const genreMatch = genres.length ? genres.includes(itemGenre) : true;
      if (!classMatch || !genreMatch) {
        return;
      }
      names.forEach((namePl) => {
        items.push(
          runtime.normalizeSeedEntry(
            {
              namePl,
              descriptionPl: `${namePl}. Przykładowy towar dla typu sklepu: ${node?.namePl || "Sklep"}.`,
              itemClass,
              itemGenre,
              priceTier: node?.suggestionRules?.draftPriceTier || "mid",
              segment: "products",
              tags: ["przykład", String(node?.id || "")],
              sourceType: "example_seed",
            },
            {},
          ),
        );
      });
    });
    return items.filter(Boolean);
  };
  Object.assign(runtime, {
    collectExampleSeedsForNode,
  });
  return {
    seasonalScore,
    legalSignalScore,
    worldProfileMetaFor,
    profileAliasTokens,
    applyCounterfeitRisk,
    keywordPreferenceScore,
    locationContextScore,
    wealthContextScore,
    reputationContextScore,
    legalContextScore,
    collectExampleSeedsForNode,
  };
};
