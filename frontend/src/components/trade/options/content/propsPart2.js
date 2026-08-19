export const createContentPropsPart2 = () => ({
  gmMoveItemOptions: {
    type: Array,
    default: () => [],
  },
  gmMoveQuantityEnabled: {
    type: Boolean,
    default: false,
  },
  gmMoveQuantityMax: {
    type: [Number, String],
    default: 1,
  },
  containerOverview: {
    type: Array,
    default: () => [],
  },
  shopContainerOptions: {
    type: Array,
    default: () => [],
  },
  shopBuyItemOptions: {
    type: Array,
    default: () => [],
  },
  characterContainerOptions: {
    type: Array,
    default: () => [],
  },
  shopBuyQuantityEnabled: {
    type: Boolean,
    default: false,
  },
  shopEditorShopOptions: {
    type: Array,
    default: () => [],
  },
  canDeleteActiveShop: {
    type: Boolean,
    default: false,
  },
  activeShopId: {
    type: [Number, String],
    default: null,
  },
  shopTypeOptions: {
    type: Array,
    default: () => [],
  },
  shopOwnerOptions: {
    type: Array,
    default: () => [],
  },
  worldProfileOptions: {
    type: Array,
    default: () => [],
  },
  shopWorldProfileImpactText: {
    type: String,
    default: "",
  },
  locationTypeOptions: {
    type: Array,
    default: () => [],
  },
  legalStatusOptions: {
    type: Array,
    default: () => [],
  },
  wealthTierOptions: {
    type: Array,
    default: () => [],
  },
  reputationOptions: {
    type: Array,
    default: () => [],
  },
  seasonalityOptions: {
    type: Array,
    default: () => [],
  },
  shopEditorForm: {
    type: Object,
    default: () => ({}),
  },
  activeShopProfile: {
    type: Object,
    default: null,
  },
  templateItems: {
    type: Array,
    default: () => [],
  },
  shopItems: {
    type: Array,
    default: () => [],
  },
  catalogNodes: {
    type: Array,
    default: () => [],
  },
  worldProfiles: {
    type: Array,
    default: () => [],
  },
  shopAutoTagsText: {
    type: String,
    default: "",
  },
  activeShopTypeNode: {
    type: Object,
    default: null,
  },
  shopSuggestions: {
    type: Array,
    default: () => [],
  },
  shopTemplateRecommendations: {
    type: Array,
    default: () => [],
  },
  selectedSuggestionIds: {
    type: Array,
    default: () => [],
  },
  showShopActivationDialog: {
    type: Boolean,
    default: false,
  },
  shopActivationOptions: {
    type: Array,
    default: () => [],
  },
  shopActivationActiveCount: {
    type: [Number, String],
    default: 0,
  },
  assortmentRollTarget: {
    type: [Number, String],
    default: 12,
  },
  assortmentRollPreview: {
    type: Array,
    default: () => [],
  },
  assortmentRollPreviewMeta: {
    type: Object,
    default: null,
  },
  showBuyForm: {
    type: Boolean,
    default: false,
  },
  showSellForm: {
    type: Boolean,
    default: false,
  },
  showSellAddForm: {
    type: Boolean,
    default: false,
  },
  isTrashMode: {
    type: Boolean,
    default: false,
  },
  buyListTitle: {
    type: String,
    default: "",
  },
  sellListTitle: {
    type: String,
    default: "",
  },
  buyItems: {
    type: Array,
    default: () => [],
  },
  sellItems: {
    type: Array,
    default: () => [],
  },
  trashZoneOwnerCode: {
    type: String,
    default: "TRASH",
  },
  inventoryOwnerCodeFilter: {
    type: String,
    default: "all",
  },
  inventoryOwnerFilterOptions: {
    type: Array,
    default: () => [],
  },
});
