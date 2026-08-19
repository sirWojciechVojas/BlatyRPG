export const createRuntimePart3 = (runtime) => {
  const resolveFamilyById = (familyId) => {
    const allFamilies = [
      ...Object.values(runtime.shopSignboardLexicon.familiesByTypeId || {}),
      ...Object.values(runtime.shopSignboardLexicon.familiesByGroupId || {}),
      ...Object.values(runtime.shopSignboardLexicon.familiesByDomainId || {}),
      runtime.shopSignboardLexicon.fallbackFamily,
    ].filter(Boolean);
    return allFamilies.find((entry) => entry.id === familyId) || null;
  };
  Object.assign(runtime, {
    resolveFamilyById,
  });
  const inferFamilyFromLabels = (context = {}) => {
    const haystack = runtime.toLower(
      [context.typeName, context.groupName, context.domainName]
        .filter(Boolean)
        .join(" "),
    );
    const hinted = runtime.FAMILY_HINTS.find((entry) =>
      entry.match.some((token) => haystack.includes(token)),
    );
    if (!hinted?.id) {
      return null;
    }
    return runtime.resolveFamilyById(hinted.id);
  };
  Object.assign(runtime, {
    inferFamilyFromLabels,
  });
  const resolveFamily = (context = {}) => {
    const typeId = runtime.toText(context.typeId);
    const groupId = runtime.toText(context.groupId);
    const domainId = runtime.toText(context.domainId);
    if (typeId && runtime.shopSignboardLexicon.familiesByTypeId[typeId]) {
      return runtime.shopSignboardLexicon.familiesByTypeId[typeId];
    }
    if (groupId && runtime.shopSignboardLexicon.familiesByGroupId[groupId]) {
      return runtime.shopSignboardLexicon.familiesByGroupId[groupId];
    }
    if (domainId && runtime.shopSignboardLexicon.familiesByDomainId[domainId]) {
      return runtime.shopSignboardLexicon.familiesByDomainId[domainId];
    }
    return (
      runtime.inferFamilyFromLabels(context) ||
      runtime.shopSignboardLexicon.fallbackFamily
    );
  };
  Object.assign(runtime, {
    resolveFamily,
  });
  const appendAddon = (base, addon) => {
    const normalizedBase = runtime.normalizeSpaces(base);
    const normalizedAddon = runtime.normalizeSpaces(addon);
    if (!normalizedAddon) {
      return normalizedBase;
    }
    const merged = runtime.normalizeSpaces(
      `${normalizedBase} ${normalizedAddon}`,
    );
    return merged;
  };
  Object.assign(runtime, {
    appendAddon,
  });
  const createBaseName = (patternId, family, context, randomFn) => {
    const owner = runtime.ownerLabel(context.ownerName);
    const role = runtime.roleHintForType(context, family, randomFn);
    const surname = runtime.pickRandom(
      family.professionSurnames || runtime.shopSignboardLexicon.commonSurnames,
      randomFn,
      "Huppa",
    );
    switch (patternId) {
      case "POD_SYMBOL":
        return `Pod ${runtime.pickRandom(family.symbolPhrases, randomFn, "Rynkiem")}`;
      case "U_OWNER_ROLE_SURNAME":
        return `U ${role} ${surname}`;
      case "U_OWNER_NAME": {
        const emblem = runtime.pickRandom(
          family.canonicalNames,
          randomFn,
          runtime.shortTypeLabel(context.typeName, "Sklep"),
        );
        return owner ? `${emblem} — ${owner}` : emblem;
      }
      case "EMBLEMATIC_PAIR":
        return runtime.pickRandom(
          family.emblematicPairs,
          randomFn,
          "Mlot i Kowadlo",
        );
      case "NUMBERED_EMBLEM":
        return runtime.pickRandom(
          family.numberedEmblems,
          randomFn,
          "Trzy Miary",
        );
      case "CANONICAL":
      default:
        return runtime.pickRandom(
          family.canonicalNames,
          randomFn,
          "Pod Czarnym Gryfem",
        );
    }
  };
  Object.assign(runtime, {
    createBaseName,
  });
  const applyContextVariant = (baseName, context, family, randomFn) => {
    const locationPool =
      runtime.shopSignboardLexicon.locationAddons[
        runtime.toLower(context.locationType)
      ] || [];
    const seasonalPool =
      runtime.shopSignboardLexicon.seasonalAddons[
        runtime.toLower(context.seasonality)
      ] || [];
    const worldPool =
      runtime.shopSignboardLexicon.worldProfileAddons[
        runtime.toLower(context.worldProfileId)
      ] || [];
    const legalPool =
      runtime.shopSignboardLexicon.toneLibrary.legalStatus[
        runtime.toLower(context.legalStatus)
      ] || [];
    const wealthPool =
      runtime.shopSignboardLexicon.toneLibrary.wealthTier[
        runtime.toLower(context.wealthTier)
      ] || [];
    const reputationPool =
      runtime.shopSignboardLexicon.toneLibrary.reputation[
        runtime.toLower(context.reputation)
      ] || [];
    const tonePool = runtime.uniq([
      ...legalPool,
      ...wealthPool,
      ...reputationPool,
    ]);
    const familyTone = runtime.pickRandom(tonePool, randomFn, "");
    const variantId = runtime.pickRandom(
      ["none", "location", "seasonal", "world", "location_world", "tone_hint"],
      randomFn,
      "none",
    );
    let nextName = runtime.normalizeSpaces(baseName);
    if (variantId === "location") {
      nextName = runtime.appendAddon(
        nextName,
        runtime.pickRandom(locationPool, randomFn, ""),
      );
    } else if (variantId === "seasonal") {
      nextName = runtime.appendAddon(
        nextName,
        runtime.pickRandom(seasonalPool, randomFn, ""),
      );
    } else if (variantId === "world") {
      nextName = runtime.appendAddon(
        nextName,
        runtime.pickRandom(worldPool, randomFn, ""),
      );
    } else if (variantId === "location_world") {
      nextName = runtime.appendAddon(
        nextName,
        runtime.pickRandom(locationPool, randomFn, ""),
      );
      nextName = runtime.appendAddon(
        nextName,
        runtime.pickRandom(worldPool, randomFn, ""),
      );
    } else if (variantId === "tone_hint" && familyTone) {
      const tonePattern = runtime.pickRandom(["pod", "u"], randomFn, "pod");
      nextName =
        tonePattern === "u" && runtime.hasOwnerName(context.ownerName)
          ? `${nextName} — ${runtime.ownerLabel(context.ownerName)}`
          : `${nextName} - ${familyTone}`;
    }
    const mandatoryContextTone =
      (runtime.toLower(context.legalStatus) !== "legal" &&
        runtime.pickRandom(legalPool, randomFn, "")) ||
      (runtime.toLower(context.wealthTier) !== "standard" &&
        runtime.pickRandom(wealthPool, randomFn, "")) ||
      (runtime.toLower(context.reputation) !== "neutralna" &&
        runtime.pickRandom(reputationPool, randomFn, "")) ||
      "";
    if (
      mandatoryContextTone &&
      !runtime.toLower(nextName).includes(runtime.toLower(mandatoryContextTone))
    ) {
      nextName = `${nextName} - ${mandatoryContextTone}`;
    }
    return {
      name: runtime.normalizeSpaces(nextName),
      variantId,
    };
  };
  Object.assign(runtime, {
    applyContextVariant,
  });
  const isLowQualityPhrase = (value) => {
    const normalized = runtime.normalizeSpaces(value);
    return runtime.LOW_QUALITY_PATTERNS.some((pattern) => {
      pattern.lastIndex = 0;
      return pattern.test(normalized);
    });
  };
  Object.assign(runtime, {
    isLowQualityPhrase,
  });
  const normalizeNameWithGuards = (candidate, qualityGuardsApplied = []) => {
    let next = runtime.normalizeSpaces(candidate);
    if (!next) {
      qualityGuardsApplied.push("empty_candidate");
      return "";
    }
    runtime.BAD_DOUBLE_PATTERNS.forEach(({ pattern, replacement }) => {
      pattern.lastIndex = 0;
      if (pattern.test(next)) {
        qualityGuardsApplied.push(`guard_fix:${pattern.source}`);
        next = next.replace(pattern, replacement);
        next = runtime.normalizeSpaces(next);
      }
    });
    if (runtime.isLowQualityPhrase(next)) {
      qualityGuardsApplied.push("guard_block:low_quality");
      return "";
    }
    if (next.length < 6) {
      qualityGuardsApplied.push("guard_block:too_short");
      return "";
    }
    return next;
  };
  Object.assign(runtime, {
    normalizeNameWithGuards,
  });
  const createAliases = ({
    signboardName,
    context,
    family,
    locationAddon,
    seasonalAddon,
  }) => {
    const typeLabel = runtime.shortTypeLabel(context.typeName, "Sklep");
    const core = runtime.normalizeSpaces(
      String(signboardName || "")
        .replace(/^Pod\s+/i, "")
        .replace(/^U\s+/i, ""),
    );
    const rawOwner = runtime.ownerLabel(context.ownerName);
    const owner = runtime.isLowQualityPhrase(rawOwner) ? "" : rawOwner;
    const tokenMap = {
      "{TYPE}": typeLabel,
      "{OWNER_NAME}": owner,
      "{LOCATION_ADDON}": runtime.normalizeSpaces(locationAddon),
      "{SEASONAL_ADDON}": runtime.normalizeSpaces(seasonalAddon),
      "{SIGNBOARD_CORE}": core,
    };
    const expand = (pattern) => {
      let next = String(pattern || "");
      if (next.includes("{OWNER_NAME}") && !owner) {
        return "";
      }
      Object.entries(tokenMap).forEach(([token, replacement]) => {
        next = next.replaceAll(token, replacement || "");
      });
      return runtime.normalizeSpaces(next);
    };
    const pool = runtime.uniq([
      ...family.aliasPatterns,
      ...runtime.shopSignboardLexicon.globalAliasPatterns,
      "{TYPE} „{SIGNBOARD_CORE}”",
      "{OWNER_NAME} — {TYPE}",
    ]);
    const aliases = [];
    pool.forEach((pattern) => {
      const value = expand(pattern);
      if (value && value !== signboardName && !aliases.includes(value)) {
        aliases.push(value);
      }
    });
    while (aliases.length < 3) {
      aliases.push(`${typeLabel} ${runtime.toRoman(aliases.length + 2)}`);
    }
    return aliases.slice(0, 6);
  };
  Object.assign(runtime, {
    createAliases,
  });
  return {
    resolveFamilyById,
    inferFamilyFromLabels,
    resolveFamily,
    appendAddon,
    createBaseName,
    applyContextVariant,
    isLowQualityPhrase,
    normalizeNameWithGuards,
    createAliases,
  };
};
