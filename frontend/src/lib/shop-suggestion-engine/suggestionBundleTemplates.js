export const buildTemplateRecommendations = (
  runtime,
  { templates, node, allowedClasses, profile, worldProfileMeta, ancestryIds },
) =>
  (templates || [])
    .filter((item) =>
      runtime.isSingleItemName(
        runtime.stripBannedSuggestionSuffixes(item?.NAME),
      ),
    )
    .filter((item) =>
      runtime.itemPassesHardRules({
        item,
        node,
        allowedClasses,
      }),
    )
    .map((item) => {
      const sanitizedTemplateName = runtime.stripBannedSuggestionSuffixes(
        String(item.NAME || `Template ${item.ID}`),
      );
      const itemClass = runtime.toUpper(item?.ITEM_CLASS);
      const itemGenre = String(item.ITEM_GENRE || "").toUpperCase();
      const quantityByClass =
        node?.suggestionRules?.defaultQuantityByClass || {};
      const quantity = Math.max(1, Number(quantityByClass[itemClass] || 1));
      const reasonBundle = runtime.buildSuggestionReason(
        item,
        node,
        profile,
        worldProfileMeta,
      );
      const suggestionId = `template:${item.ID}`;
      const scoreTieBreaker = runtime.scoreTieBreakerFor(suggestionId, profile);
      const scoreRaw =
        runtime.scoreTemplate({
          item,
          node,
          profile,
          worldProfileMeta,
          ancestryIds,
        }) + scoreTieBreaker;
      const score = Number(scoreRaw.toFixed(2));
      const recommendation = runtime.recommendationByScore(scoreRaw);
      const personalizedVariants =
        score > 0
          ? runtime.buildPersonalizedVariants({
              suggestionId,
              baseName: sanitizedTemplateName,
              baseDescription: String(item.DESCRIPTION || ""),
              basePrice: Number(item.PRIZE || 0),
              itemClass,
              itemGenre,
              profile,
              score: scoreRaw,
            })
          : [];
      return {
        suggestionId,
        templateId: Number(item.ID),
        displayName: sanitizedTemplateName,
        templateName: sanitizedTemplateName,
        imgClass: runtime.resolveItemIconClass(item),
        description: String(item.DESCRIPTION || ""),
        classKey: itemClass,
        genreKey: itemGenre,
        examples: runtime.examplesForClassGenre(itemClass, itemGenre),
        label: sanitizedTemplateName,
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
        action: "use_existing",
      };
    })
    .sort(runtime.suggestionScoreComparator);
