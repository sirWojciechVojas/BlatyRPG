import { computed, isRef, unref } from "vue";
import { OWNER_CODES } from "@/lib/trade/constants";
import {
  BG_CARRY_LIMIT,
  BG_CARRY_UNIT_NAME,
  BG_CARRY_UNIT_SHORT,
  bgEncumbranceStatusLabel,
  calculateInventoryEncumbrance as calculateItemsEncumbrance,
  resolveEncumbranceStatus as resolveSharedEncumbranceStatus,
  resolveItemCharge as resolveSharedItemCharge,
  resolveItemQuantity as resolveSharedItemQuantity,
} from "@/lib/trade/encumbrance";

const hasOwn = (target, key) =>
  Object.prototype.hasOwnProperty.call(target, key);

const createVm = ({ state, api, deps }) =>
  new Proxy(
    {},
    {
      get(_, key) {
        if (typeof key === "symbol") {
          return undefined;
        }
        if (hasOwn(state, key)) {
          return state[key];
        }
        if (hasOwn(api, key)) {
          const value = api[key];
          return typeof value === "function" ? value : unref(value);
        }
        if (hasOwn(deps, key)) {
          const value = deps[key];
          return typeof value === "function" ? value : unref(value);
        }
        return undefined;
      },
      set(_, key, value) {
        if (typeof key === "symbol") {
          return false;
        }
        if (hasOwn(state, key)) {
          state[key] = value;
          return true;
        }
        if (hasOwn(api, key)) {
          const current = api[key];
          if (isRef(current)) {
            current.value = value;
            return true;
          }
          if (typeof current !== "function") {
            api[key] = value;
            return true;
          }
          return false;
        }
        if (hasOwn(deps, key)) {
          const current = deps[key];
          if (isRef(current)) {
            current.value = value;
            return true;
          }
          if (typeof current !== "function") {
            deps[key] = value;
            return true;
          }
          return false;
        }
        state[key] = value;
        return true;
      },
    },
  );
const encumbranceOptions = {
  computed: {
    bgEncumbranceCurrent() {
      return this.calculateInventoryEncumbrance();
    },
    bgEncumbranceSelection() {
      return this.calculateSelectionEncumbrance();
    },
    bgEncumbranceProjected() {
      return this.bgEncumbranceCurrent + this.bgEncumbranceSelection;
    },
    bgEncumbranceRemaining() {
      return BG_CARRY_LIMIT - this.bgEncumbranceCurrent;
    },
    bgEncumbranceOverLimit() {
      return this.bgEncumbranceCurrent > BG_CARRY_LIMIT;
    },
    bgEncumbranceWouldExceedLimit() {
      return this.bgEncumbranceProjected > BG_CARRY_LIMIT;
    },
    bgEncumbranceStatus() {
      return this.resolveEncumbranceStatus(
        this.bgEncumbranceCurrent,
        BG_CARRY_LIMIT,
      );
    },
    bgEncumbranceLimit() {
      return BG_CARRY_LIMIT;
    },
    bgEncumbranceUnitShort() {
      return BG_CARRY_UNIT_SHORT;
    },
    bgEncumbranceUnitName() {
      return BG_CARRY_UNIT_NAME;
    },
  },
  methods: {
    resolveItemQuantity(item, fallback = 1) {
      return resolveSharedItemQuantity(item, fallback);
    },
    resolveItemCharge(item, fallback = 0) {
      return resolveSharedItemCharge(item, this.templateItemsMap, fallback);
    },
    calculateInventoryEncumbrance() {
      const activeOwnerCode = String(
        this.activeBgOwner || OWNER_CODES.BG1,
      ).toUpperCase();
      const source =
        this.isGM === false
          ? (this.inventoryItems || []).filter(
              (item) =>
                String(
                  item?.OWNER_OPT || item?.OWNER || OWNER_CODES.DEFAULT,
                ).toUpperCase() === activeOwnerCode,
            )
          : this.inventoryItems || [];
      return calculateItemsEncumbrance(source, this.templateItemsMap);
    },
    calculateSelectionEncumbrance() {
      if (this.isGM) {
        return 0;
      }
      return (this.selectedBuyIds || []).reduce((total, id) => {
        const item = (this.buyItems || []).find(
          (entry) => Number(entry.ID) === Number(id),
        );
        if (!item) {
          return total;
        }
        const max = Math.max(1, this.resolveItemQuantity(item, 1));
        const requested = Number(this.selectedBuyQuantities?.[id]);
        const quantity = Number.isFinite(requested)
          ? Math.max(1, Math.min(max, Math.round(requested)))
          : 1;
        return total + this.resolveItemCharge(item, 0) * quantity;
      }, 0);
    },
    resolveEncumbranceStatus(load, limit = BG_CARRY_LIMIT) {
      return bgEncumbranceStatusLabel(
        resolveSharedEncumbranceStatus(load, limit),
      );
    },
  },
};
export const useShopTradeModalEncumbrance = (ctx, deps = {}) => {
  const { state } = ctx;
  const api = {};
  const vm = createVm({ state, api, deps });

  Object.entries(encumbranceOptions.methods || {}).forEach(([name, method]) => {
    if (typeof method !== "function") {
      return;
    }
    api[name] = (...args) => method.apply(vm, args);
  });

  Object.entries(encumbranceOptions.computed || {}).forEach(
    ([name, getter]) => {
      if (typeof getter !== "function") {
        return;
      }
      api[name] = computed(() => getter.call(vm));
    },
  );

  return api;
};

export default useShopTradeModalEncumbrance;
