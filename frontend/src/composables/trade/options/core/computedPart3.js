export const createCoreComputedPart3 = (runtime) => {
  return {
    shopEditorShopOptions() {
      return (this.shops || [])
        .map((shop) => ({
          value: Number(shop.id),
          label:
            shop?.isActive === false
              ? `${
                  shop.name ||
                  runtime.t("shop.defaults.shopName.withId", {
                    id: shop.id,
                  })
                } (${runtime.t("shop.activationDialog.statusInactive")})`
              : shop.name ||
                runtime.t("shop.defaults.shopName.withId", {
                  id: shop.id,
                }),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, "pl"));
    },
    canDeleteActiveShop() {
      return (this.shops || []).length > 1;
    },
    shopActivationOptions() {
      return (this.shops || [])
        .map((shop, index) => {
          const label = String(
            shop?.name ||
              runtime.t("shop.defaults.shopName.withId", {
                id: shop?.id || index + 1,
              }),
          );
          const badge = label.trim().charAt(0).toUpperCase();
          const isActive = shop?.isActive !== false;
          return {
            id: Number(shop.id),
            label,
            badge: badge || runtime.t("shop.defaults.shopBadge"),
            isActive,
            isCurrent: Number(shop.id) === Number(this.activeShopId),
          };
        })
        .sort((a, b) => a.label.localeCompare(b.label, "pl"));
    },
    shopActivationActiveCount() {
      return (this.shops || []).filter((shop) => shop?.isActive !== false)
        .length;
    },
    shopOwnerOptions() {
      const ownerOptions = (this.actors || []).map((actor) => ({
        value: actor.ownerCode || actor.code || String(actor.id),
        label: `${actor.ownerCode || actor.code || actor.id} - ${actor.name || actor.ownerCode || actor.code || actor.id}`,
      }));
      ownerOptions.push({
        value: "NPC",
        label: "NPC",
      });
      return ownerOptions;
    },
    worldProfileOptions() {
      return (this.worldProfiles || []).map((entry) => ({
        value: entry.id,
        label: entry.labelPl || entry.id,
      }));
    },
    activeWorldProfile() {
      const id = String(this.shopEditorForm?.worldProfileId || "standard");
      return (
        (this.worldProfiles || []).find((entry) => String(entry.id) === id) ||
        null
      );
    },
    activeSettlementCurrencyCode() {
      return String(
        this.activeShopProfile?.pricingConfig?.currencyPolicy
          ?.settlementCurrencyCode ||
          this.$store.state.shop.currencyDefinitions?.defaultCurrencyCode ||
          "generic",
      );
    },
    shopWorldProfileImpactText() {
      const world = this.activeWorldProfile;
      if (!world) {
        return "";
      }
      return (
        world.impactSummaryPl ||
        world.description ||
        runtime.t("shop.shopEditor.worldProfileFallback")
      );
    },
    locationTypeOptions() {
      return runtime.toShopEditorOptions("locationType");
    },
    legalStatusOptions() {
      return runtime.toShopEditorOptions("legalStatus");
    },
    wealthTierOptions() {
      return runtime.toShopEditorOptions("wealthTier");
    },
    reputationOptions() {
      return runtime.toShopEditorOptions("reputation");
    },
    seasonalityOptions() {
      return runtime.toShopEditorOptions("seasonality");
    },
    shopEditorForm() {
      return this.shopEditorState || {};
    },
    activeShopTypeNode() {
      const typeId = String(this.shopEditorForm?.typeId || "");
      if (!typeId) {
        return null;
      }
      return (
        (this.catalogNodes || []).find(
          (entry) => entry.level === "type" && entry.id === typeId,
        ) || null
      );
    },
    shopAutoTagsList() {
      const node = this.activeShopTypeNode;
      const form = this.shopEditorForm || {};
      const tags = new Set();
      if (node?.id) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.type,
            node.id,
          ),
        );
      }
      if (node?.namePl) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.profile,
            node.namePl,
          ),
        );
      }
      if (form.locationType) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.location,
            form.locationType,
          ),
        );
      }
      if (form.worldProfileId) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.world,
            form.worldProfileId,
          ),
        );
      }
      if (form.legalStatus) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.legalStatus,
            form.legalStatus,
          ),
        );
      }
      if (form.wealthTier) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.wealthTier,
            form.wealthTier,
          ),
        );
      }
      if (form.reputation) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.reputation,
            form.reputation,
          ),
        );
      }
      if (form.seasonality) {
        tags.add(
          runtime.buildShopAutoTag(
            runtime.SHOP_AUTO_TAG_PREFIXES.seasonality,
            form.seasonality,
          ),
        );
      }
      return Array.from(tags).filter(Boolean);
    },
    shopAutoTagsText() {
      return this.shopAutoTagsList.join(", ");
    },
    selectedSuggestionIds() {
      return this.shopEditorState?.selectedSuggestionIds || [];
    },
    buyActId() {
      if (!this.isGM) {
        return null;
      }
      if (this.gmMode === runtime.GM_MODES.TRASH) {
        return this.selectedInventoryId;
      }
      return this.selectedTemplateId;
    },
    sellActId() {
      if (!this.isGM) {
        return null;
      }
      if (this.gmMode === runtime.GM_MODES.TRASH) {
        return this.selectedTrashId;
      }
      if (this.gmMode === runtime.GM_MODES.INVENTORY) {
        return this.selectedInventoryId;
      }
      return null;
    },
    showBuyForm() {
      return (
        this.isGM &&
        this.gmMode === runtime.GM_MODES.INVENTORY &&
        this.selectedSellEditItem
      );
    },
    showSellForm() {
      return (
        this.isGM &&
        this.gmMode === runtime.GM_MODES.TEMPLATES &&
        this.selectedTemplate
      );
    },
    showSellAddForm() {
      return (
        this.isGM &&
        this.gmMode === runtime.GM_MODES.TEMPLATES &&
        !this.selectedTemplate
      );
    },
    showFieldEditDialog() {
      return Boolean(this.classEditType);
    },
    classEditMode() {
      if (!this.classEditType) {
        return "";
      }
      if (this.classEditType === "IMG_CLASS") {
        return "icon";
      }
      if (this.classEditType === "OWNER_OPT") {
        return "owner";
      }
      if (
        this.classEditType === "ITEM_ID" &&
        this.currentItemClassForTarget(this.classEditTarget) === "WEAPON"
      ) {
        return "weapon";
      }
      return "value";
    },
    showClassEditDialog() {
      return this.classEditMode === "icon";
    },
    showWeaponStatsDialog() {
      return this.classEditMode === "weapon";
    },
    showOwnerOptDialog() {
      return this.classEditMode === "owner";
    },
    classEditItemClass() {
      return this.currentItemClassForTarget(this.classEditTarget);
    },
  };
};
