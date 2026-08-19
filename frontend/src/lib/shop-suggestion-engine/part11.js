export const createRuntimePart11 = (runtime) => {
  const generateShopSuggestions = (payload) =>
    runtime.generateShopSuggestionBundle(payload).suggestions;
  Object.assign(runtime, {
    generateShopSuggestions,
  });
  return {
    generateShopSuggestions,
  };
};
