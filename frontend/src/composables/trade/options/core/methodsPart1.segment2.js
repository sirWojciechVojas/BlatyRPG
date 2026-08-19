export const createCoreMethodsPart1Segment2 = (runtime) => {
  return {
    shopProfileOptionLabel(group, value) {
      const normalized = String(value || "").trim();
      if (!normalized) {
        return "";
      }
      const option = runtime
        .toShopEditorOptions(group)
        .find((entry) => String(entry.value) === normalized);
      return String(option?.label || normalized).trim();
    },
    normalizeTrashOwnerCode(value) {
      const normalized = String(value || "")
        .trim()
        .toUpperCase();
      if (this.actorOwnerCodes.includes(normalized)) {
        return normalized;
      }
      return runtime.TRASH_OWNER_GENERAL;
    },
    normalizeInventoryOwnerCode(value, { allowAll = false } = {}) {
      const normalized = String(value || "")
        .trim()
        .toUpperCase();
      if (allowAll && normalized === "ALL") {
        return "all";
      }
      if (normalized === runtime.OWNER_CODES.DEFAULT) {
        return runtime.OWNER_CODES.DEFAULT;
      }
      if (this.actorOwnerCodes.includes(normalized)) {
        return normalized;
      }
      return runtime.OWNER_CODES.DEFAULT;
    },
    inventoryOwnerLabel(ownerCode) {
      const normalized = this.normalizeInventoryOwnerCode(ownerCode);
      if (normalized === runtime.OWNER_CODES.DEFAULT) {
        return runtime.t("shop.dataLabels.owners.DEFAULT");
      }
      const actor = this.actorByOwnerCode[normalized];
      if (actor?.name) {
        return `${normalized} - ${actor.name}`;
      }
      return normalized;
    },
    trashItemsForOwner(ownerCode) {
      const normalized = this.normalizeTrashOwnerCode(ownerCode);
      return (this.trashItems || []).filter(
        (item) => this.normalizeTrashOwnerCode(item?.OWNER) === normalized,
      );
    },
    trashCapacityForOwner(ownerCode) {
      const normalized = this.normalizeTrashOwnerCode(ownerCode);
      if (normalized === runtime.TRASH_OWNER_GENERAL) {
        return runtime.GENERAL_TRASH_SLOT_CAPACITY;
      }
      return runtime.PLAYER_TRASH_SLOT_CAPACITY;
    },
    trashOwnerLabel(ownerCode) {
      const normalized = this.normalizeTrashOwnerCode(ownerCode);
      const actor = this.actorByOwnerCode[normalized];
      return actor?.name || normalized;
    },
    canAllocateTrashSlots(ownerCode, amount = 1) {
      const normalized = this.normalizeTrashOwnerCode(ownerCode);
      const required = Math.max(0, Number(amount || 0));
      const used = this.trashItemsForOwner(normalized).length;
      const capacity = this.trashCapacityForOwner(normalized);
      const numericCapacity = Number(capacity);
      const isLimited =
        capacity !== null &&
        capacity !== undefined &&
        Number.isFinite(numericCapacity) &&
        numericCapacity >= 0;
      if (!isLimited) {
        return true;
      }
      return used + required <= numericCapacity;
    },
    deferTradeUiWork(callback) {
      if (typeof callback !== "function") {
        return;
      }
      if (typeof window === "undefined") {
        setTimeout(callback, 0);
        return;
      }
      if (typeof window.requestIdleCallback === "function") {
        window.requestIdleCallback(callback, {
          timeout: 400,
        });
        return;
      }
      if (typeof window.requestAnimationFrame === "function") {
        window.requestAnimationFrame(() => setTimeout(callback, 0));
        return;
      }
      setTimeout(callback, 0);
    },
    queueTradingDataLoad(options = {}) {
      const payload =
        options && typeof options === "object" && !Array.isArray(options)
          ? options
          : {};
      Promise.resolve(this.loadTradingData(payload)).catch(() => {});
    },
    retryTradeDataLoad() {
      this.queueTradingDataLoad({
        forceReload: true,
      });
    },
    resolveTrashOwnerForInventoryItem(item) {
      const owner = this.normalizeTrashOwnerCode(item?.OWNER_OPT);
      if (owner !== runtime.TRASH_OWNER_GENERAL) {
        return owner;
      }
      const activeOwner = this.normalizeTrashOwnerCode(this.activeBgOwner);
      return activeOwner === runtime.TRASH_OWNER_GENERAL ? "BG1" : activeOwner;
    },
    isShopActiveEntry(shop) {
      return shop?.isActive !== false;
    },
    ensurePlayerActiveShop() {
      if (this.isGM) {
        return;
      }
      const current = (this.shops || []).find(
        (shop) => Number(shop.id) === Number(this.activeShopId),
      );
      if (current && this.isShopActiveEntry(current)) {
        return;
      }
      const fallback =
        (this.shops || []).find((shop) => this.isShopActiveEntry(shop)) ||
        this.shops?.[0] ||
        null;
      if (!fallback) {
        return;
      }
      if (Number(fallback.id) === Number(this.activeShopId)) {
        return;
      }
      this.setActiveShop(Number(fallback.id));
    },
    openShopActivationDialog() {
      if (!this.isShopAddEditMode) {
        return;
      }
      this.showShopActivationDialog = true;
    },
  };
};
