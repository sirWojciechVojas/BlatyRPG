export const createCoreMethodsPart5 = (runtime) => {
  return {
    updateWeaponStatsDraft(patch = {}) {
      const safePatch = patch && typeof patch === "object" ? patch : {};
      this.weaponStatsDraft = runtime.normalizeWeaponStatsDraft({
        ...this.weaponStatsDraft,
        ...safePatch,
      });
      this.classEditDraftValue = String(this.weaponStatsDraft?.ITEM_ID || "");
    },
    confirmWeaponStats() {
      const itemId = String(this.weaponStatsDraft?.ITEM_ID || "").trim();
      if (!itemId) {
        this.classEditValidationError = runtime.t("common.validation.required");
        this.showWalletAlert(runtime.t("shop.alerts.chooseOptionFirst"));
        return;
      }
      const normalized = this.weaponStatsForItemId(
        itemId,
        this.weaponStatsDraft,
      );
      this.applyFormPatchForTarget(this.classEditTarget, {
        ITEM_ID: itemId,
        WEAPON: {
          ...normalized,
        },
      });
      this.resetClassEdit();
    },
    currentFieldValueForTarget(field, target) {
      const form = this.currentFormForTarget(target);
      return form?.[field];
    },
    openClassEdit(field, target) {
      this.classEditType = field;
      this.classEditTarget = target;
      this.classEditSearch = "";
      this.classEditValidationError = "";
      if (field === "IMG_CLASS") {
        this.selectedImgClass = this.normalizeLegacyIconClass(
          this.currentImgClassForTarget(target),
        );
        this.classEditDraftValue = this.selectedImgClass;
      } else if (field === "OWNER_OPT") {
        this.selectedOwnerOpt = this.currentOwnerOptForTarget(target);
        this.classEditDraftValue = this.selectedOwnerOpt;
      } else if (
        field === "ITEM_ID" &&
        this.currentItemClassForTarget(target) === "WEAPON"
      ) {
        this.weaponStatsDraft = this.weaponStatsForTarget(target);
        this.classEditDraftValue = String(this.weaponStatsDraft?.ITEM_ID || "");
      } else {
        const currentValue = this.currentFieldValueForTarget(field, target);
        this.classEditDraftValue =
          currentValue === null || typeof currentValue === "undefined"
            ? ""
            : String(currentValue);
      }
    },
    applyClassEdit(value) {
      if (!this.classEditType) {
        return;
      }
      this.classEditValidationError = "";
      const patch = {
        [this.classEditType]: value,
      };
      if (this.classEditType === "ITEM_CLASS") {
        const nextClass = String(value || "")
          .trim()
          .toUpperCase();
        if (nextClass === "WEAPON") {
          const currentWeapon =
            this.currentFormForTarget(this.classEditTarget)?.WEAPON || {};
          const currentItemId =
            this.currentItemIdForTarget(this.classEditTarget) ||
            runtime.classOptionsMap.ITEM_ID?.[0] ||
            "";
          patch.ITEM_ID = currentItemId;
          patch.WEAPON = {
            ...this.weaponStatsForItemId(currentItemId, currentWeapon),
          };
        } else {
          patch.ITEM_ID = "";
          patch.WEAPON = {};
        }
      }
      if (
        this.classEditType === "ITEM_ID" &&
        this.currentItemClassForTarget(this.classEditTarget) === "WEAPON"
      ) {
        patch.WEAPON = {
          ...this.weaponStatsForItemId(value),
        };
      }
      this.applyFormPatchForTarget(this.classEditTarget, patch);
      this.resetClassEdit();
    },
    updateClassEditDraftValue(value) {
      this.classEditValidationError = "";
      this.classEditDraftValue = String(value ?? "");
      if (this.classEditMode === "weapon" && this.classEditType === "ITEM_ID") {
        this.selectWeaponStatsItem(this.classEditDraftValue);
      }
    },
    setClassEditSearch(value) {
      this.classEditSearch = String(value ?? "");
    },
    applyClassEditSuggestion(value) {
      if (this.classEditMode === "icon") {
        this.selectImgClass(value);
        return;
      }
      if (this.classEditMode === "owner") {
        this.selectOwnerOpt(value);
        return;
      }
      if (this.classEditMode === "weapon") {
        this.selectWeaponStatsItem(value);
        return;
      }
      this.updateClassEditDraftValue(value);
    },
    confirmClassEdit() {
      if (!this.classEditType) {
        return;
      }
      this.classEditValidationError = "";
      if (this.classEditMode === "icon") {
        this.confirmImgClass();
        return;
      }
      if (this.classEditMode === "owner") {
        this.confirmOwnerOpt();
        return;
      }
      if (this.classEditMode === "weapon") {
        this.confirmWeaponStats();
        return;
      }
      let nextValue = this.classEditDraftValue;
      if (this.classEditType === "PRIZE") {
        const parsed = Number(nextValue);
        if (!Number.isFinite(parsed)) {
          this.classEditValidationError = runtime.t(
            "common.validation.invalidNumber",
          );
          return;
        }
        if (parsed < 0) {
          this.classEditValidationError = runtime.t(
            "common.validation.nonNegative",
          );
          return;
        }
        nextValue = parsed;
      } else if (this.classEditType === "CHARGE") {
        const parsed = Number(nextValue);
        if (!Number.isFinite(parsed)) {
          this.classEditValidationError = runtime.t(
            "common.validation.invalidNumber",
          );
          return;
        }
        if (parsed < 0) {
          this.classEditValidationError = runtime.t(
            "common.validation.nonNegative",
          );
          return;
        }
        if (!Number.isInteger(parsed)) {
          this.classEditValidationError = runtime.t(
            "common.validation.nonNegativeInteger",
          );
          return;
        }
        nextValue = parsed;
      } else if (
        this.classEditType === "ITEM_CLASS" ||
        this.classEditType === "ITEM_GENRE"
      ) {
        nextValue = String(nextValue || "")
          .trim()
          .toUpperCase();
      } else if (typeof nextValue === "string") {
        nextValue = nextValue.trim();
      }
      this.applyClassEdit(nextValue);
    },
    closeFieldEditDialog() {
      this.resetClassEdit();
    },
    resetClassEdit() {
      this.classEditType = null;
      this.classEditTarget = null;
      this.classEditDraftValue = "";
      this.classEditSearch = "";
      this.classEditValidationError = "";
      this.selectedImgClass = "";
      this.selectedOwnerOpt = "";
      this.weaponStatsDraft = runtime.emptyWeaponStatsDraft();
    },
    closeClassEditDialog() {
      this.closeFieldEditDialog();
    },
    closeOwnerOptDialog() {
      this.closeFieldEditDialog();
    },
    selectImgClass(value) {
      this.selectedImgClass = this.normalizeLegacyIconClass(value);
      this.classEditDraftValue = this.selectedImgClass;
      this.classEditValidationError = "";
    },
  };
};
