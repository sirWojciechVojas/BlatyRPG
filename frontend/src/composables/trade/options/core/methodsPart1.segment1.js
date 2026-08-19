export const createCoreMethodsPart1Segment1 = (runtime) => {
  return {
    ...runtime.mapActions("shop", [
      "loadTradingData",
      "persistTradingData",
      "createTemplateRecord",
      "saveTemplateRecord",
      "deleteTemplateRecord",
      "saveShopProfile",
      "createShop",
      "deleteShop",
      "updateShopActivation",
      "saveShopAssortment",
      "generateShopSuggestions",
      "promoteRecommendationsToSuggestions",
      "applyShopSuggestions",
      "createTemplatesFromSuggestions",
      "materializeShopSuggestion",
      "rollShopAssortment",
      "playerBuyFromShop",
      "playerQuoteBuyPayment",
      "playerSellToShop",
    ]),
    ...runtime.mapMutations("shop", [
      "setIsGM",
      "setGMMode",
      "setActiveShop",
      "setShopAssortment",
      "consumeShopSelection",
      "adjustWalletBrass",
      "toggleBuySelection",
      "setBuySelectionQuantity",
      "toggleSellSelection",
      "setSellSelectionQuantity",
      "setSelectedTemplateId",
      "setSelectedTrashId",
      "setSelectedInventoryId",
      "setInventoryItems",
      "setTrashItems",
      "clearSelections",
      "clearBuySelection",
      "clearSellSelection",
      "restorePlayerTradeUi",
      "addInventoryItem",
      "addInventoryStackItem",
      "addTemplateItem",
      "removeTemplateItem",
      "addTrashItem",
      "updateTemplateItem",
      "updateInventoryItem",
      "updateTrashItem",
      "removeInventoryItems",
      "removeTrashItem",
      "sellInventorySelectionToActiveShop",
      "setShopEditorState",
      "toggleShopSuggestionSelection",
      "createOrUpdateShopProfile",
      "setShopSuggestions",
      "setShopTemplateRecommendations",
    ]),
    resolveSuggestionShopId() {
      if (this.isAssortmentMode) {
        const container = this.containerById(this.assortmentRightContainerId);
        const targetShopId = Number(container?.shopId);
        if (Number.isFinite(targetShopId) && targetShopId > 0) {
          return targetShopId;
        }
      }
      const activeShopId = Number(this.activeShopId);
      return Number.isFinite(activeShopId) && activeShopId > 0
        ? activeShopId
        : null;
    },
    resetAssortmentSuggestionState() {
      this.assortmentRollPreview = [];
      this.assortmentRollPreviewMeta = null;
      this.setShopEditorState({
        selectedSuggestionIds: [],
      });
      this.setShopSuggestions([]);
      this.setShopTemplateRecommendations([]);
    },
    async saveSuggestionShopProfileIfCurrent(shopId) {
      if (Number(shopId) !== Number(this.activeShopId)) {
        return true;
      }
      return this.handleShopEditorSave();
    },
    shopTypeLabelForShop(shop = {}) {
      const profile = this.shopProfiles?.[Number(shop.id)] || {};
      const typeId = String(
        profile?.typeId ||
          shop?.typeId ||
          shop?.type_id ||
          shop?.shopType ||
          shop?.type ||
          "",
      ).trim();
      if (!typeId) {
        return runtime.t("shop.itemDetailDialog.noDataShort");
      }
      const node = (this.catalogNodes || []).find(
        (entry) => entry.level === "type" && String(entry.id) === typeId,
      );
      return String(node?.namePl || node?.name || typeId).trim();
    },
    shopTileTypeLabel(typeLabel = "") {
      const normalized = String(typeLabel || "").trim();
      if (!normalized) {
        return runtime.t("shop.itemDetailDialog.noDataShort");
      }
      const normalizedLower = normalized.toLocaleLowerCase("pl");
      const aliases = [
        {
          test: ["alchem"],
          label: "Alchemik",
        },
        {
          test: ["karcz", "tawern"],
          label: "Karczma",
        },
        {
          test: ["kuź", "kuz", "kowal", "zbroj"],
          label: "Kuźnia",
        },
        {
          test: ["ziel"],
          label: "Zielarz",
        },
        {
          test: ["mag", "mist"],
          label: "Magia",
        },
        {
          test: ["aptek"],
          label: "Apteka",
        },
        {
          test: ["kram", "ogóln", "ogoln"],
          label: "Kram",
        },
      ];
      const match = aliases.find((entry) =>
        entry.test.some((token) => normalizedLower.includes(token)),
      );
      if (match) {
        return match.label;
      }
      return normalized.split(/\s+/).filter(Boolean)[0] || normalized;
    },
  };
};
