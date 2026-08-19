export const createRuntimePart3Segment2 = (runtime) => {
  const normalizeNameKey = (value) =>
    String(value || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^\p{L}\p{N}]+/gu, " ")
      .trim()
      .replace(/\s+/g, " ");
  Object.assign(runtime, {
    normalizeNameKey,
  });
  const normalizeDisplayName = (value) =>
    String(value || "")
      .replace(/\s+/g, " ")
      .trim();
  Object.assign(runtime, {
    normalizeDisplayName,
  });
  const stripBannedSuggestionSuffixes = (value) => {
    let next = runtime.normalizeDisplayName(value);
    let changed = true;
    while (changed) {
      changed = false;
      runtime.bannedSuggestionSuffixPatterns.forEach((pattern) => {
        const replaced = runtime.normalizeDisplayName(
          next.replace(pattern, ""),
        );
        if (replaced !== next) {
          next = replaced;
          changed = true;
        }
      });
    }
    return next;
  };
  Object.assign(runtime, {
    stripBannedSuggestionSuffixes,
  });
  const lowerCaseFirst = (value) => {
    const text = String(value || "");
    if (!text) {
      return "";
    }
    return text.charAt(0).toLocaleLowerCase("pl-PL") + text.slice(1);
  };
  Object.assign(runtime, {
    lowerCaseFirst,
  });
  const upperCaseFirst = (value) => {
    const text = String(value || "");
    if (!text) {
      return "";
    }
    return text.charAt(0).toLocaleUpperCase("pl-PL") + text.slice(1);
  };
  Object.assign(runtime, {
    upperCaseFirst,
  });
  const isSingleItemName = (value) => {
    const normalized = runtime.normalizeNameKey(value);
    if (!normalized) {
      return false;
    }
    const tokens = normalized.split(" ").filter(Boolean);
    if (!tokens.length) {
      return false;
    }
    if (
      runtime.blockedSuggestionNamePhrases.some((phrase) =>
        normalized.includes(phrase),
      )
    ) {
      return false;
    }
    if (
      tokens.some((token) => runtime.blockedSuggestionNameTokens.has(token))
    ) {
      return false;
    }
    if (tokens.length === 1 && tokens[0].length < 4) {
      return false;
    }
    return true;
  };
  Object.assign(runtime, {
    isSingleItemName,
  });
  const normalizeSeedEntry = (entry, fallback = {}) => {
    const namePl = runtime.stripBannedSuggestionSuffixes(entry?.namePl || "");
    if (!namePl || !runtime.isSingleItemName(namePl)) {
      return null;
    }
    return {
      namePl,
      descriptionPl: String(
        entry?.descriptionPl ||
          fallback.descriptionPl ||
          `${namePl}. Propozycja dla sklepu.`,
      ).trim(),
      itemClass: runtime.toUpper(
        entry?.itemClass || fallback.itemClass || "TOOL",
      ),
      itemGenre: runtime.toUpper(
        entry?.itemGenre || fallback.itemGenre || "UTILITY",
      ),
      priceTier: String(
        entry?.priceTier || fallback.priceTier || "mid",
      ).toLowerCase(),
      segment: String(entry?.segment || fallback.segment || "products"),
      tags: Array.isArray(entry?.tags)
        ? entry.tags.map(runtime.toLower).filter(Boolean)
        : [],
      sourceType: String(
        entry?.sourceType || fallback.sourceType || "seed",
      ).toLowerCase(),
    };
  };
  Object.assign(runtime, {
    normalizeSeedEntry,
  });
  const resolveLocationBuckets = (locationType) => {
    const key = String(locationType || "")
      .trim()
      .toLowerCase();
    if (!key) {
      return [];
    }
    const canonical = runtime.locationFallbackMap[key] || [key];
    return runtime.uniqueArray([key, ...canonical]);
  };
  Object.assign(runtime, {
    resolveLocationBuckets,
  });
  const nodeByIdMap = (catalogNodes = []) =>
    new Map((catalogNodes || []).map((entry) => [String(entry.id), entry]));
  Object.assign(runtime, {
    nodeByIdMap,
  });
  const nodeAncestryIds = (node, nodesMap) => {
    const ids = [];
    let current = node;
    while (current?.parentId) {
      const parentId = String(current.parentId);
      ids.push(parentId);
      current = nodesMap.get(parentId);
    }
    return ids;
  };
  Object.assign(runtime, {
    nodeAncestryIds,
  });
  return {
    normalizeNameKey,
    normalizeDisplayName,
    stripBannedSuggestionSuffixes,
    lowerCaseFirst,
    upperCaseFirst,
    isSingleItemName,
    normalizeSeedEntry,
    resolveLocationBuckets,
    nodeByIdMap,
    nodeAncestryIds,
  };
};
