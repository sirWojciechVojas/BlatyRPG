export const createRuntimePart6 = (runtime) => {
  const hashSeed = (seed) => {
    let hash = 2166136261;
    const text = String(seed || "");
    for (let i = 0; i < text.length; i += 1) {
      hash ^= text.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
  };
  Object.assign(runtime, {
    hashSeed,
  });
  const seededFloat = (seed, salt) => {
    const hash = runtime.hashSeed(`${seed}::${salt}`);
    return (hash % 10000) / 10000;
  };
  Object.assign(runtime, {
    seededFloat,
  });
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  Object.assign(runtime, {
    clamp,
  });
  const normalizeHeadWord = (value) => {
    const head = String(value || "")
      .split(/\s+/)
      .map((entry) => entry.trim())
      .find(Boolean);
    if (!head) {
      return "";
    }
    return head.toLowerCase().replace(/[^\p{L}\p{N}-]+/gu, "");
  };
  Object.assign(runtime, {
    normalizeHeadWord,
  });
  const inferNameGrammar = (baseName) => {
    const head = runtime.normalizeHeadWord(baseName);
    if (!head) {
      return {
        form: null,
        confident: false,
      };
    }
    if (head.endsWith("a")) {
      return {
        form: "f",
        confident: true,
      };
    }
    if (head.endsWith("o") || head.endsWith("e") || head.endsWith("um")) {
      return {
        form: "n",
        confident: true,
      };
    }
    if (head.endsWith("y") || head.endsWith("i")) {
      return {
        form: "pl",
        confident: false,
      };
    }
    if (/[bcdfghjklmnpqrstvwxyz]$/i.test(head)) {
      return {
        form: "m",
        confident: true,
      };
    }
    return {
      form: null,
      confident: false,
    };
  };
  Object.assign(runtime, {
    inferNameGrammar,
  });
  const materialEntryForVariant = (suggestionId, index = 0) => {
    if (!runtime.materialLexicon.length) {
      return null;
    }
    const offset =
      runtime.hashSeed(`${String(suggestionId || "variant")}:material`) %
      runtime.materialLexicon.length;
    return runtime.materialLexicon[
      (offset + index) % runtime.materialLexicon.length
    ];
  };
  Object.assign(runtime, {
    materialEntryForVariant,
  });
  const buildMaterializedVariantName = (baseName, material) => {
    const sanitizedBaseName = runtime.stripBannedSuggestionSuffixes(baseName);
    const safeBaseName = runtime.isSingleItemName(sanitizedBaseName)
      ? runtime.normalizeDisplayName(sanitizedBaseName)
      : "Przedmiot";
    if (!material) {
      return safeBaseName;
    }
    const materialPhrase = runtime.normalizeDisplayName(
      `${safeBaseName} ${material.prepositional}`,
    );
    if (runtime.isSingleItemName(materialPhrase)) {
      return materialPhrase;
    }
    const grammar = runtime.inferNameGrammar(safeBaseName);
    if (
      grammar?.confident &&
      grammar?.form &&
      material.adjective?.[grammar.form]
    ) {
      const adjective = runtime.upperCaseFirst(
        material.adjective[grammar.form],
      );
      const phrase = runtime.normalizeDisplayName(
        `${adjective} ${runtime.lowerCaseFirst(safeBaseName)}`,
      );
      if (runtime.isSingleItemName(phrase)) {
        return phrase;
      }
    }
    return safeBaseName;
  };
  Object.assign(runtime, {
    buildMaterializedVariantName,
  });
  const profileCategoryTokens = (profile) =>
    (Array.isArray(profile?.categoryTags)
      ? profile.categoryTags
      : String(profile?.categoryTagsText || "")
          .split(",")
          .map((tag) => tag.trim())
    )
      .map(runtime.toLower)
      .filter(Boolean);
  Object.assign(runtime, {
    profileCategoryTokens,
  });
  const lexicalRelevanceScore = ({ node, profile, tagsText }) => {
    const tokenPool = runtime
      .uniqueArray([
        ...runtime.tokenize(node?.namePl),
        ...runtime.tokenize(node?.descriptionPl),
        ...runtime.nodeReferenceTokens(node),
        ...runtime.tokenize(profile?.signboardName),
        ...runtime.profileAliasTokens(profile),
        ...runtime.profileCategoryTokens(profile),
        ...(node?.suggestionRules?.requiredTags || []).map(runtime.toLower),
        ...(node?.suggestionRules?.preferredItemClasses || []).map(
          runtime.toLower,
        ),
        ...(node?.suggestionRules?.preferredGenres || []).map(runtime.toLower),
      ])
      .filter(Boolean);
    if (!tokenPool.length) {
      return 0;
    }
    let weightedHits = 0;
    tokenPool.forEach((token) => {
      if (!token || !tagsText.includes(token)) {
        return;
      }
      weightedHits += runtime.clamp(token.length / 3.8, 0.9, 2.8);
    });
    const normalized = weightedHits / Math.max(1, tokenPool.length * 1.2);
    return runtime.clamp(normalized * 42, 0, 34);
  };
  Object.assign(runtime, {
    lexicalRelevanceScore,
  });
  const typeIdentityScore = ({ node, tagsText }) => {
    const referenceTokens = runtime.nodeReferenceTokens(node);
    if (!referenceTokens.length) {
      return 0;
    }
    let hits = 0;
    referenceTokens.forEach((token) => {
      if (token && tagsText.includes(token)) {
        hits += 1;
      }
    });
    return runtime.clamp(
      (hits / Math.max(1, referenceTokens.length * 0.45)) * 26,
      0,
      26,
    );
  };
  Object.assign(runtime, {
    typeIdentityScore,
  });
  const tokensForIconMatch = (value) =>
    runtime
      .tokenize(value)
      .map((entry) => entry.trim().toLowerCase())
      .filter(Boolean);
  Object.assign(runtime, {
    tokensForIconMatch,
  });
  const resolveDraftImgClass = ({
    name = "",
    description = "",
    itemClass = "",
    itemGenre = "",
    templates = [],
  }) => {
    const candidateTokens = new Set([
      ...runtime.tokensForIconMatch(name),
      ...runtime.tokensForIconMatch(description),
    ]);
    const classKey = runtime.toUpper(itemClass);
    const genreKey = runtime.toUpper(itemGenre);
    let bestMatch = null;
    let bestScore = Number.NEGATIVE_INFINITY;
    (templates || []).forEach((template) => {
      const templateClass = runtime.toUpper(template?.ITEM_CLASS);
      if (templateClass && classKey && templateClass !== classKey) {
        return;
      }
      const templateTokens = new Set(
        runtime.tokensForIconMatch(template?.NAME),
      );
      let score = 0;
      candidateTokens.forEach((token) => {
        if (templateTokens.has(token)) {
          score += 12;
        }
      });
      if (
        runtime.normalizeNameKey(template?.NAME) ===
        runtime.normalizeNameKey(name)
      ) {
        score += 100;
      }
      if (runtime.toUpper(template?.ITEM_GENRE) === genreKey) {
        score += 8;
      }
      if (score > bestScore && template?.IMG_CLASS) {
        bestScore = score;
        bestMatch = template;
      }
    });
    if (bestMatch?.IMG_CLASS && bestScore >= 18) {
      return runtime.resolveItemIconClass({
        ...bestMatch,
        NAME: name,
        DESCRIPTION: description,
        ITEM_CLASS: itemClass,
        ITEM_GENRE: itemGenre,
      });
    }
    return runtime.resolveItemIconClass({
      NAME: name,
      DESCRIPTION: description,
      ITEM_CLASS: itemClass,
      ITEM_GENRE: itemGenre,
      IMG_CLASS: runtime.draftImgClass[classKey] || "v0001",
    });
  };
  Object.assign(runtime, {
    resolveDraftImgClass,
  });
  const wealthPriceTargets = {
    nedzny: 44,
    biedny: 90,
    standard: 210,
    bogaty: 620,
    elitarny: 1380,
    luksusowy: 2500,
  };
  Object.assign(runtime, {
    wealthPriceTargets,
  });
  return {
    hashSeed,
    seededFloat,
    clamp,
    normalizeHeadWord,
    inferNameGrammar,
    materialEntryForVariant,
    buildMaterializedVariantName,
    profileCategoryTokens,
    lexicalRelevanceScore,
    typeIdentityScore,
    tokensForIconMatch,
    resolveDraftImgClass,
    wealthPriceTargets,
  };
};
