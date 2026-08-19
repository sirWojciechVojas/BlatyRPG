export const createContainersMethodsPart3Segment1 = () => {
  return {
    isTemplateStackable(template) {
      const stackableClasses = new Set(["ALCHEMY", "TOOL", "POTION"]);
      if (!template) {
        return false;
      }
      return (
        stackableClasses.has(template.ITEM_CLASS) ||
        stackableClasses.has(template.ITEM_GENRE)
      );
    },
    cloneStoreItem(item) {
      return JSON.parse(JSON.stringify(item));
    },
  };
};
