export const buildDraftSuggestions = (
  runtime,
  { node, templates, profile, worldProfileMeta, ancestryIds, nextTemplateId },
) => {
  const seeds = runtime.ensureSeedPoolSize(
    runtime.buildSeedPoolForNode(node, templates),
  );
  const draftSuggestions = [];
  let nextId = Number(nextTemplateId) || 1;
  seeds.forEach((seed, index) => {
    const pseudoItem = runtime.pseudoItemFromSeed(seed);
    const itemClass = String(seed?.itemClass || "TOOL").toUpperCase();
    const itemGenre = String(seed?.itemGenre || "UTILITY").toUpperCase();
    const priceTier = String(seed?.priceTier || "mid").toLowerCase();
    const draftTemplate = {
      ID: nextId,
      NAME: String(seed?.namePl || `Szkic ${nextId}`),
      DESCRIPTION: String(seed?.descriptionPl || ""),
      DETAILS: "AUTO_DRAFT",
      ITEM_CLASS: itemClass,
      ITEM_ID: "",
      ITEM_GENRE: itemGenre,
      IMG_CLASS: runtime.resolveDraftImgClass({
        name: String(seed?.namePl || `Szkic ${nextId}`),
        description: String(seed?.descriptionPl || ""),
        itemClass,
        itemGenre,
        templates,
      }),
      PRIZE:
        runtime.priceTierToBrass[priceTier] || runtime.priceTierToBrass.mid,
      CHARGE: Math.max(1, Number(seed?.charge || pseudoItem.CHARGE || 10)),
      DRAFT: true,
    };
    const suggestionId = `draft:${nextId}:${index}`;
    const reasonBundle = runtime.draftReasonBundle(
      seed,
      node,
      profile,
      worldProfileMeta,
      pseudoItem,
    );
    const scoreTieBreaker = runtime.scoreTieBreakerFor(suggestionId, profile);
    const scoreRaw =
      runtime.scoreTemplate({
        item: pseudoItem,
        node,
        profile,
        worldProfileMeta,
        ancestryIds,
      }) +
      scoreTieBreaker +
      Number(
        runtime.seedSourceWeights[
          String(seed?.sourceType || "").toLowerCase()
        ] || 0,
      );
    const score = Number(scoreRaw.toFixed(2));
    const recommendation = runtime.recommendationByScore(scoreRaw);
    const personalizedVariants =
      score > 0
        ? runtime.buildPersonalizedVariants({
            suggestionId,
            baseName: draftTemplate.NAME,
            baseDescription: draftTemplate.DESCRIPTION,
            basePrice: Number(draftTemplate.PRIZE || 0),
            itemClass,
            itemGenre,
            profile,
            score: scoreRaw,
          })
        : [];
    const quantityByClass = node?.suggestionRules?.defaultQuantityByClass || {};
    const quantity = Math.max(
      1,
      Number(quantityByClass[itemClass] || seed?.defaultQuantity || 1),
    );
    draftSuggestions.push({
      suggestionId,
      draftTemplate,
      displayName: draftTemplate.NAME,
      templateName: draftTemplate.NAME,
      imgClass: draftTemplate.IMG_CLASS,
      description: draftTemplate.DESCRIPTION,
      classKey: itemClass,
      genreKey: itemGenre,
      examples: runtime.examplesForClassGenre(itemClass, itemGenre),
      label: draftTemplate.NAME,
      reason: reasonBundle.reasons,
      reasonDetails: reasonBundle.reasonDetails,
      personalizedVariants,
      scoreRaw,
      score,
      scoreTieBreaker,
      quantity,
      recommendationCode: recommendation.code,
      recommendationLabelPl: recommendation.labelPl,
      recommendationReasonPl: recommendation.reasonPl,
      recommendationWeight: recommendation.weight,
      action: "create_draft",
      segment: String(seed?.segment || "products"),
    });
    nextId += 1;
  });

  // Recommendations are a broader catalogue than the final assortment.  A
  // deterministic set of quality editions keeps that catalogue useful even
  // for narrowly described shop types with only a handful of seed products.
  return {
    draftSuggestions,
    nextId,
  };
};
