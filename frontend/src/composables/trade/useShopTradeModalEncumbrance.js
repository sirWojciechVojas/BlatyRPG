import { computed, isRef, unref } from "vue";
import { OWNER_CODES } from "@/lib/trade/constants";

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
const bgCarryLimit = 300;
const bgCarryUnitShort = "KP";
const bgCarryUnitName = "Kamienie Podroznika";
const bgCarryHighRatio = 0.7;
const bgCarryWarningRatio = 0.9;

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
      return bgCarryLimit - this.bgEncumbranceCurrent;
    },
    bgEncumbranceOverLimit() {
      return this.bgEncumbranceCurrent > bgCarryLimit;
    },
    bgEncumbranceWouldExceedLimit() {
      return this.bgEncumbranceProjected > bgCarryLimit;
    },
    bgEncumbranceStatus() {
      return this.resolveEncumbranceStatus(
        this.bgEncumbranceCurrent,
        bgCarryLimit,
      );
    },
    bgEncumbranceLimit() {
      return bgCarryLimit;
    },
    bgEncumbranceUnitShort() {
      return bgCarryUnitShort;
    },
    bgEncumbranceUnitName() {
      return bgCarryUnitName;
    },
  },
  methods: {
    resolveItemQuantity(item, fallback = 1) {
      const quantity = Number(item?.QUANTITY);
      if (!Number.isFinite(quantity)) {
        return Math.max(0, Math.round(fallback));
      }
      return Math.max(0, Math.round(quantity));
    },
    resolveItemCharge(item, fallback = 0) {
      const directCharge = Number(item?.CHARGE);
      if (Number.isFinite(directCharge) && directCharge >= 0) {
        return directCharge;
      }
      const templateId = Number(item?.INV_ID ?? item?.ID);
      if (!Number.isFinite(templateId)) {
        return fallback;
      }
      const templateCharge = Number(
        this.templateItemsMap?.[templateId]?.CHARGE,
      );
      if (Number.isFinite(templateCharge) && templateCharge >= 0) {
        return templateCharge;
      }
      return fallback;
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
      return source.reduce((total, item) => {
        return (
          total +
          this.resolveItemCharge(item, 0) * this.resolveItemQuantity(item, 1)
        );
      }, 0);
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
    resolveEncumbranceStatus(load, limit = bgCarryLimit) {
      if (!Number.isFinite(load) || !Number.isFinite(limit) || limit <= 0) {
        return "Brak danych";
      }
      if (load > limit) {
        return "Przeciazony";
      }
      const ratio = load / limit;
      if (ratio >= bgCarryWarningRatio) {
        return "Na granicy";
      }
      if (ratio >= bgCarryHighRatio) {
        return "Ciezko";
      }
      return "Lekko";
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
