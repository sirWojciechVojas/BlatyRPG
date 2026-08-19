const baseRules = {
  requiredItemClasses: [],
  preferredItemClasses: [],
  preferredGenres: [],
  requiredTags: [],
  forbiddenTags: [],
  defaultQuantityByClass: {},
  draftPriceTier: "mid",
};
const domain = (id, namePl, nameEn) => ({
  id,
  parentId: null,
  level: "domain",
  namePl,
  nameEn,
  aliasesPl: [],
  descriptionPl: namePl,
  typicalLocations: [],
  worldProfiles: [],
  legalStatus: "legal",
  traits: [],
  suggestionRules: {
    ...baseRules,
  },
});
const group = (id, parentId, namePl, descriptionPl) => ({
  id,
  parentId,
  level: "group",
  namePl,
  aliasesPl: [],
  descriptionPl,
  typicalLocations: [],
  worldProfiles: [],
  legalStatus: "legal",
  traits: [],
  suggestionRules: {
    ...baseRules,
  },
});
const type = ({
  id,
  parentId,
  namePl,
  descriptionPl,
  typicalLocations,
  legalStatus = "legal",
  worldProfiles = [],
  requiredItemClasses = [],
  preferredItemClasses = [],
  preferredGenres = [],
  requiredTags = [],
  forbiddenTags = [],
  strictClassRules = false,
  draftPriceTier = "mid",
  articleSeeds = [],
}) => ({
  id,
  parentId,
  level: "type",
  namePl,
  aliasesPl: [],
  descriptionPl,
  typicalLocations,
  worldProfiles,
  legalStatus,
  traits: [],
  suggestionRules: {
    ...baseRules,
    requiredItemClasses,
    preferredItemClasses,
    preferredGenres,
    requiredTags,
    forbiddenTags,
    strictClassRules,
    draftPriceTier,
    defaultQuantityByClass: requiredItemClasses.reduce((acc, key) => {
      acc[key] = 2;
      return acc;
    }, {}),
  },
  articleSeeds,
});
export { domain, group, type };
