import { buildServerContainerState } from "./serverContainerStateBuilder";
import { buildDemoContainerState } from "./demoContainerStateBuilder";
import { computed, isRef, unref, watch } from "vue";
import {
  moveInstance,
  moveTemplateStack,
  buyFromShop,
  getSystemContainerId,
} from "@/lib/containerModel";
import i18n from "@/i18n";
import {
  OWNER_CODES,
  SYSTEM_CONTAINER_KEYS,
  TRADE_DEFAULT_STACK_UI_LABEL,
} from "@/lib/trade/constants";
import { clampQuantity as clampQuantityUtil } from "@/lib/tradeModalUtils";
import { notifyShop } from "@/components/shop/composables/useShopNotifications";
import { createContainersRuntimePart1 } from "./part1";

const runtime = {
  buildServerContainerState,
  buildDemoContainerState,
  computed,
  isRef,
  unref,
  watch,
  moveInstance,
  moveTemplateStack,
  buyFromShop,
  getSystemContainerId,
  i18n,
  OWNER_CODES,
  SYSTEM_CONTAINER_KEYS,
  TRADE_DEFAULT_STACK_UI_LABEL,
  clampQuantityUtil,
  notifyShop,
};
Object.assign(runtime, createContainersRuntimePart1(runtime));

export default runtime;
