export const rankSuggestionBundle = (
  runtime,
  { templateRecommendations, draftSuggestions, editionDraftSuggestions, node },
) => {
  const rankingComparator = (a, b) =>
    Number(b.recommendationWeight || 0) - Number(a.recommendationWeight || 0) ||
    runtime.suggestionScoreComparator(a, b);
  const allRanked = runtime.dedupeSuggestionEntries(
    [
      ...templateRecommendations,
      ...draftSuggestions,
      ...editionDraftSuggestions,
    ].sort(rankingComparator),
  );
  const positivesAdd = allRanked.filter(
    (entry) => entry.recommendationCode === "add",
  );
  const suggestionTarget = Math.max(
    runtime.SUGGESTIONS_TARGET_MIN,
    Math.min(
      runtime.SUGGESTIONS_TARGET_MAX,
      runtime.SUGGESTIONS_TARGET_DEFAULT + Math.floor(positivesAdd.length / 18),
    ),
  );
  const suggestions = runtime.selectBalancedSuggestions({
    entries: allRanked,
    node,
    suggestionTarget,
  });
  const recommendationTarget = Math.min(
    runtime.RECOMMENDATIONS_MAX,
    Math.max(suggestions.length * runtime.RECOMMENDATION_MULTIPLIER, 60),
  );
  const templateSuggestionKeys = new Set(
    templateRecommendations.map((entry) =>
      runtime.suggestionDistinctKey(entry),
    ),
  );
  const seenRecommendationLabels = new Set();
  const recommendations = [
    ...templateRecommendations,
    ...allRanked.filter(
      (entry) =>
        !templateSuggestionKeys.has(runtime.suggestionDistinctKey(entry)),
    ),
  ]
    .filter((entry) => {
      const labelKey = runtime.normalizeNameKey(
        runtime.suggestionLabelForSort(entry),
      );
      if (!labelKey || seenRecommendationLabels.has(labelKey)) {
        return false;
      }
      seenRecommendationLabels.add(labelKey);
      return true;
    })
    .slice(0, recommendationTarget);
  return {
    suggestions,
    recommendations,
  };
};
