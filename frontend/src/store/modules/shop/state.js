const createPermissionsState = () => ({
  isGm: false,
  canManageShops: false,
  canManageCatalog: false,
  canManageContainers: false,
  canViewFullLedger: false,
  canTrade: false,
  ownerCodes: [],
});

export const createSessionState = ({ campaignId, gmMode }) => ({
  campaignId,
  apiStatus: "idle",
  apiError: null,
  tradeDataLoaded: false,
  tradeDataCacheKey: "",
  tradeDataCacheVersion: 0,
  isGM: false,
  gmMode,
  context: null,
  actors: [],
  permissions: createPermissionsState(),
});

export const createCatalogState = ({ pricingConfig }) => ({
  shops: [],
  activeShopId: null,
  shopName: "",
  templateItems: [],
  archivedTemplateItems: [],
  selectedTemplateId: null,
  shopProfiles: {},
  shopTypes: [],
  catalogNodes: [],
  itemDictionaries: {
    icon_categories: [],
    icon_subcategories: [],
    classes: [],
    genres: [],
    attributes: [],
  },
  currencyDefinitions: {
    systemCode: "generic",
    defaultCurrencyCode: "generic",
    currencies: [],
  },
  mechanics: {
    encumbrance: { unit: "", unitShort: "", allowCustom: true, presets: [] },
  },
  worldProfiles: [],
  shopSuggestions: [],
  shopTemplateRecommendations: [],
  shopEditorState: {
    typeId: "",
    signboardName: "",
    ownerCode: "BG1",
    ownerName: "",
    signboardAltNamesText: "",
    categoryTagsText: "",
    worldProfileId: "standard",
    locationType: "miasto",
    legalStatus: "legal",
    wealthTier: "standard",
    reputation: "neutralna",
    seasonality: "caloroczny",
    counterfeitRisk: 10,
    pricingConfig,
    selectedSuggestionIds: [],
  },
  formStatus: {
    shop: "clean",
    template: "clean",
    instance: "clean",
    stackInstance: "clean",
  },
});

export const createContainerState = () => ({
  containerState: {
    containers: [],
    templateRows: [],
    instanceRows: [],
    itemInstances: [],
  },
  shopItems: [],
  inventoryItems: [],
  allItemInstances: [],
  trashItems: [],
  selectedTrashId: null,
  selectedInventoryId: null,
});

export const createTradeState = () => ({
  loadingBuy: false,
  loadingSell: false,
  errorBuy: "",
  errorSell: "",
  bgWalletBrass: 0,
  walletBalances: {},
  walletCurrencyCode: "generic",
  selectedBuyIds: [],
  selectedSellIds: [],
  selectedBuyQuantities: {},
  selectedSellQuantities: {},
  lastTradeReceipt: null,
});

export const createLedgerState = () => ({
  playerTransactions: [],
  exportedShopSnapshot: null,
});

export const createShopState = ({ campaignId, gmMode, pricingConfig }) => ({
  ...createSessionState({ campaignId, gmMode }),
  ...createCatalogState({ pricingConfig }),
  ...createContainerState(),
  ...createTradeState(),
  ...createLedgerState(),
});
