export const createRuntimePart5Segment3 = (runtime) => {
  const buildSuggestionReason = (item, node, profile, worldProfileMeta) => {
    const reasons = [];
    const reasonDetails = [];
    const itemClass = runtime.toUpper(item.ITEM_CLASS);
    const itemGenre = runtime.toUpper(item.ITEM_GENRE);
    const required = node?.suggestionRules?.requiredItemClasses || [];
    const preferred = node?.suggestionRules?.preferredItemClasses || [];
    const preferredGenres = node?.suggestionRules?.preferredGenres || [];
    if (required.includes(itemClass)) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Klasa ${itemClass} jest wymagana dla tego typu sklepu.`,
        "suggestionRules.requiredItemClasses",
        itemClass,
      );
    }
    if (preferred.includes(itemClass)) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Klasa ${itemClass} jest preferowana.`,
        "suggestionRules.preferredItemClasses",
        itemClass,
      );
    }
    if (preferredGenres.includes(itemGenre)) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Genre ${itemGenre} pasuje do profilu sklepu.`,
        "suggestionRules.preferredGenres",
        itemGenre,
      );
    }
    if (String(profile?.locationType || "")) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Lokalizacja sklepu: ${profile.locationType}.`,
        "locationType",
        profile.locationType,
      );
    }
    if (String(profile?.legalStatus || "")) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Legalnosc sklepu: ${profile.legalStatus}.`,
        "legalStatus",
        profile.legalStatus,
      );
    }
    if (String(profile?.wealthTier || "")) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Zamoznosc sklepu: ${profile.wealthTier}.`,
        "wealthTier",
        profile.wealthTier,
      );
    }
    if (String(profile?.reputation || "")) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Reputacja sklepu: ${profile.reputation}.`,
        "reputation",
        profile.reputation,
      );
    }
    if (String(profile?.seasonality || "")) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Sezonowosc: ${profile.seasonality}.`,
        "seasonality",
        profile.seasonality,
      );
    }
    if (String(profile?.worldProfileId || "")) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Profil swiata: ${profile.worldProfileId}.`,
        "worldProfileId",
        profile.worldProfileId,
      );
    }
    if (String(worldProfileMeta?.impactSummaryPl || "")) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Wplyw profilu swiata: ${worldProfileMeta.impactSummaryPl}`,
        "worldProfileImpact",
        worldProfileMeta.impactSummaryPl,
      );
    }
    const profileTags = Array.isArray(profile?.categoryTags)
      ? profile.categoryTags
      : String(profile?.categoryTagsText || "")
          .split(",")
          .map(runtime.toLower)
          .filter(Boolean);
    if (profileTags.length) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Kategorie sklepu: ${profileTags.slice(0, 4).join(", ")}.`,
        "categoryTags",
        profileTags.slice(0, 4).join(", "),
      );
    }
    const examples = runtime.examplesForClassGenre(itemClass, itemGenre);
    if (examples.length) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Przyklady: ${examples.join(", ")}.`,
        "examples",
        examples.join(", "),
      );
    }
    const aliasTokens = runtime.profileAliasTokens(profile);
    if (aliasTokens.length) {
      runtime.appendReasonDetail(
        reasonDetails,
        reasons,
        `Aliasy szyldu wzmacniaja dopasowanie po slowach kluczowych: ${aliasTokens.slice(0, 4).join(", ")}.`,
        "signboardAltNames",
        aliasTokens.slice(0, 4).join(", "),
      );
    }
    return {
      reasons,
      reasonDetails,
    };
  };
  Object.assign(runtime, {
    buildSuggestionReason,
  });
  return {
    buildSuggestionReason,
  };
};
