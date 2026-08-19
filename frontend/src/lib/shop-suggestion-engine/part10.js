import { buildTemplateRecommendations } from "./suggestionBundleTemplates";
import { buildDraftSuggestions } from "./suggestionBundleDrafts";
import { buildEditionSuggestions } from "./suggestionBundleEditions";
import { rankSuggestionBundle } from "./suggestionBundleRanking";
export const createRuntimePart10 = (runtime) => {
  const generateShopSuggestionBundle = ({
    templates = [],
    catalogNodes = [],
    profile = null,
    nextTemplateId = 1,
  }) => {
    const node = (catalogNodes || []).find(
      (entry) => entry.id === profile?.typeId,
    );
    if (!node) {
      return {
        suggestions: [],
        recommendations: [],
      };
    }
    const allowedClasses = runtime.allowedClassSetForNode(node);
    const nodesMap = runtime.nodeByIdMap(catalogNodes);
    const ancestryIds = runtime.nodeAncestryIds(node, nodesMap);
    const worldProfileMeta = runtime.worldProfileMetaFor(
      profile?.worldProfileId,
    );
    const templateRecommendations = buildTemplateRecommendations(runtime, {
      templates,
      node,
      allowedClasses,
      profile,
      worldProfileMeta,
      ancestryIds,
    });
    const { draftSuggestions, nextId } = buildDraftSuggestions(runtime, {
      node,
      templates,
      profile,
      worldProfileMeta,
      ancestryIds,
      nextTemplateId,
    });
    const editionDraftSuggestions = buildEditionSuggestions(runtime, {
      draftSuggestions,
      nextId,
      profile,
    });
    return rankSuggestionBundle(runtime, {
      templateRecommendations,
      draftSuggestions,
      editionDraftSuggestions,
      node,
    });
  };
  Object.assign(runtime, {
    generateShopSuggestionBundle,
  });
  return {
    generateShopSuggestionBundle,
  };
};
