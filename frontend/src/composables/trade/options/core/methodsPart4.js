export const createCoreMethodsPart4 = (runtime) => {
  return {
    async handleCreateDraftTemplatesFromSelected() {
      const result = await this.createTemplatesFromSuggestions({
        suggestionIds: this.selectedSuggestionIds,
      });
      const created = Number(result?.created ?? result ?? 0);
      if (!created) {
        this.showWalletAlert(runtime.t("shop.alerts.noDraftSuggestions"));
        return;
      }
      this.persistTradingData();
      this.showWalletAlert(
        runtime.t("shop.alerts.draftTemplatesCreated", {
          count: created,
        }),
      );
    },
    async handlePreviewShopStarterAssortment() {
      const shopId = this.resolveSuggestionShopId();
      if (!shopId) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return;
      }
      const saved = await this.saveSuggestionShopProfileIfCurrent(shopId);
      if (!saved) {
        return;
      }
      const result = await this.rollShopAssortment({
        shopId,
        targetInstances: this.assortmentRollTarget,
        clearExisting: false,
        dryRun: true,
      });
      const preview = Array.isArray(result?.suggestions)
        ? result.suggestions
        : [];
      if (!preview.length) {
        this.assortmentRollPreview = [];
        this.assortmentRollPreviewMeta = null;
        this.showWalletAlert(runtime.t("shop.alerts.rollPreviewEmpty"));
        return;
      }
      const targetShop = (this.shops || []).find(
        (entry) => Number(entry.id) === Number(shopId),
      );
      const currentEntries = Array.isArray(targetShop?.shopEntries)
        ? targetShop.shopEntries
        : [];
      const currentUnique = currentEntries.length;
      const currentInstances = currentEntries.reduce(
        (sum, entry) => sum + Math.max(1, Number(entry?.QUANTITY || 1)),
        0,
      );
      const previewUnique = preview.length;
      const previewInstances = preview.reduce(
        (sum, entry) => sum + Math.max(1, Number(entry?.quantity || 1)),
        0,
      );
      this.assortmentRollPreview = preview.map((entry) => ({
        ...entry,
      }));
      this.assortmentRollPreviewMeta = {
        targetInstances: Math.max(
          8,
          Math.min(20, Number(this.assortmentRollTarget) || 12),
        ),
        previewUnique,
        previewInstances,
        currentUnique,
        currentInstances,
        deltaUnique: previewUnique - currentUnique,
        deltaInstances: previewInstances - currentInstances,
      };
      this.showWalletAlert(
        runtime.t("shop.alerts.rollPreviewReady", {
          unique: previewUnique,
          instances: previewInstances,
          target: this.assortmentRollPreviewMeta.targetInstances,
        }),
      );
    },
    async handleApplyAssortmentRollPreview() {
      const suggestions = Array.isArray(this.assortmentRollPreview)
        ? this.assortmentRollPreview
        : [];
      if (!suggestions.length) {
        this.showWalletAlert(runtime.t("shop.alerts.rollPreviewEmpty"));
        return;
      }
      const shopId = this.resolveSuggestionShopId();
      if (!shopId) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return;
      }
      this.setShopAssortment({
        shopId,
        shopEntries: [],
      });
      await this.applyShopSuggestions({
        shopId,
        suggestions,
      });
      const appliedUnique = suggestions.length;
      const appliedInstances = suggestions.reduce(
        (sum, entry) => sum + Math.max(1, Number(entry?.quantity || 1)),
        0,
      );
      this.assortmentRollPreview = [];
      this.assortmentRollPreviewMeta = null;
      this.persistTradingData();
      this.showWalletAlert(
        runtime.t("shop.alerts.rollPreviewApplied", {
          unique: appliedUnique,
          instances: appliedInstances,
        }),
      );
      this.initAssortmentState();
    },
    async handleRollShopStarterAssortment() {
      const shopId = this.resolveSuggestionShopId();
      if (!shopId) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return;
      }
      const saved = await this.saveSuggestionShopProfileIfCurrent(shopId);
      if (!saved) {
        return;
      }
      const result = await this.rollShopAssortment({
        shopId,
        targetInstances: this.assortmentRollTarget,
        clearExisting: true,
      });
      if (!result?.appliedUnique) {
        this.showWalletAlert(runtime.t("shop.alerts.noSuggestionsForRoll"));
        return;
      }
      this.assortmentRollPreview = [];
      this.assortmentRollPreviewMeta = null;
      this.persistTradingData();
      this.showWalletAlert(
        runtime.t("shop.alerts.rollApplied", {
          unique: result.appliedUnique,
          instances: result.appliedInstances,
        }),
      );
      this.initAssortmentState();
    },
    currentFormForTarget(target) {
      if (target === "template") {
        return this.templateForm || {};
      }
      if (target === "newTemplate") {
        return this.newTemplateForm || {};
      }
      return this.inventoryForm || {};
    },
    applyFormPatchForTarget(target, patch = {}) {
      if (target === "template") {
        this.templateForm = {
          ...this.templateForm,
          ...patch,
        };
        return;
      }
      if (target === "newTemplate") {
        this.newTemplateForm = {
          ...this.newTemplateForm,
          ...patch,
        };
        return;
      }
      this.inventoryForm = {
        ...this.inventoryForm,
        ...patch,
      };
    },
    currentItemClassForTarget(target) {
      return String(this.currentFormForTarget(target)?.ITEM_CLASS || "")
        .trim()
        .toUpperCase();
    },
    currentItemIdForTarget(target) {
      return String(this.currentFormForTarget(target)?.ITEM_ID || "").trim();
    },
    weaponStatsProfileForItemId(itemId) {
      const key = String(itemId || "").trim();
      if (!key) {
        return runtime.normalizeWeaponStatsDraft({});
      }
      return (
        runtime.legacyWeaponStatsById[key] ||
        runtime.normalizeWeaponStatsDraft({})
      );
    },
    weaponStatsForItemId(itemId, source = {}) {
      const key = String(itemId || "").trim();
      const profile = this.weaponStatsProfileForItemId(key);
      return runtime.normalizeWeaponStatsDraft({
        ...profile,
        ...source,
        ITEM_ID: key || source?.ITEM_ID || profile.ITEM_ID || "",
      });
    },
    weaponStatsForTarget(target) {
      const form = this.currentFormForTarget(target);
      const itemId =
        this.currentItemIdForTarget(target) ||
        runtime.classOptionsMap.ITEM_ID?.[0] ||
        "";
      const source =
        form?.WEAPON && typeof form.WEAPON === "object" ? form.WEAPON : {};
      return this.weaponStatsForItemId(itemId, source);
    },
    openWeaponStatsDialog(target) {
      this.openClassEdit("ITEM_ID", target);
    },
    closeWeaponStatsDialog() {
      this.closeFieldEditDialog();
    },
    createWeaponStatsDraft() {
      const target = this.classEditTarget;
      const itemId =
        this.currentItemIdForTarget(target) ||
        this.weaponStatsDraft?.ITEM_ID ||
        runtime.classOptionsMap.ITEM_ID?.[0] ||
        "";
      this.weaponStatsDraft = this.weaponStatsForItemId(itemId);
      this.classEditDraftValue = String(this.weaponStatsDraft?.ITEM_ID || "");
    },
    removeWeaponStats() {
      const target = this.classEditTarget;
      if (!target) {
        return;
      }
      const itemId = String(this.currentItemIdForTarget(target) || "").trim();
      this.applyFormPatchForTarget(target, {
        ITEM_ID: itemId,
        WEAPON: {},
      });
      this.resetClassEdit();
    },
    selectWeaponStatsItem(itemId) {
      const normalizedItemId = String(itemId || "").trim();
      this.weaponStatsDraft = this.weaponStatsForItemId(normalizedItemId);
      this.classEditDraftValue = normalizedItemId;
    },
  };
};
