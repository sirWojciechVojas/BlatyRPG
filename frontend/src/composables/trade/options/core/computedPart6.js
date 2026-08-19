export const createCoreComputedPart6 = (runtime) => {
  return {
    leftFlankButtons() {
      if (!this.isGM) {
        const shopButtons = this.playerVisibleShops.map((shop, index) => {
          const profile = this.shopProfiles?.[Number(shop.id)] || {};
          const label =
            shop.name ||
            profile.signboardName ||
            runtime.t("shop.defaults.shopName.withId", {
              id: shop?.id || index + 1,
            });
          const noData = runtime.t("shop.itemDetailDialog.noDataShort");
          const detailRows = [
            {
              label: runtime.t("shop.shopEditor.ownerName"),
              value: profile.ownerName || profile.ownerCode || noData,
            },
            {
              label: runtime.t("shop.shopEditor.location"),
              value: this.shopProfileOptionLabel(
                "locationType",
                profile.locationType,
              ),
            },
            {
              label: runtime.t("shop.shopEditor.legalStatus"),
              value: this.shopProfileOptionLabel(
                "legalStatus",
                profile.legalStatus,
              ),
            },
            {
              label: runtime.t("shop.shopEditor.wealthTier"),
              value: this.shopProfileOptionLabel(
                "wealthTier",
                profile.wealthTier,
              ),
            },
            {
              label: runtime.t("shop.shopEditor.reputation"),
              value: this.shopProfileOptionLabel(
                "reputation",
                profile.reputation,
              ),
            },
          ].filter((row) => row.value && row.value !== noData);
          const shopTypeLabel = this.shopTypeLabelForShop(shop);
          const tileTypeLabel = this.shopTileTypeLabel(shopTypeLabel);
          return {
            type: "shop-card",
            label,
            tileTypeLabel,
            subtitle: runtime.t("shop.modules.signboard"),
            shopTypeLabel,
            variantClass: "btn-info",
            extraClass: "shop-flank-card",
            shopId: shop.id,
            active: Number(shop.id) === Number(this.activeShopId),
            detailRows,
          };
        });
        return shopButtons;
      }
      return [
        {
          label: runtime.t("shop.modules.templates"),
          variantClass: "btn-secondary",
          mode: runtime.GM_MODES.TEMPLATES,
          active: this.gmMode === runtime.GM_MODES.TEMPLATES,
          extraClass:
            "leftGM shop-flank-asset-button shop-flank-asset-button--button01",
        },
        {
          label: runtime.t("shop.modules.defaultStack"),
          variantClass: "btn-warning",
          mode: runtime.GM_MODES.INVENTORY,
          active: this.gmMode === runtime.GM_MODES.INVENTORY,
          extraClass:
            "leftGM shop-flank-asset-button shop-flank-asset-button--button02",
        },
        {
          label: runtime.t("shop.modules.trash"),
          variantClass: "btn-info",
          mode: runtime.GM_MODES.TRASH,
          active: this.gmMode === runtime.GM_MODES.TRASH,
          extraClass:
            "leftGM shop-flank-asset-button shop-flank-asset-button--button03",
          title: runtime.t("shop.modules.trashTitle"),
        },
      ];
    },
    rightFlankButtons() {
      if (!this.isGM) {
        return [
          {
            label: runtime.t("shop.modules.walletStatus"),
            type: "wallet",
          },
          {
            label: runtime.t("shop.modules.encumbranceStatus"),
            type: "encumbrance",
            current: this.bgEncumbranceCurrent,
            limit: this.bgEncumbranceLimit,
            unitShort: this.bgEncumbranceUnitShort,
            unitName: this.bgEncumbranceUnitName,
            status: this.bgEncumbranceStatus,
            remaining: Math.max(0, this.bgEncumbranceRemaining),
            overload: Math.max(
              0,
              this.bgEncumbranceCurrent - this.bgEncumbranceLimit,
            ),
            selection: this.bgEncumbranceSelection,
            isOverLimit: this.bgEncumbranceOverLimit,
            wouldExceedOnBuy:
              this.bgEncumbranceWouldExceedLimit &&
              this.bgEncumbranceSelection > 0,
          },
        ];
      }
      const buttons = [
        {
          label: runtime.t("shop.modules.shopEditor"),
          variantClass: "btn-secondary",
          extraClass:
            "shop-flank-asset-button shop-flank-asset-button--button04",
          action: runtime.GM_MODES.SHOP_ADD_EDIT,
          active:
            this.gmMode === runtime.GM_MODES.SHOP_ADD_EDIT ||
            this.gmMode === runtime.GM_MODES.SHOP_ARTICLE_EDITOR,
          title: runtime.t("shop.modules.shopEditorTitle"),
        },
        {
          label: runtime.t("shop.modules.assortment"),
          variantClass: "btn-warning",
          extraClass:
            "shop-flank-asset-button shop-flank-asset-button--button05",
          action: runtime.GM_MODES.ASSORTMENT,
          active: this.gmMode === runtime.GM_MODES.ASSORTMENT,
          title: runtime.t("shop.modules.assortmentTitle"),
        },
        {
          label: runtime.t("shop.modules.quickTransfer"),
          variantClass: "btn-info",
          extraClass:
            "shop-flank-asset-button shop-flank-asset-button--button06",
          action: runtime.GM_MODES.ASSORTMENT_TOOLS,
          active: this.gmMode === runtime.GM_MODES.ASSORTMENT_TOOLS,
          title: runtime.t("shop.modules.quickTransferTitle"),
        },
      ];
      if (runtime.FEATURE_FLAGS.SHOW_BG_EQUIPMENT_TRASH_MODULE) {
        buttons.push({
          label: runtime.t("shop.modules.bgEquipmentTrash"),
          variantClass: "btn-warning",
          disabled: true,
          title: runtime.t("shop.modules.futureTitle"),
        });
      }
      return buttons;
    },
    imgClassOptions() {
      return [...runtime.legacySpriteIconOptions];
    },
    ownerOptOptions() {
      return Array.from(
        new Set([
          ...(runtime.classOptionsMap.OWNER_OPT || []),
          ...this.actorOwnerCodes,
        ]),
      );
    },
    weaponStatsItemOptions() {
      const target = this.classEditTarget;
      const currentForm = this.currentFormForTarget(target);
      const currentWeapon =
        currentForm?.WEAPON && typeof currentForm.WEAPON === "object"
          ? currentForm.WEAPON
          : {};
      const currentItemId = String(
        currentWeapon.ITEM_ID ||
          this.currentItemIdForTarget(target) ||
          runtime.classOptionsMap.ITEM_ID?.[0] ||
          "",
      ).trim();
      const sourceItemIds = [
        ...(runtime.classOptionsMap.ITEM_ID || []),
        currentItemId,
      ];
      const uniqueItemIds = Array.from(
        new Set(sourceItemIds.map((entry) => String(entry || "").trim())),
      ).filter(Boolean);
      return uniqueItemIds.map((value) => {
        const profile =
          String(value) === String(currentItemId)
            ? this.weaponStatsForItemId(value, currentWeapon)
            : this.weaponStatsForItemId(value);
        const name = String(profile?.NAME || "").trim();
        const type = String(profile?.TYPE || "").trim();
        const handed = String(profile?.HANDED || "").trim();
        const subtitle = [type, handed].filter(Boolean).join(" | ");
        return {
          value: String(value),
          label: name ? `${name} [${value}]` : String(value),
          subtitle,
        };
      });
    },
    weaponFeatureOptions() {
      return runtime.weaponFeatureCatalog.map((feature) => ({
        id: String(feature.id),
        name: runtime.t(feature.nameKey),
        description: runtime.t(feature.descriptionKey),
        mechanics: runtime.t(feature.mechanicsKey),
      }));
    },
    weaponStatsSourceType() {
      return this.classEditTarget === "inventory" ? "instance" : "template";
    },
    inventoryOwnerOptions() {
      const ownerCodes = new Set([
        runtime.OWNER_CODES.DEFAULT,
        ...this.actorOwnerCodes,
      ]);
      (this.inventoryItems || []).forEach((item) => {
        const code = this.normalizeInventoryOwnerCode(item?.OWNER_OPT);
        if (code === runtime.OWNER_CODES.DEFAULT || /^BG\d+$/.test(code)) {
          ownerCodes.add(code);
        }
      });
      const options = [];
      Array.from(ownerCodes)
        .sort((left, right) => left.localeCompare(right, "pl"))
        .forEach((ownerCode) => {
          options.push({
            value: ownerCode,
            label: this.inventoryOwnerLabel(ownerCode),
          });
        });
      return options;
    },
    templateItemsMap() {
      return (this.templateItems || []).reduce((acc, item) => {
        acc[Number(item.ID)] = item;
        return acc;
      }, {});
    },
    displayOwnerOpt() {
      return this.selectedOwnerOpt || this.ownerOptOptions[0] || "DEFAULT";
    },
    activeBgOwner() {
      if (!this.isGM) {
        const permittedOwners = (this.permissions?.ownerCodes || [])
          .map((ownerCode) =>
            String(ownerCode || "")
              .trim()
              .toUpperCase(),
          )
          .filter(Boolean);
        const contextOwner = String(this.context?.ownerCode || "")
          .trim()
          .toUpperCase();
        if (permittedOwners.includes(contextOwner)) {
          return contextOwner;
        }
        return (
          permittedOwners[0] ||
          this.actorOwnerCodes[0] ||
          runtime.OWNER_CODES.BG1
        );
      }
      const selectedOwner = String(
        this.selectedInventory?.OWNER_OPT ||
          this.inventoryItems?.[0]?.OWNER_OPT ||
          this.context?.ownerCode ||
          this.actorOwnerCodes[0] ||
          runtime.OWNER_CODES.BG1,
      )
        .trim()
        .toUpperCase();
      if (this.actorOwnerCodes.includes(selectedOwner)) {
        return selectedOwner;
      }
      return this.actorOwnerCodes[0] || runtime.OWNER_CODES.BG1;
    },
  };
};
