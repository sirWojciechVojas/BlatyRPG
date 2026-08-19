export const createCoreMethodsPart6 = (runtime) => {
  return {
    async confirmImgClass() {
      if (!this.selectedImgClass) {
        this.classEditValidationError = runtime.t("common.validation.required");
        this.showWalletAlert(runtime.t("shop.alerts.chooseIconFirst"));
        return;
      }
      const target = this.classEditTarget;
      this.applyClassEdit(this.normalizeLegacyIconClass(this.selectedImgClass));
      if (
        target !== "template" ||
        typeof this.saveTemplateRecord !== "function"
      ) {
        return;
      }
      const normalized = this.normalizeTemplateForm(this.templateForm);
      if (!normalized.valid) {
        if (typeof this.setFormErrors === "function") {
          this.setFormErrors("templateEdit", normalized.errors || {});
        }
        this.showWalletAlert(normalized.message, {
          zone: "sell",
          type: "error",
          title: "Błąd walidacji",
        });
        return;
      }
      const saved = await this.saveTemplateRecord(normalized.item);
      if (!saved) {
        this.showWalletAlert(runtime.t("shop.alerts.templateSaveFailed"), {
          zone: "sell",
          type: "error",
          title: "Błąd zapisu",
        });
        return;
      }
      this.showWalletAlert(runtime.t("shop.alerts.templateSaved"), {
        zone: "sell",
        type: "success",
        title: "Zapisano",
      });
    },
    selectOwnerOpt(value) {
      this.selectedOwnerOpt = value;
      this.classEditDraftValue = String(value || "");
      this.classEditValidationError = "";
    },
    confirmOwnerOpt() {
      if (!this.selectedOwnerOpt) {
        this.classEditValidationError = runtime.t("common.validation.required");
        this.showWalletAlert(runtime.t("shop.alerts.chooseOptionFirst"));
        return;
      }
      this.applyClassEdit(this.selectedOwnerOpt);
    },
    currentImgClassForTarget(target) {
      if (target === "template") {
        return (
          this.templateForm.IMG_CLASS ||
          runtime.legacySpriteIconOptions[0] ||
          "v0001"
        );
      }
      if (target === "newTemplate") {
        return (
          this.newTemplateForm.IMG_CLASS ||
          runtime.legacySpriteIconOptions[0] ||
          "v0001"
        );
      }
      return (
        this.inventoryForm.IMG_CLASS ||
        runtime.legacySpriteIconOptions[0] ||
        "v0001"
      );
    },
    currentOwnerOptForTarget(target) {
      if (target === "template") {
        return (
          this.templateForm.OWNER_OPT ||
          runtime.classOptionsMap.OWNER_OPT?.[0] ||
          "DEFAULT"
        );
      }
      if (target === "newTemplate") {
        return (
          this.newTemplateForm.OWNER_OPT ||
          runtime.classOptionsMap.OWNER_OPT?.[0] ||
          "DEFAULT"
        );
      }
      return (
        this.inventoryForm.OWNER_OPT ||
        runtime.classOptionsMap.OWNER_OPT?.[0] ||
        "DEFAULT"
      );
    },
    ownerOptDescription(value) {
      const key = runtime.ownerOptDescriptionKeys[value];
      return key
        ? runtime.t(key)
        : runtime.t("modals.ownerOption.noDescription");
    },
    defaultTemplateForm() {
      const defaultItemClass = runtime.classOptionsMap.ITEM_CLASS?.[0] || "";
      const defaultImgClass = runtime.legacySpriteIconOptions[0] || "v0001";
      const defaultItemId =
        defaultItemClass === "WEAPON"
          ? runtime.classOptionsMap.ITEM_ID?.[0] || ""
          : "";
      const defaultWeaponStats =
        defaultItemClass === "WEAPON"
          ? this.weaponStatsForItemId(defaultItemId)
          : {};
      return {
        ID:
          typeof this.nextTemplateId === "function"
            ? this.nextTemplateId()
            : runtime.nextIdFromItems(this.$store.state.shop.templateItems, 0),
        NAME: "",
        DESCRIPTION: "",
        DETAILS: "",
        ITEM_CLASS: defaultItemClass,
        ITEM_ID: defaultItemId,
        WEAPON: {
          ...defaultWeaponStats,
        },
        ITEM_GENRE: "",
        IMG_CLASS: defaultImgClass,
        PRIZE: 0,
        CHARGE: 0,
      };
    },
    resetNewTemplateForm() {
      this.newTemplateForm = this.defaultTemplateForm();
      this.newTemplateFormErrors = {};
    },
    setFormErrors(target, errors = {}) {
      const normalized =
        errors && typeof errors === "object"
          ? {
              ...errors,
            }
          : {};
      if (target === "templateEdit") {
        this.templateFormErrors = normalized;
        return;
      }
      if (target === "templateCreate") {
        this.newTemplateFormErrors = normalized;
        return;
      }
      if (target === "inventoryEdit") {
        this.inventoryFormErrors = normalized;
      }
    },
    clearFormErrors() {
      this.templateFormErrors = {};
      this.newTemplateFormErrors = {};
      this.inventoryFormErrors = {};
    },
    fieldInputType(field) {
      const numericFields = [
        "ID",
        "INV_ID",
        "ITEM_ID",
        "PRIZE",
        "CHARGE",
        "PERSONAL_COST",
        "QUANTITY",
      ];
      return numericFields.includes(field) ? "number" : "text";
    },
    isReadOnlyField(field, context) {
      if (context === "templateEdit") {
        return field === "ID";
      }
      if (context === "inventoryEdit") {
        return field === "ID" || field === "INV_ID";
      }
      return false;
    },
    toNumber(value, fallback = 0) {
      return runtime.toNumberUtil(value, fallback);
    },
    defaultIconClass() {
      return runtime.legacySpriteIconOptions[0] || "v0001";
    },
    templateIdExists(id, ignoreId = null) {
      const numericId = this.toNumber(id, NaN);
      if (!Number.isFinite(numericId)) {
        return false;
      }
      return this.$store.state.shop.templateItems.some((item) => {
        const itemId = Number(item.ID);
        if (ignoreId !== null && Number(ignoreId) === itemId) {
          return false;
        }
        return itemId === numericId;
      });
    },
    localizeTemplateErrors(errors = {}) {
      const dictionary = {
        ID: runtime.t("shop.validation.template.id"),
        NAME: runtime.t("shop.validation.template.name"),
        DESCRIPTION: runtime.t("shop.validation.template.description"),
        ITEM_CLASS: runtime.t("shop.validation.template.itemClass"),
        ITEM_ID: runtime.t("shop.validation.template.itemIdWeapon"),
        PRIZE: runtime.t("shop.validation.template.prize"),
        CHARGE: runtime.t("shop.validation.template.charge"),
      };
      return Object.keys(errors || {}).reduce((acc, key) => {
        acc[key] = dictionary[key] || String(errors[key] || "");
        return acc;
      }, {});
    },
    localizeInventoryErrors(errors = {}) {
      const dictionary = {
        ID: runtime.t("shop.validation.inventory.id"),
        INV_ID: runtime.t("shop.validation.inventory.invId"),
        PERSONAL_COST: runtime.t("shop.validation.inventory.personalCost"),
        QUANTITY: runtime.t("shop.validation.inventory.quantity"),
        PRIZE: runtime.t("shop.validation.inventory.prize"),
        CHARGE: runtime.t("shop.validation.inventory.charge"),
      };
      return Object.keys(errors || {}).reduce((acc, key) => {
        acc[key] = dictionary[key] || String(errors[key] || "");
        return acc;
      }, {});
    },
    firstValidationMessage(errors = {}) {
      const list = Object.values(errors || {}).filter(Boolean);
      return list.length
        ? list[0]
        : runtime.t("shop.validation.common.invalidForm");
    },
  };
};
