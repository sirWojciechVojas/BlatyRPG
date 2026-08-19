export const createCoreComputedPart4 = (runtime) => {
  return {
    classEditSuggestions() {
      const field = String(this.classEditType || "")
        .trim()
        .toUpperCase();
      if (!field) {
        return [];
      }
      const query = String(this.classEditSearch || "")
        .trim()
        .toLowerCase();
      const applySearch = (values = []) =>
        query
          ? values.filter((value) =>
              String(value || "")
                .toLowerCase()
                .includes(query),
            )
          : values;
      if (field === "ITEM_CLASS") {
        return applySearch([...(runtime.classOptionsMap.ITEM_CLASS || [])]);
      }
      if (field === "ITEM_ID") {
        const options = new Set([...(runtime.classOptionsMap.ITEM_ID || [])]);
        const current = String(
          this.currentItemIdForTarget(this.classEditTarget),
        );
        if (current) {
          options.add(current);
        }
        const selected = String(this.weaponStatsDraft?.ITEM_ID || "").trim();
        if (selected) {
          options.add(selected);
        }
        return applySearch(
          Array.from(options).sort(
            (left, right) => Number(left) - Number(right),
          ),
        );
      }
      if (field === "OWNER_OPT") {
        return applySearch([...(runtime.classOptionsMap.OWNER_OPT || [])]);
      }
      if (field === "ITEM_PLACE") {
        return applySearch([...(runtime.classOptionsMap.ITEM_PLACE || [])]);
      }
      const currentForm = this.currentFormForTarget(this.classEditTarget);
      if (field === "ITEM_GENRE") {
        const selectedClass = this.classEditItemClass;
        if (!selectedClass) {
          return [];
        }
        const values = new Set();
        const appendGenre = (item) => {
          const itemClass = String(item?.ITEM_CLASS || "")
            .trim()
            .toUpperCase();
          if (itemClass !== selectedClass) {
            return;
          }
          const value = String(item?.ITEM_GENRE || "")
            .trim()
            .toUpperCase();
          if (value) {
            values.add(value);
          }
        };
        (this.templateItems || []).forEach(appendGenre);
        (this.inventoryItems || []).forEach(appendGenre);
        (this.trashItems || []).forEach(appendGenre);
        [this.templateForm, this.newTemplateForm, this.inventoryForm].forEach(
          appendGenre,
        );
        const current = String(currentForm?.ITEM_GENRE || "")
          .trim()
          .toUpperCase();
        if (current) {
          values.add(current);
        }
        return applySearch(
          Array.from(values).sort((left, right) =>
            left.localeCompare(right, "pl"),
          ),
        );
      }
      if (field === "PRIZE" || field === "CHARGE") {
        const values = new Set();
        const append = (raw) => {
          const parsed = Number(raw);
          if (!Number.isFinite(parsed) || parsed < 0) {
            return;
          }
          values.add(String(parsed));
        };
        const appendFromItem = (item) => append(item?.[field]);
        (this.templateItems || []).forEach(appendFromItem);
        (this.inventoryItems || []).forEach(appendFromItem);
        (this.trashItems || []).forEach(appendFromItem);
        [this.templateForm, this.newTemplateForm, this.inventoryForm].forEach(
          (form) => append(form?.[field]),
        );
        append(currentForm?.[field]);
        return applySearch(
          Array.from(values).sort(
            (left, right) => Number(left) - Number(right),
          ),
        );
      }
      return [];
    },
    itemDetailBaseName() {
      const item = this.itemDetailItem;
      if (!item) {
        return "";
      }
      return (
        item.NAME ||
        item.name ||
        item.title ||
        runtime.t("shop.itemDetailDialog.fallbackItemName")
      );
    },
    itemDetailNicknameValue() {
      if (this.itemDetailNickname) {
        return this.itemDetailNickname;
      }
      return this.itemDetailItem?.PERSONAL_PSEU || "";
    },
    itemDetailDisplayName() {
      const baseName =
        this.itemDetailBaseName ||
        runtime.t("shop.itemDetailDialog.fallbackItemName");
      const nickname = this.itemDetailNicknameValue;
      if (!nickname) {
        return baseName;
      }
      if (
        String(nickname)
          .trim()
          .localeCompare(String(baseName).trim(), undefined, {
            sensitivity: "base",
          }) === 0
      ) {
        return baseName;
      }
      if (this.itemDetailNicknameMode === "replace") {
        return nickname;
      }
      return `${baseName} (${nickname})`;
    },
    itemDetailNicknameModeLabel() {
      return this.itemDetailNicknameMode === "replace"
        ? runtime.t("shop.itemDetailDialog.nicknameModeReplace")
        : runtime.t("shop.itemDetailDialog.nicknameModeAppend");
    },
    itemDetailCanEditNickname() {
      const item = this.itemDetailItem;
      if (!item) {
        return false;
      }
      if (Object.prototype.hasOwnProperty.call(item, "PERSONAL_PSEU")) {
        return true;
      }
      return (
        this.itemDetailSource === "inventory" ||
        this.itemDetailSource === "trash"
      );
    },
    itemDetailSourceLabel() {
      if (!this.itemDetailSource) {
        return "";
      }
      const map = {
        buy: runtime.t("shop.itemDetailDialog.sources.shop"),
        sell: runtime.t("shop.itemDetailDialog.sources.inventory"),
        shop: runtime.t("shop.itemDetailDialog.sources.shop"),
        inventory: runtime.t("shop.itemDetailDialog.sources.inventory"),
        trash: runtime.t("shop.itemDetailDialog.sources.trash"),
      };
      return map[this.itemDetailSource] || this.itemDetailSource;
    },
    itemDetailPersonalCost() {
      const item = this.itemDetailItem;
      if (!item) {
        return null;
      }
      const raw = runtime.resolveDisplayedPrice(item, NaN);
      if (raw === null || raw === undefined || raw === "") {
        return null;
      }
      return this.formatCoin(raw);
    },
    itemDetailPersonalCostRaw() {
      const item = this.itemDetailItem;
      if (!item) {
        return "";
      }
      const raw = runtime.resolveDisplayedPrice(item, NaN);
      if (raw === null || raw === undefined || raw === "") {
        return "";
      }
      return Number(raw) || 0;
    },
    itemDetailCurrencyCode() {
      return String(
        this.itemDetailItem?.CURRENCY ||
          this.itemDetailItem?.currency ||
          this.$store.state.shop.currencyDefinitions?.defaultCurrencyCode ||
          "wfrp_empire",
      );
    },
    itemDetailCurrencyLabel() {
      const configured =
        this.$store.state.shop.currencyDefinitions?.currencies || [];
      const definitions = configured.length
        ? configured
        : runtime.currencyDefinitionsForSystem(
            this.$store.state.shop.context?.systemCode || "wfrp2ed",
          );
      return runtime.localizedCurrencyLabel(
        runtime.resolveCurrencyDefinition(
          definitions,
          this.itemDetailCurrencyCode,
        ),
        typeof runtime.i18n.global.locale === "string"
          ? runtime.i18n.global.locale
          : runtime.i18n.global.locale.value,
      );
    },
    itemDetailChargeRaw() {
      const item = this.itemDetailItem;
      if (!item) {
        return null;
      }
      return this.resolveItemCharge(item, null);
    },
    itemDetailChargeUnit() {
      return this.bgEncumbranceUnitShort || "KP";
    },
    itemDetailChargeText() {
      if (this.itemDetailChargeRaw === null) {
        return "";
      }
      return `${this.itemDetailChargeRaw} ${this.itemDetailChargeUnit}`;
    },
    itemDetailChargeLabel() {
      const item = this.itemDetailItem;
      if (!item) {
        return "";
      }
      if (
        item.QUANTITY !== null &&
        item.QUANTITY !== undefined &&
        item.QUANTITY !== ""
      ) {
        return `x${item.QUANTITY}`;
      }
      return "";
    },
    itemDetailImageSrc() {
      return this.itemImageSrcForItem(this.itemDetailItem);
    },
    itemDetailMetaLines() {
      return this.itemDetailMetaSections.reduce(
        (lines, section) => lines.concat(section.lines || []),
        [],
      );
    },
  };
};
