import i18n from "@/i18n";
import { GM_MODES, OWNER_CODES } from "@/lib/trade/constants";
import {
  normalizeLegacyInventoryRecord,
  normalizeLegacyTemplateRecord,
  normalizeLegacyTrashRecord,
} from "@/lib/trade/adapters";
import {
  createShopApiConfig,
  isRecoverableShopApiError,
  isShopApiEnabled,
  isShopApiFallbackEnabled,
  isShopDemoMode,
  normalizeShopApiError,
  shopApiClient,
} from "@/lib/trade/shopApiClient";
import {
  createDefaultShopPricingConfig,
  normalizeShopPricingConfig,
  resolveDisplayedPrice,
} from "@/lib/trade/shopPriceCalculator";
import {
  buildPersistedTradePayload,
  normalizePersistedTradePayload,
} from "@/lib/trade/persistMigration";
import { nextIdFromItems } from "@/lib/tradeModalUtils";
import {
  resolveItemIconClass,
  withResolvedSuggestionIcons,
} from "@/lib/trade/shopItemIconResolver";
import { createCatalogMutations } from "./shop/catalog";
import { createCatalogActions } from "./shop/catalogActions";
import { createContainerMutations } from "./shop/containers";
import { createContainerActions } from "./shop/containerActions";
import { createLedgerMutations } from "./shop/ledger";
import { createLedgerActions } from "./shop/ledgerActions";
import { createSessionMutations } from "./shop/session";
import { createShopState } from "./shop/state";
import { createTradeMutations } from "./shop/trade";
import { createTradeActions } from "./shop/tradeActions";
import { loadDemoShopData, loadDemoSuggestionEngine } from "./shop/demoData";
import * as runtime from "./shop/runtime";
import { createShopGetters } from "./shop/getters";
import { createMutationGroup1 } from "./shop/mutations/mutationGroup1";
import { createMutationGroup2 } from "./shop/mutations/mutationGroup2";
import { createMutationGroup3 } from "./shop/mutations/mutationGroup3";
import { createMutationGroup4 } from "./shop/mutations/mutationGroup4";
import { createMutationGroup5 } from "./shop/mutations/mutationGroup5";
import { createMutationGroup6 } from "./shop/mutations/mutationGroup6";
import { createActionGroup1 } from "./shop/actions/actionGroup1";
import { createActionGroup2 } from "./shop/actions/actionGroup2";
import { createActionGroup3 } from "./shop/actions/actionGroup3";
import { createActionGroup4 } from "./shop/actions/actionGroup4";
import { createActionGroup5 } from "./shop/actions/actionGroup5";
import { createActionGroup6 } from "./shop/actions/actionGroup6";
import { createActionGroup7 } from "./shop/actions/actionGroup7";

const deps = {
  ...runtime,
  i18n,
  GM_MODES,
  OWNER_CODES,
  normalizeLegacyInventoryRecord,
  normalizeLegacyTemplateRecord,
  normalizeLegacyTrashRecord,
  createShopApiConfig,
  isRecoverableShopApiError,
  isShopApiEnabled,
  isShopApiFallbackEnabled,
  isShopDemoMode,
  normalizeShopApiError,
  shopApiClient,
  createDefaultShopPricingConfig,
  normalizeShopPricingConfig,
  resolveDisplayedPrice,
  buildPersistedTradePayload,
  normalizePersistedTradePayload,
  nextIdFromItems,
  resolveItemIconClass,
  withResolvedSuggestionIcons,
  createCatalogMutations,
  createCatalogActions,
  createContainerMutations,
  createContainerActions,
  createLedgerMutations,
  createLedgerActions,
  createSessionMutations,
  createShopState,
  createTradeMutations,
  createTradeActions,
  loadDemoShopData,
  loadDemoSuggestionEngine,
};

export default {
  namespaced: true,
  state: () =>
    createShopState({
      campaignId: Number(process.env.VUE_APP_SHOP_CAMPAIGN_ID || 1),
      gmMode: GM_MODES.TEMPLATES,
      pricingConfig: createDefaultShopPricingConfig(),
    }),
  getters: createShopGetters(deps),
  mutations: {
    ...createMutationGroup1(deps),
    ...createMutationGroup2(deps),
    ...createMutationGroup3(deps),
    ...createMutationGroup4(deps),
    ...createMutationGroup5(deps),
    ...createMutationGroup6(deps),
  },
  actions: {
    ...createActionGroup1(deps),
    ...createActionGroup2(deps),
    ...createActionGroup3(deps),
    ...createActionGroup4(deps),
    ...createActionGroup5(deps),
    ...createActionGroup6(deps),
    ...createActionGroup7(deps),
  },
};
