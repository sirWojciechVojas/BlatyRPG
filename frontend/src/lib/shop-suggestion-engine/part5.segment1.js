export const createRuntimePart5Segment1 = (runtime) => {
  const collectLibrarySeedsForNode = (node) => {
    const seeds = [];
    const typeId = String(node?.id || "");
    const requiredClasses = (
      node?.suggestionRules?.requiredItemClasses || []
    ).map(runtime.toUpper);
    const preferredClasses = (
      node?.suggestionRules?.preferredItemClasses || []
    ).map(runtime.toUpper);
    const preferredGenres = (node?.suggestionRules?.preferredGenres || []).map(
      runtime.toUpper,
    );
    const classCandidates = new Set([...requiredClasses, ...preferredClasses]);
    const typeSeeds = runtime.shopSuggestionSeedLibrary?.byType?.[typeId] || [];
    typeSeeds.forEach((entry) => {
      seeds.push(
        runtime.normalizeSeedEntry(entry, {
          priceTier: node?.suggestionRules?.draftPriceTier || "mid",
          sourceType: "type_seed",
        }),
      );
    });
    classCandidates.forEach((classKey) => {
      const classSeeds =
        runtime.shopSuggestionSeedLibrary?.byClass?.[classKey] || [];
      classSeeds.forEach((entry) => {
        seeds.push(
          runtime.normalizeSeedEntry(entry, {
            itemClass: classKey,
            priceTier: node?.suggestionRules?.draftPriceTier || "mid",
            sourceType: "class_seed",
          }),
        );
      });
    });
    preferredGenres.forEach((genre) => {
      const genreSeeds =
        runtime.shopSuggestionSeedLibrary?.byGenre?.[genre] || [];
      genreSeeds.forEach((entry) => {
        seeds.push(
          runtime.normalizeSeedEntry(entry, {
            itemGenre: genre,
            priceTier: node?.suggestionRules?.draftPriceTier || "mid",
            sourceType: "genre_seed",
          }),
        );
      });
    });
    (runtime.shopSuggestionSeedLibrary?.universal || []).forEach((entry) => {
      seeds.push(
        runtime.normalizeSeedEntry(entry, {
          priceTier: node?.suggestionRules?.draftPriceTier || "mid",
          sourceType: "universal_seed",
        }),
      );
    });
    return seeds.filter(Boolean);
  };
  Object.assign(runtime, {
    collectLibrarySeedsForNode,
  });
  const collectFallbackSeedsForNode = (node) => {
    const requiredClasses = (
      node?.suggestionRules?.requiredItemClasses || []
    ).map(runtime.toUpper);
    const preferredGenres = (node?.suggestionRules?.preferredGenres || []).map(
      runtime.toUpper,
    );
    const classKey = requiredClasses[0] || "TOOL";
    const genreKey = preferredGenres[0] || "UTILITY";
    const names = runtime.fallbackNamesForClassGenre(classKey, genreKey);
    return names
      .map((namePl, index) =>
        runtime.normalizeSeedEntry({
          namePl,
          descriptionPl: `${namePl}. Pozycja zapasowa dla profilu sklepu: ${node?.namePl || "Sklep"}.`,
          itemClass: classKey,
          itemGenre: genreKey,
          priceTier: node?.suggestionRules?.draftPriceTier || "mid",
          segment: "products",
          tags: ["fallback_single_item", String(node?.id || ""), String(index)],
          sourceType: "fallback_seed",
        }),
      )
      .filter(Boolean);
  };
  Object.assign(runtime, {
    collectFallbackSeedsForNode,
  });
  const allowedClassSetForNode = (node) => {
    const allowed = new Set(
      [
        ...(node?.suggestionRules?.requiredItemClasses || []),
        ...(node?.suggestionRules?.preferredItemClasses || []),
      ].map(runtime.toUpper),
    );
    (Array.isArray(node?.articleSeeds) ? node.articleSeeds : []).forEach(
      (entry) => {
        const itemClass = runtime.toUpper(entry?.itemClass);
        if (itemClass) {
          allowed.add(itemClass);
        }
      },
    );
    (
      runtime.shopSuggestionSeedLibrary?.byType?.[String(node?.id || "")] || []
    ).forEach((entry) => {
      const itemClass = runtime.toUpper(entry?.itemClass);
      if (itemClass) {
        allowed.add(itemClass);
      }
    });
    return allowed;
  };
  Object.assign(runtime, {
    allowedClassSetForNode,
  });
  const matchesAnyRuleToken = (tokens = [], haystack = "") =>
    (tokens || []).some(
      (token) => token && haystack.includes(runtime.toLower(token)),
    );
  Object.assign(runtime, {
    matchesAnyRuleToken,
  });
  const itemPassesHardRules = ({ item, node, allowedClasses }) => {
    const itemClass = runtime.toUpper(item?.ITEM_CLASS || item?.itemClass);
    const name = runtime.toLower(item?.NAME || item?.namePl);
    const description = runtime.toLower(
      item?.DESCRIPTION || item?.descriptionPl,
    );
    const tags = Array.isArray(item?.tags)
      ? item.tags.map(runtime.toLower).join(" ")
      : "";
    const tagsText = `${name} ${description} ${tags}`.trim();
    if (
      node?.suggestionRules?.strictClassRules === true &&
      allowedClasses?.size &&
      !allowedClasses.has(itemClass)
    ) {
      return false;
    }
    const forbiddenTags = (node?.suggestionRules?.forbiddenTags || []).map(
      runtime.toLower,
    );
    if (runtime.matchesAnyRuleToken(forbiddenTags, tagsText)) {
      return false;
    }
    return true;
  };
  Object.assign(runtime, {
    itemPassesHardRules,
  });
  return {
    collectLibrarySeedsForNode,
    collectFallbackSeedsForNode,
    allowedClassSetForNode,
    matchesAnyRuleToken,
    itemPassesHardRules,
  };
};
