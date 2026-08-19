import {
  getCategoryClassCodes,
  getSubtypeGenreCodes,
} from "@/data/trade/iconTaxonomy";

export const createRuntimePart3 = (runtime) => {
  function buildName(tokens, matchedRule) {
    const consumed = new Set(matchedRule.tokens || []);
    const modifiers = runtime
      .uniqueEntries(
        tokens
          .filter((token) => !consumed.has(token))
          .map(runtime.translateToken)
          .filter((token) => token.length > 1),
      )
      .slice(0, 4);
    const baseName = matchedRule.name || "przedmiot";
    if (baseName === "przedmiot" && modifiers.length) {
      return runtime.capitalize(modifiers.join(" "));
    }
    return runtime.capitalize(
      runtime.uniqueEntries([baseName, ...modifiers]).join(" "),
    );
  }
  Object.assign(runtime, {
    buildName,
  });
  function buildDescription(name, sourceName, typeKeys, subtypeKeys) {
    const categories = runtime
      .uniqueEntries([
        ...typeKeys.map((key) => runtime.TYPE_LABELS_PL[key] || key),
        ...subtypeKeys.map((key) => runtime.SUBTYPE_LABELS_PL[key] || key),
      ])
      .join(" / ");
    return `Ikona przedmiotu: ${name}. Kategoria: ${categories || "Różne"}. Źródłowa nazwa z analizy: ${sourceName}.`;
  }
  Object.assign(runtime, {
    buildDescription,
  });
  function buildSpecialMarks(tokens, matchedRule, name) {
    return runtime
      .uniqueEntries([
        name.toLowerCase(),
        ...(matchedRule.tags || []),
        ...tokens.map(runtime.translateToken),
      ])
      .slice(0, 14)
      .join(", ");
  }
  Object.assign(runtime, {
    buildSpecialMarks,
  });
  function buildMetadata(iconClass, sourceName) {
    const manual = runtime.MANUAL_METADATA[iconClass];
    if (manual) {
      return {
        iconClass,
        sourceName,
        ...manual,
      };
    }
    const tokens = runtime.toTokens(sourceName);
    const matchedRule = runtime.findRule(tokens);
    const typeKeys = matchedRule.typeKeys || ["MISC"];
    const subtypeKeys = matchedRule.subtypeKeys || ["OTHER"];
    const itemClasses =
      matchedRule.itemClasses || getCategoryClassCodes(typeKeys[0]).slice(0, 1);
    const itemGenres =
      matchedRule.itemGenres ||
      getSubtypeGenreCodes(subtypeKeys[0]).slice(0, 1);
    const name = runtime.buildName(tokens, matchedRule);
    return {
      iconClass,
      sourceName,
      name,
      description: runtime.buildDescription(
        name,
        sourceName,
        typeKeys,
        subtypeKeys,
      ),
      specialMarks: runtime.buildSpecialMarks(tokens, matchedRule, name),
      typeKeys,
      subtypeKeys,
      itemClasses,
      itemGenres,
    };
  }
  Object.assign(runtime, {
    buildMetadata,
  });
  const inventoryIconMetadataMap = Object.freeze(
    Object.entries(runtime.iconSourceNames).reduce(
      (map, [iconClass, sourceName]) => {
        map[iconClass] = runtime.buildMetadata(iconClass, sourceName);
        return map;
      },
      {},
    ),
  );
  Object.assign(runtime, {
    inventoryIconMetadataMap,
  });
  const inventoryIconClasses = Object.freeze(
    Object.keys(runtime.iconSourceNames).sort(
      (left, right) => Number(left.slice(1)) - Number(right.slice(1)),
    ),
  );
  Object.assign(runtime, {
    inventoryIconClasses,
  });
  return {
    buildName,
    buildDescription,
    buildSpecialMarks,
    buildMetadata,
    inventoryIconMetadataMap,
    inventoryIconClasses,
  };
};
