export const createRuntimePart9 = (runtime) => {
  const scoreTemplate = ({
    item,
    node,
    profile,
    worldProfileMeta,
    ancestryIds,
  }) => {
    const name = runtime.toLower(item?.NAME);
    const description = runtime.toLower(item?.DESCRIPTION);
    const tagsText = `${name} ${description}`;
    const itemClass = runtime.toUpper(item?.ITEM_CLASS);
    const itemGenre = runtime.toUpper(item?.ITEM_GENRE);
    const priceTier = runtime.priceTierForItem(item);
    const required = node?.suggestionRules?.requiredItemClasses || [];
    const preferred = node?.suggestionRules?.preferredItemClasses || [];
    const preferredGenres = node?.suggestionRules?.preferredGenres || [];
    const requiredTags = (node?.suggestionRules?.requiredTags || []).map(
      runtime.toLower,
    );
    const forbiddenTags = (node?.suggestionRules?.forbiddenTags || []).map(
      runtime.toLower,
    );
    const profileTags = runtime.profileCategoryTokens(profile);
    let score = 0;
    if (required.includes(itemClass)) {
      score += 100;
    }
    if (preferred.includes(itemClass)) {
      score += 60;
    }
    if (preferredGenres.includes(itemGenre)) {
      score += 40;
    }
    requiredTags.forEach((tag) => {
      if (tag && tagsText.includes(tag)) {
        score += 30;
      }
    });
    forbiddenTags.forEach((tag) => {
      if (tag && tagsText.includes(tag)) {
        score -= 200;
      }
    });
    profileTags.forEach((tag) => {
      if (tag && tagsText.includes(tag)) {
        score += 22;
      }
    });
    const aliasTokens = runtime.profileAliasTokens(profile);
    aliasTokens.forEach((token) => {
      if (token && tagsText.includes(token)) {
        score += 12;
      }
    });
    const nodeLegal = String(node?.legalStatus || "legal").toLowerCase();
    const profileLegal = String(profile?.legalStatus || "legal").toLowerCase();
    const legalHits = runtime.legalSignalScore(tagsText);
    score += runtime.legalContextScore(profileLegal, nodeLegal, legalHits);
    const reputationKey = String(
      profile?.reputation || "neutralna",
    ).toLowerCase();
    const reputationCfg =
      runtime.reputationBehavior[reputationKey] ||
      runtime.reputationBehavior.neutralna;
    score += legalHits.legalHits * reputationCfg.legalBonus * 1.3;
    score += legalHits.illegalHits * reputationCfg.illegalBonus * 1.3;
    if (Number(item?.CHARGE || 0) >= 120) {
      score += reputationCfg.qualityWeight * 1.8;
    } else if (Number(item?.CHARGE || 0) <= 30) {
      score -= Math.max(0, Math.round((reputationCfg.qualityWeight * 1.8) / 2));
    }
    score += legalHits.greyHits * 4;
    const wealthKey = String(profile?.wealthTier || "standard").toLowerCase();
    const wealthCfg =
      runtime.wealthPriceWeights[wealthKey] ||
      runtime.wealthPriceWeights.standard;
    score += Number(wealthCfg[priceTier] || 0) * 1.8;
    score += runtime.seasonalScore(
      profile?.seasonality,
      tagsText,
      node,
      ancestryIds,
    );
    score += runtime.locationContextScore(profile, tagsText, node);
    score += runtime.wealthContextScore(profile, tagsText);
    score += runtime.reputationContextScore(profile, tagsText);
    const worldProfiles = node?.worldProfiles || [];
    if (
      !worldProfiles.length ||
      worldProfiles.includes(profile?.worldProfileId)
    ) {
      score += 22;
    } else if (worldProfiles.length) {
      score -= 14;
    }
    const modifiers = worldProfileMeta?.modifiers || {};
    score += Number(modifiers?.classBoosts?.[itemClass] || 0) * 2.2;
    score += Number(modifiers?.genreBoosts?.[itemGenre] || 0) * 2.2;
    const tagBoosts = Array.isArray(modifiers?.tagBoosts)
      ? modifiers.tagBoosts
      : [];
    tagBoosts.forEach((entry) => {
      const tag = runtime.toLower(entry?.tag);
      if (tag && tagsText.includes(tag)) {
        score += Number(entry?.score || 0) * 1.8;
      }
    });
    score += Number(modifiers?.legalityBias?.[nodeLegal] || 0) * 1.8;
    score += Number(modifiers?.priceTierBoosts?.[priceTier] || 0) * 1.8;
    score += Number(
      modifiers?.seasonalityBoosts?.[
        String(profile?.seasonality || "").toLowerCase()
      ] || 0,
    );
    score += runtime.applyCounterfeitRisk(
      profile?.counterfeitRisk,
      itemClass,
      itemGenre,
      tagsText,
    );
    score += runtime.lexicalRelevanceScore({
      node,
      profile,
      tagsText,
    });
    score += runtime.typeIdentityScore({
      node,
      tagsText,
    });
    score += runtime.priceAndChargeFitScore({
      item,
      profile,
    });
    return score;
  };
  Object.assign(runtime, {
    scoreTemplate,
  });
  const pseudoItemFromSeed = (seed) => ({
    NAME: String(seed?.namePl || "").trim(),
    DESCRIPTION: String(seed?.descriptionPl || "").trim(),
    ITEM_CLASS: runtime.toUpper(seed?.itemClass || "TOOL"),
    ITEM_GENRE: runtime.toUpper(seed?.itemGenre || "UTILITY"),
    PRIZE: Number(
      runtime.priceTierToBrass[String(seed?.priceTier || "mid")] || 120,
    ),
    CHARGE: 20,
  });
  Object.assign(runtime, {
    pseudoItemFromSeed,
  });
  const draftReasonBundle = (
    seed,
    node,
    profile,
    worldProfileMeta,
    pseudoItem,
  ) => {
    const reasonBundle = runtime.buildSuggestionReason(
      pseudoItem,
      node,
      profile,
      worldProfileMeta,
    );
    const segmentLabelMap = {
      products: "produkt",
      ingredients: "składnik",
      equipment: "sprzęt",
    };
    const segment = String(seed?.segment || "products").toLowerCase();
    const segmentLabel = segmentLabelMap[segment] || "pozycja";
    const intro = `Brak gotowego szablonu; proponowany ${segmentLabel} dla typu sklepu ${node?.namePl || "Sklep"}.`;
    return {
      reasons: [intro, ...(reasonBundle.reasons || [])],
      reasonDetails: [
        {
          textPl: intro,
          refKey: "draftTemplate",
          refValue: String(node?.id || ""),
        },
        ...(reasonBundle.reasonDetails || []),
      ],
    };
  };
  Object.assign(runtime, {
    draftReasonBundle,
  });
  return {
    scoreTemplate,
    pseudoItemFromSeed,
    draftReasonBundle,
  };
};
