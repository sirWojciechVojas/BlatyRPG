export const createRuntimePart5Segment2 = (runtime) => {
  const nodeReferenceTokens = (node) => {
    const typeSeeds =
      runtime.shopSuggestionSeedLibrary?.byType?.[String(node?.id || "")] || [];
    return runtime
      .uniqueArray([
        ...runtime.tokenize(node?.namePl),
        ...runtime.tokenize(node?.descriptionPl),
        ...(node?.suggestionRules?.requiredTags || []).map(runtime.toLower),
        ...(Array.isArray(node?.articleSeeds)
          ? node.articleSeeds.flatMap((entry) => [
              ...runtime.tokenize(entry?.namePl),
              ...runtime.tokenize(entry?.descriptionPl),
              ...(Array.isArray(entry?.tags)
                ? entry.tags.map(runtime.toLower)
                : []),
            ])
          : []),
        ...typeSeeds.flatMap((entry) => [
          ...runtime.tokenize(entry?.namePl),
          ...runtime.tokenize(entry?.descriptionPl),
          ...(Array.isArray(entry?.tags)
            ? entry.tags.map(runtime.toLower)
            : []),
        ]),
      ])
      .filter(Boolean);
  };
  Object.assign(runtime, {
    nodeReferenceTokens,
  });
  const buildSeedPoolForNode = (node, templates = []) => {
    const allowedClasses = runtime.allowedClassSetForNode(node);
    const existingNameKeys = new Set(
      (templates || []).map((item) => runtime.normalizeNameKey(item?.NAME)),
    );
    const sourcePools = [
      ...(Array.isArray(node?.articleSeeds) ? node.articleSeeds : []),
      ...runtime.collectExampleSeedsForNode(node),
      ...runtime.collectLibrarySeedsForNode(node),
      ...runtime.collectFallbackSeedsForNode(node),
    ]
      .map((entry) =>
        runtime.normalizeSeedEntry(entry, {
          priceTier: node?.suggestionRules?.draftPriceTier || "mid",
          sourceType: "article_seed",
        }),
      )
      .filter(Boolean)
      .filter((entry) =>
        runtime.itemPassesHardRules({
          item: entry,
          node,
          allowedClasses,
        }),
      );
    const dedup = [];
    const seen = new Set();
    sourcePools.forEach((entry) => {
      const key = runtime.normalizeNameKey(entry.namePl);
      if (!key || seen.has(key) || existingNameKeys.has(key)) {
        return;
      }
      seen.add(key);
      dedup.push(entry);
    });
    return dedup;
  };
  Object.assign(runtime, {
    buildSeedPoolForNode,
  });
  const ensureSeedPoolSize = (seedPool = []) =>
    (Array.isArray(seedPool) ? seedPool : []).filter((entry) =>
      runtime.isSingleItemName(entry?.namePl),
    );
  Object.assign(runtime, {
    ensureSeedPoolSize,
  });
  const appendReasonDetail = (details, reasons, textPl, refKey, refValue) => {
    const line = String(textPl || "").trim();
    if (!line) {
      return;
    }
    reasons.push(line);
    details.push({
      textPl: line,
      refKey: String(refKey || ""),
      refValue: String(refValue ?? ""),
    });
  };
  Object.assign(runtime, {
    appendReasonDetail,
  });
  return {
    nodeReferenceTokens,
    buildSeedPoolForNode,
    ensureSeedPoolSize,
    appendReasonDetail,
  };
};
