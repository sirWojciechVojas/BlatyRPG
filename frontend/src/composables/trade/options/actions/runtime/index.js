import { isRef, unref } from "vue";
import i18n from "@/i18n";
import {
  clearShopNotificationZone,
  notifyShop,
} from "@/components/shop/composables/useShopNotifications";
import { GM_MODES, OWNER_CODES, TRASH_KINDS } from "@/lib/trade/constants";
import {
  COC_CURRENCY_DEFINITIONS,
  GENERIC_CURRENCY_DEFINITION,
  WFRP_CURRENCY_DEFINITIONS,
  formatCurrencyAmount,
  resolveCurrencyDefinition,
} from "@/lib/trade/currency";
import { createActionsRuntimePart1 } from "./part1";

const runtime = {
  isRef,
  unref,
  i18n,
  clearShopNotificationZone,
  notifyShop,
  GM_MODES,
  OWNER_CODES,
  TRASH_KINDS,
  COC_CURRENCY_DEFINITIONS,
  GENERIC_CURRENCY_DEFINITION,
  WFRP_CURRENCY_DEFINITIONS,
  formatCurrencyAmount,
  resolveCurrencyDefinition,
};
Object.assign(runtime, createActionsRuntimePart1(runtime));

export default runtime;
