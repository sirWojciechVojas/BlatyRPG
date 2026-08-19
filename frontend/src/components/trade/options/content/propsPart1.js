export const createContentPropsPart1 = () => ({
  isGM: {
    type: Boolean,
    default: false,
  },
  isAssortmentMode: {
    type: Boolean,
    default: false,
  },
  isAssortmentToolsMode: {
    type: Boolean,
    default: false,
  },
  isShopAddEditMode: {
    type: Boolean,
    default: false,
  },
  shopName: {
    type: String,
    default: "",
  },
  leftFlankButtons: {
    type: Array,
    default: () => [],
  },
  rightFlankButtons: {
    type: Array,
    default: () => [],
  },
  showFieldEditDialog: {
    type: Boolean,
    default: false,
  },
  classEditMode: {
    type: String,
    default: "",
  },
  classEditType: {
    type: String,
    default: "",
  },
  classEditTarget: {
    type: String,
    default: "",
  },
  classEditDraftValue: {
    type: [String, Number],
    default: "",
  },
  classEditSearch: {
    type: String,
    default: "",
  },
  classEditSuggestions: {
    type: Array,
    default: () => [],
  },
  classEditValidationError: {
    type: String,
    default: "",
  },
  classEditItemClass: {
    type: String,
    default: "",
  },
  bgWalletBrass: {
    type: [Number, String],
    default: 0,
  },
  walletBalances: {
    type: Object,
    default: () => ({}),
  },
  currencyDefinitions: {
    type: Object,
    default: () => ({}),
  },
  activeSettlementCurrencyCode: {
    type: String,
    default: "generic",
  },
  activeBgName: {
    type: String,
    default: "",
  },
  activeBgOwner: {
    type: String,
    default: "BG1",
  },
  activeBgAvatar: {
    type: String,
    default: "",
  },
  lastTradeReceipt: {
    type: Object,
    default: null,
  },
  playerTransactions: {
    type: Array,
    default: () => [],
  },
  iconSize: {
    type: [Number, String],
    default: 34,
  },
  iconSizeMin: {
    type: [Number, String],
    default: 24,
  },
  iconSizeMax: {
    type: [Number, String],
    default: 64,
  },
  iconSizeDefault: {
    type: [Number, String],
    default: 34,
  },
  assortmentLeftContainerId: {
    type: [Number, String],
    default: null,
  },
  assortmentRightContainerId: {
    type: [Number, String],
    default: null,
  },
  assortmentSearch: {
    type: String,
    default: "",
  },
  assortmentLeftSelectedKeys: {
    type: Array,
    default: () => [],
  },
  assortmentRightSelectedKeys: {
    type: Array,
    default: () => [],
  },
  assortmentLeftTab: {
    type: String,
    default: "transfer",
  },
  assortmentRightTab: {
    type: String,
    default: "transfer",
  },
  gmMoveItemKey: {
    type: String,
    default: "",
  },
  gmMoveTargetContainerId: {
    type: [Number, String],
    default: null,
  },
  gmMoveQuantity: {
    type: [Number, String],
    default: 1,
  },
  shopBuyContainerId: {
    type: [Number, String],
    default: null,
  },
  shopBuyItemKey: {
    type: String,
    default: "",
  },
  shopBuyTargetContainerId: {
    type: [Number, String],
    default: null,
  },
  shopBuyQuantity: {
    type: [Number, String],
    default: 1,
  },
  containerUndoStack: {
    type: Array,
    default: () => [],
  },
  containerSelectOptions: {
    type: Array,
    default: () => [],
  },
  assortmentLeftItems: {
    type: Array,
    default: () => [],
  },
  assortmentRightItems: {
    type: Array,
    default: () => [],
  },
});
