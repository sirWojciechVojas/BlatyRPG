export const createContentPropsPart4 = () => ({
  itemDetailCanEditNickname: {
    type: Boolean,
    default: false,
  },
  itemDetailNickname: {
    type: String,
    default: "",
  },
  itemDetailNicknameModeLabel: {
    type: String,
    default: "",
  },
  itemDetailPersonalCost: {
    type: Object,
    default: null,
  },
  itemDetailPersonalCostRaw: {
    type: [Number, String],
    default: "",
  },
  itemDetailChargeText: {
    type: String,
    default: "",
  },
  itemDetailMetaLines: {
    type: Array,
    default: () => [],
  },
  itemDetailMetaSections: {
    type: Array,
    default: () => [],
  },
  itemDetailImageSrc: {
    type: String,
    default: "",
  },
  suggestionDetailEntry: {
    type: Object,
    default: null,
  },
  suggestionDetailVariantId: {
    type: String,
    default: "",
  },
  canMergeAssortmentSelection: {
    type: Boolean,
    default: false,
  },
  assortmentMergeFieldDefinitions: {
    type: Array,
    default: () => [],
  },
  assortmentMergeLeftItem: {
    type: Object,
    default: null,
  },
  assortmentMergeRightItem: {
    type: Object,
    default: null,
  },
  assortmentMergeChoices: {
    type: Object,
    default: () => ({}),
  },
  fieldInputType: {
    type: Function,
    required: true,
  },
  isReadOnlyField: {
    type: Function,
    required: true,
  },
  legacyIconClassForItem: {
    type: Function,
    required: true,
  },
  itemImageSrcForItem: {
    type: Function,
    default: () => "",
  },
  containerLabelById: {
    type: Function,
    required: true,
  },
  filteredContainerItems: {
    type: Function,
    required: true,
  },
  isContainerSelected: {
    type: Function,
    required: true,
  },
  containerItemInlineLabel: {
    type: Function,
    required: true,
  },
  ownerOptDescription: {
    type: Function,
    required: true,
  },
  iconStyle: {
    type: Function,
    required: true,
  },
  formatCoin: {
    type: Function,
    required: true,
  },
  showTempHidden: {
    type: Function,
    required: true,
  },
  selectedBuyQuantityForItem: {
    type: Function,
    default: () => 1,
  },
  selectedSellQuantityForItem: {
    type: Function,
    default: () => 1,
  },
  canAdjustBuySelectionQuantity: {
    type: Function,
    default: () => false,
  },
  canAdjustSellSelectionQuantity: {
    type: Function,
    default: () => false,
  },
  retryTradeDataLoad: {
    type: Function,
    default: null,
  },
  openAssortmentMergeDialog: {
    type: Function,
    default: null,
  },
  closeAssortmentMergeDialog: {
    type: Function,
    default: null,
  },
  setAssortmentMergeChoice: {
    type: Function,
    default: null,
  },
  assortmentMergeChoiceFor: {
    type: Function,
    default: () => "left",
  },
  confirmAssortmentMergeDialog: {
    type: Function,
    default: null,
  },
  shopSuggestionReasonText: {
    type: Function,
    default: null,
  },
  shopSuggestionReasonDetailsText: {
    type: Function,
    default: null,
  },
  updateShopEditorField: {
    type: Function,
    default: null,
  },
  rollShopSignboard: {
    type: Function,
    default: null,
  },
  changeShopForEditor: {
    type: Function,
    default: null,
  },
  createShopForEditor: {
    type: Function,
    default: null,
  },
  deleteActiveShopForEditor: {
    type: Function,
    default: null,
  },
  openShopActivationDialogForEditor: {
    type: Function,
    default: null,
  },
  closeShopActivationDialog: {
    type: Function,
    default: null,
  },
  toggleShopActivationForEditor: {
    type: Function,
    default: null,
  },
});
