export const createCoreMethodsPart3 = (runtime) => {
  return {
    handleShopEditorFieldUpdate(payload, legacyValue) {
      const normalizedPayload =
        payload && typeof payload === "object" && !Array.isArray(payload)
          ? payload
          : {
              field: payload,
              value: legacyValue,
            };
      if (!normalizedPayload || !normalizedPayload.field) {
        return;
      }
      this.setShopEditorState({
        [normalizedPayload.field]: normalizedPayload.value,
      });
    },
    handleRollShopSignboard() {
      const nodes = Array.isArray(this.catalogNodes) ? this.catalogNodes : [];
      const typeId = String(this.shopEditorForm?.typeId || "");
      const typeNode =
        nodes.find(
          (entry) => entry.level === "type" && String(entry.id) === typeId,
        ) || null;
      const groupNode = typeNode
        ? nodes.find(
            (entry) =>
              String(entry.id) === String(typeNode.parentId || "__none__"),
          ) || null
        : null;
      const existingNames = (this.shops || [])
        .map((shop) => String(shop?.name || "").trim())
        .filter(Boolean);
      const selectedTypeId = String(typeNode?.id || typeId || "");
      const locale =
        typeof runtime.i18n.global.locale === "string"
          ? runtime.i18n.global.locale
          : runtime.i18n.global.locale.value;
      const result = runtime.drawShopSignboard({
        typeId: selectedTypeId,
        typeOptions: [
          {
            id: selectedTypeId,
            labelPl: typeNode?.namePl || typeNode?.name || typeId,
            labelEn: typeNode?.nameEn || typeNode?.name || typeId,
            category: groupNode?.namePl || groupNode?.name || "",
          },
        ],
        locale,
        profile: this.shopEditorForm || {},
        ownerName: this.shopEditorForm?.ownerName || "",
        existingNames,
      });
      if (!result?.signboardName) {
        this.showWalletAlert(runtime.t("shop.alerts.signboardGenerateFailed"));
        return;
      }
      this.setShopEditorState({
        signboardName: result.signboardName,
        signboardAltNamesText: (result.aliases || []).join(", "),
      });
      this.showWalletAlert(
        runtime.t("shop.alerts.signboardGenerated", {
          name: result.signboardName,
        }),
      );
    },
    async handleShopEditorSave() {
      const activeShopId = Number(this.activeShopId);
      if (!Number.isFinite(activeShopId)) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return false;
      }
      const form = this.shopEditorForm || {};
      const profile = this.activeShopProfile || {};
      const altNamesRaw = String(
        form.signboardAltNamesText ??
          (Array.isArray(profile.signboardAltNames)
            ? profile.signboardAltNames.join(", ")
            : ""),
      );
      const altNames = altNamesRaw
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean);
      const categoryTagsRaw = String(
        form.categoryTagsText ??
          (Array.isArray(profile.categoryTags)
            ? profile.categoryTags.join(", ")
            : ""),
      );
      const categoryTags = categoryTagsRaw
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean);
      const savedProfile = await this.saveShopProfile({
        shopId: activeShopId,
        typeId: form.typeId || profile.typeId || "",
        signboardName:
          form.signboardName || profile.signboardName || this.shopName,
        ownerCode: form.ownerCode || profile.ownerCode || "BG1",
        ownerName: form.ownerName || profile.ownerName || "",
        signboardAltNames: altNames,
        categoryTags: this.shopAutoTagsList.length
          ? this.shopAutoTagsList
          : categoryTags,
        worldProfileId:
          form.worldProfileId || profile.worldProfileId || "standard",
        locationType: form.locationType || profile.locationType || "miasto",
        legalStatus: form.legalStatus || profile.legalStatus || "legal",
        wealthTier: form.wealthTier || profile.wealthTier || "standard",
        reputation: form.reputation || profile.reputation || "neutralna",
        seasonality: form.seasonality || profile.seasonality || "caloroczny",
        counterfeitRisk: Number(
          form.counterfeitRisk ?? profile.counterfeitRisk ?? 0,
        ),
        pricingConfig: form.pricingConfig || profile.pricingConfig || {},
      });
      if (!savedProfile) {
        this.showWalletAlert(runtime.t("shop.alerts.shopProfileSaveFailed"));
        return false;
      }
      this.showWalletAlert(runtime.t("shop.alerts.shopProfileSaved"));
      return true;
    },
    async handleGenerateShopSuggestions() {
      const shopId = this.resolveSuggestionShopId();
      if (!shopId) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return;
      }
      const saved = await this.saveSuggestionShopProfileIfCurrent(shopId);
      if (!saved) {
        return;
      }
      this.assortmentRollPreview = [];
      this.assortmentRollPreviewMeta = null;
      this.setShopEditorState({
        selectedSuggestionIds: [],
      });
      const count = await this.generateShopSuggestions({
        shopId,
      });
      const size = Array.isArray(count)
        ? count.length
        : this.shopSuggestions.length;
      if (!size) {
        this.showWalletAlert(runtime.t("shop.alerts.noSuggestionsForConfig"));
        return;
      }
      this.showWalletAlert(
        runtime.t("shop.alerts.suggestionsGenerated", {
          count: size,
        }),
      );
    },
    async handleLoadMoreShopSuggestions(count = 30) {
      const shopId = this.resolveSuggestionShopId();
      if (!shopId) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return 0;
      }
      const added = await this.promoteRecommendationsToSuggestions({
        count,
        shopId,
      });
      if (!added) {
        return 0;
      }
      this.showWalletAlert(
        runtime.t("shop.alerts.suggestionsGenerated", {
          count: this.shopSuggestions.length,
        }),
      );
      return added;
    },
    handleToggleShopSuggestion(payload) {
      const id = payload?.suggestionId ?? payload;
      if (!id) {
        return;
      }
      this.toggleShopSuggestionSelection(id);
    },
    handleSetAssortmentRollTarget(value) {
      const parsed = Number(value);
      if (!Number.isFinite(parsed)) {
        return;
      }
      this.assortmentRollTarget = Math.max(8, Math.min(20, Math.round(parsed)));
      this.assortmentRollPreview = [];
      this.assortmentRollPreviewMeta = null;
    },
    handleSetGmMoveQuantity(value) {
      const parsed = Number(value);
      if (!Number.isFinite(parsed)) {
        return;
      }
      this.gmMoveQuantity = Math.max(1, Math.min(999, Math.round(parsed)));
    },
    async handleApplySelectedShopSuggestions() {
      const shopId = this.resolveSuggestionShopId();
      if (!shopId) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return;
      }
      const applied = await this.applyShopSuggestions({
        shopId,
        suggestionIds: this.selectedSuggestionIds,
      });
      if (!applied) {
        this.showWalletAlert(runtime.t("shop.alerts.noSelectedSuggestions"));
        return;
      }
      this.initAssortmentState();
      this.persistTradingData();
      this.showWalletAlert(
        runtime.t("shop.alerts.suggestionsApplied", {
          count: applied,
        }),
      );
    },
    async handleApplyAllShopSuggestions() {
      const allIds = (this.shopSuggestions || []).map(
        (entry) => entry.suggestionId,
      );
      if (!allIds.length) {
        this.showWalletAlert(runtime.t("shop.alerts.noSuggestionsToAdd"));
        return;
      }
      const shopId = this.resolveSuggestionShopId();
      if (!shopId) {
        this.showWalletAlert(runtime.t("shop.alerts.noActiveShop"));
        return;
      }
      const applied = await this.applyShopSuggestions({
        shopId,
        suggestionIds: allIds,
      });
      this.initAssortmentState();
      this.persistTradingData();
      this.showWalletAlert(
        runtime.t("shop.alerts.suggestionsAllApplied", {
          count: applied,
        }),
      );
    },
  };
};
