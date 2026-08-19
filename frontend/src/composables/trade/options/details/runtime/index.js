import { isRef, onBeforeUnmount, unref } from "vue";
import { isShopApiEnabled } from "@/lib/trade/shopApiClient";
import i18n from "@/i18n";
import {
  formatCoin as formatCoinUtil,
  nextIdFromItems,
} from "@/lib/tradeModalUtils";
import {
  clearAllShopNotifications,
  notifyShop,
} from "@/components/shop/composables/useShopNotifications";
import { createDetailsRuntimePart1 } from "./part1";

const loadInventoryDetailSprite = () =>
  import(
    /* webpackChunkName: "shop-item-detail-sprite" */
    "@/assets/app-ui/img/inventory/invIco144x144.png"
  ).then((module) => module.default || module);

const runtime = {
  isRef,
  onBeforeUnmount,
  unref,
  isShopApiEnabled,
  i18n,
  formatCoinUtil,
  nextIdFromItems,
  clearAllShopNotifications,
  notifyShop,
  loadInventoryDetailSprite,
};
Object.assign(runtime, createDetailsRuntimePart1(runtime));

export default runtime;
