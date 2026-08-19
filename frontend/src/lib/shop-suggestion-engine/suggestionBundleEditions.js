export const buildEditionSuggestions = (
  runtime,
  { draftSuggestions, nextId, profile },
) => {
  // Recommendations are a broader catalogue than the final assortment.  A
  // deterministic set of quality editions keeps that catalogue useful even
  // for narrowly described shop types with only a handful of seed products.
  const catalogEditions = ["podstawowe", "staranne"];
  const editionDraftSuggestions = draftSuggestions.flatMap((entry) =>
    catalogEditions.map((edition, editionIndex) => {
      const sourceTemplate = entry.draftTemplate || {};
      const suggestionId = `${entry.suggestionId}:edition:${editionIndex + 1}`;
      const displayName = `${entry.displayName} — wykonanie ${edition}`;
      const priceFactor = editionIndex === 0 ? 0.9 : 1.15;
      const draftTemplate = {
        ...sourceTemplate,
        ID: nextId++,
        NAME: displayName,
        PRIZE: Math.max(
          1,
          Math.round(Number(sourceTemplate.PRIZE || 1) * priceFactor),
        ),
      };
      const scoreTieBreaker = runtime.scoreTieBreakerFor(suggestionId, profile);
      const scoreRaw =
        Number(entry.scoreRaw || 0) - 8 - editionIndex * 4 + scoreTieBreaker;
      const score = Number(scoreRaw.toFixed(2));
      const recommendation = runtime.recommendationByScore(scoreRaw);
      return {
        ...entry,
        suggestionId,
        draftTemplate,
        displayName,
        templateName: displayName,
        label: displayName,
        scoreRaw,
        score,
        scoreTieBreaker,
        recommendationCode: recommendation.code,
        recommendationLabelPl: recommendation.labelPl,
        recommendationReasonPl: recommendation.reasonPl,
        recommendationWeight: recommendation.weight,
        personalizedVariants: runtime.buildPersonalizedVariants({
          suggestionId,
          baseName: displayName,
          baseDescription: draftTemplate.DESCRIPTION,
          basePrice: draftTemplate.PRIZE,
          itemClass: entry.classKey,
          itemGenre: entry.genreKey,
          profile,
          score: scoreRaw,
        }),
      };
    }),
  );
  return editionDraftSuggestions;
};
