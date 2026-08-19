import { buildItemDetailMetaSections } from "./itemDetailSections";
import { computed, isRef, onMounted, unref, watch } from "vue";
import { mapActions, mapGetters, mapMutations, mapState } from "vuex";
import bgTrading from "@/assets/app-ui/img/bg-trading.jpg";
import gui from "@/assets/app-ui/gfx/GUISTBSC.png";
import frame from "@/assets/app-ui/img/frameUni.png";
import titleBar from "@/assets/app-ui/img/titleBar-center.png";
import gmBadge from "@/assets/app-ui/img/gm.bak.jpg";
import gmBadgeHover from "@/assets/app-ui/img/gm.bak.h.jpg";
import crownImg from "@/assets/app-ui/img/brass/mGoldCrowns.jpg";
import shillingImg from "@/assets/app-ui/img/brass/mSilverShillings.jpg";
import brassImg from "@/assets/app-ui/img/brass/mBronzePennies.jpg";
import inventorySprite from "@/assets/app-ui/img/inventory/invIco42x42.png";
import inventoryBorderMagic from "@/assets/app-ui/img/inventory/BorderMagicV11.png";
import inventoryBorderRare from "@/assets/app-ui/img/inventory/BorderRareV11.png";
import inventoryBorderUnique from "@/assets/app-ui/img/inventory/BorderUniqueV11.png";
import TradeModalShell from "@/components/trade/TradeModalShell.vue";
import TradeModalContent from "@/components/trade/TradeModalContent.vue";
import { createContainerState } from "@/lib/containerModel";
import {
  normalizeLegacyIconClass as normalizeLegacyIconClassUtil,
  resolveItemIconToken,
  resolveItemImageSource,
  toNumber as toNumberUtil,
  nextIdFromItems,
} from "@/lib/tradeModalUtils";
import { drawShopSignboard } from "@/lib/shopSignboardService";
import i18n from "@/i18n";
import { FEATURE_FLAGS, GM_MODES, OWNER_CODES } from "@/lib/trade/constants";
import { resolveDisplayedPrice } from "@/lib/trade/shopPriceCalculator";
import {
  currencyDefinitionsForSystem,
  localizedCurrencyLabel,
  resolveCurrencyDefinition,
} from "@/lib/trade/currency";
import {
  validateInventoryRecord,
  validateTemplateRecord,
} from "@/lib/trade/validators";
import { createCoreRuntimePart1 } from "./part1";
import { createCoreRuntimePart2 } from "./part2";

const runtime = {
  buildItemDetailMetaSections,
  computed,
  isRef,
  onMounted,
  unref,
  watch,
  mapActions,
  mapGetters,
  mapMutations,
  mapState,
  bgTrading,
  gui,
  frame,
  titleBar,
  gmBadge,
  gmBadgeHover,
  crownImg,
  shillingImg,
  brassImg,
  inventorySprite,
  inventoryBorderMagic,
  inventoryBorderRare,
  inventoryBorderUnique,
  TradeModalShell,
  TradeModalContent,
  createContainerState,
  normalizeLegacyIconClassUtil,
  resolveItemIconToken,
  resolveItemImageSource,
  toNumberUtil,
  nextIdFromItems,
  drawShopSignboard,
  i18n,
  FEATURE_FLAGS,
  GM_MODES,
  OWNER_CODES,
  resolveDisplayedPrice,
  currencyDefinitionsForSystem,
  localizedCurrencyLabel,
  resolveCurrencyDefinition,
  validateInventoryRecord,
  validateTemplateRecord,
};
Object.assign(runtime, createCoreRuntimePart1(runtime));
Object.assign(runtime, createCoreRuntimePart2(runtime));

export default runtime;
