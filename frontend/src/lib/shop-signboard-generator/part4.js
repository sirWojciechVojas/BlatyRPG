export const createRuntimePart4 = (runtime) => {
  const generateShopSignboard = (context = {}, options = {}) => {
    const randomFn =
      typeof options?.randomFn === "function" ? options.randomFn : Math.random;
    const mode = runtime.toLower(options?.mode || "mixed");
    const style = runtime.toLower(options?.style || "medieval_lore");
    const family = runtime.resolveFamily(context);
    const familyId = family?.id || "family_fallback";
    const historyKey = runtime.toHistoryKey(context, familyId);
    const generatedHistory = runtime.getOrCreateHistory(historyKey);
    const anchors = runtime.anchorsForType(context, family);
    const existing = new Set(
      runtime
        .uniq([
          ...(context.existingNames || []),
          ...Array.from(generatedHistory),
        ])
        .map((entry) => runtime.toLower(entry)),
    );
    const qualityGuardsApplied = [];
    const attemptCount = 24;
    let selectedName = "";
    let patternId = "CANONICAL";
    let variantId = "none";
    const seedHash = runtime.hashString(
      [
        context.typeId,
        context.groupId,
        context.domainId,
        context.locationType,
      ].join("|"),
    );
    const preferredPattern = runtime.pickByHash(
      family.patterns,
      seedHash,
      17,
      "CANONICAL",
    );
    for (let attempt = 0; attempt < attemptCount; attempt += 1) {
      patternId =
        mode === "mixed"
          ? runtime.pickRandom(family.patterns, randomFn, preferredPattern)
          : preferredPattern;
      const baseName = runtime.createBaseName(
        patternId,
        family,
        context,
        randomFn,
      );
      const variant = runtime.applyContextVariant(
        baseName,
        context,
        family,
        randomFn,
      );
      variantId = variant.variantId || "none";
      const normalized = runtime.normalizeNameWithGuards(
        variant.name,
        qualityGuardsApplied,
      );
      if (!normalized) {
        continue;
      }
      if (!runtime.nameMatchesTypeAnchors(normalized, anchors)) {
        qualityGuardsApplied.push("guard_skip:type_mismatch");
        continue;
      }
      if (!existing.has(runtime.toLower(normalized))) {
        selectedName = normalized;
        break;
      }
    }
    let collisionResolved = false;
    if (!selectedName) {
      const fallbackBase =
        runtime.createBaseName(
          "U_OWNER_ROLE_SURNAME",
          family,
          context,
          randomFn,
        ) ||
        runtime.pickByHash(family.canonicalNames, seedHash, 29, "Nowy Sklep");
      const locationHint = runtime.pickByHash(
        runtime.shopSignboardLexicon.locationAddons[
          runtime.toLower(context.locationType)
        ] || [],
        seedHash,
        31,
        "",
      );
      let candidate = runtime.normalizeNameWithGuards(
        runtime.appendAddon(fallbackBase, locationHint),
        qualityGuardsApplied,
      );
      if (!candidate) {
        candidate = "Nowy Sklep";
      }
      let ordinal = 2;
      while (existing.has(runtime.toLower(candidate)) && ordinal <= 40) {
        candidate = runtime.normalizeSpaces(
          `${fallbackBase} ${runtime.toRoman(ordinal)}`,
        );
        ordinal += 1;
      }
      selectedName = candidate;
      variantId = "collision_fallback";
      collisionResolved = true;
    }
    generatedHistory.add(selectedName);
    const locationAddon = runtime.pickRandom(
      runtime.shopSignboardLexicon.locationAddons[
        runtime.toLower(context.locationType)
      ] || [],
      randomFn,
      "",
    );
    const seasonalAddon = runtime.pickRandom(
      runtime.shopSignboardLexicon.seasonalAddons[
        runtime.toLower(context.seasonality)
      ] || [],
      randomFn,
      "",
    );
    const aliases = runtime.createAliases({
      signboardName: selectedName,
      context,
      family,
      locationAddon,
      seasonalAddon,
    });
    return {
      signboardName: selectedName || "Nowy Sklep",
      aliases,
      meta: {
        familyId,
        patternId,
        variantId,
        collisionResolved,
        qualityGuardsApplied: runtime.uniq(qualityGuardsApplied),
        mode,
        style,
      },
    };
  };
  Object.assign(runtime, {
    generateShopSignboard,
  });
  const __resetShopSignboardGeneratorState = () => {
    runtime.ROLL_HISTORY_BY_TYPE.clear();
  };
  Object.assign(runtime, {
    __resetShopSignboardGeneratorState,
  });
  return {
    generateShopSignboard,
    __resetShopSignboardGeneratorState,
  };
};
