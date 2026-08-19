export const createContentPropsPart3 = () => ({
  inventoryOwnerOptions: {
    type: Array,
    default: () => [],
  },
  trashZoneOptions: {
    type: Array,
    default: () => [],
  },
  trashSlotCapacity: {
    type: [Number, String],
    default: 16,
  },
  trashZoneLoad: {
    type: [Number, String],
    default: 0,
  },
  buyItemClass: {
    type: String,
    default: "",
  },
  sellItemClass: {
    type: String,
    default: "",
  },
  selectedBuyIds: {
    type: Array,
    default: () => [],
  },
  selectedSellIds: {
    type: Array,
    default: () => [],
  },
  buyActId: {
    type: [Number, String],
    default: null,
  },
  sellActId: {
    type: [Number, String],
    default: null,
  },
  buyTotalBrass: {
    type: [Number, String],
    default: 0,
  },
  sellTotalBrass: {
    type: [Number, String],
    default: 0,
  },
  buyActionLabel: {
    type: String,
    default: "",
  },
  sellActionLabel: {
    type: String,
    default: "",
  },
  buyActionEnabled: {
    type: Boolean,
    default: false,
  },
  sellActionEnabled: {
    type: Boolean,
    default: false,
  },
  loadingBuy: {
    type: Boolean,
    default: false,
  },
  loadingSell: {
    type: Boolean,
    default: false,
  },
  errorBuy: {
    type: String,
    default: "",
  },
  errorSell: {
    type: String,
    default: "",
  },
  indFieldNames: {
    type: Array,
    default: () => [],
  },
  tempFieldNames: {
    type: Array,
    default: () => [],
  },
  inventoryForm: {
    type: Object,
    default: () => ({}),
  },
  templateForm: {
    type: Object,
    default: () => ({}),
  },
  newTemplateForm: {
    type: Object,
    default: () => ({}),
  },
  templateFormErrors: {
    type: Object,
    default: () => ({}),
  },
  newTemplateFormErrors: {
    type: Object,
    default: () => ({}),
  },
  inventoryFormErrors: {
    type: Object,
    default: () => ({}),
  },
  showClassEditDialog: {
    type: Boolean,
    default: false,
  },
  showWeaponStatsDialog: {
    type: Boolean,
    default: false,
  },
  showOwnerOptDialog: {
    type: Boolean,
    default: false,
  },
  showItemDetailDialog: {
    type: Boolean,
    default: false,
  },
  showSuggestionDetailDialog: {
    type: Boolean,
    default: false,
  },
  showAssortmentMergeDialog: {
    type: Boolean,
    default: false,
  },
  showViewSettingsDialog: {
    type: Boolean,
    default: false,
  },
  imgClassOptions: {
    type: Array,
    default: () => [],
  },
  selectedImgClass: {
    type: String,
    default: "",
  },
  ownerOptOptions: {
    type: Array,
    default: () => [],
  },
  selectedOwnerOpt: {
    type: String,
    default: "",
  },
  displayOwnerOpt: {
    type: String,
    default: "",
  },
  weaponStatsDraft: {
    type: Object,
    default: () => ({}),
  },
  weaponStatsItemOptions: {
    type: Array,
    default: () => [],
  },
  weaponFeatureOptions: {
    type: Array,
    default: () => [],
  },
  weaponStatsSourceType: {
    type: String,
    default: "template",
  },
  itemDetailItem: {
    type: Object,
    default: null,
  },
  itemDetailDisplayName: {
    type: String,
    default: "",
  },
  itemDetailChargeLabel: {
    type: String,
    default: "",
  },
  itemDetailSourceLabel: {
    type: String,
    default: "",
  },
});
