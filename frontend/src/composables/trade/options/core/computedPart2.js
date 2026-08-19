export const createCoreComputedPart2 = (runtime) => {
  return {
    resolvedIconSize() {
      return runtime.clampTradeIconSize(this.iconSize);
    },
    styleVars() {
      return {
        "--trade-bg": `url("${runtime.bgTrading}")`,
        "--trade-gui": `url("${runtime.gui}")`,
        "--trade-frame": `url("${runtime.frame}")`,
        "--trade-titlebar": `url("${runtime.titleBar}")`,
        "--trade-gm": `url("${runtime.gmBadge}")`,
        "--trade-gm-hover": `url("${runtime.gmBadgeHover}")`,
        "--trade-crown": `url("${runtime.crownImg}")`,
        "--trade-shilling": `url("${runtime.shillingImg}")`,
        "--trade-brass": `url("${runtime.brassImg}")`,
        "--trade-inventory-sprite": `url("${runtime.inventorySprite}")`,
        "--trade-inventory-sprite-detail": `url("${
          this.inventoryDetailSpriteUrl || runtime.inventorySprite
        }")`,
        "--trade-inventory-border-magic": `url("${runtime.inventoryBorderMagic}")`,
        "--trade-inventory-border-rare": `url("${runtime.inventoryBorderRare}")`,
        "--trade-inventory-border-unique": `url("${runtime.inventoryBorderUnique}")`,
        "--trade-icon-size": `${this.resolvedIconSize}px`,
      };
    },
    isBuyDataLoading() {
      return this.loadingBuy || (!this.tradeDataLoaded && !this.errorBuy);
    },
    isSellDataLoading() {
      return this.loadingSell || (!this.tradeDataLoaded && !this.errorSell);
    },
    buyItemClass() {
      if (!this.isGM) {
        return "individual";
      }
      if (this.gmMode === runtime.GM_MODES.TRASH) {
        return "trashInd";
      }
      if (this.gmMode === runtime.GM_MODES.TEMPLATES) {
        return "template";
      }
      return "trashTemp";
    },
    sellItemClass() {
      if (!this.isGM) {
        return "individual";
      }
      if (
        this.gmMode === runtime.GM_MODES.TRASH ||
        this.gmMode === runtime.GM_MODES.INVENTORY
      ) {
        return "trashInd";
      }
      return "individual";
    },
    isShopAddEditMode() {
      return (
        this.isGM &&
        (this.gmMode === runtime.GM_MODES.SHOP_ADD_EDIT ||
          this.gmMode === runtime.GM_MODES.SHOP_ARTICLE_EDITOR)
      );
    },
    isTrashMode() {
      return this.isGM && this.gmMode === runtime.GM_MODES.TRASH;
    },
    normalizedTrashZoneOwnerCode() {
      return this.normalizeTrashOwnerCode(this.trashZoneOwnerCode);
    },
    actorOwnerCodes() {
      const codes = new Set(this.permissions?.ownerCodes || []);
      (this.actors || []).forEach((actor) => {
        const code = String(actor?.ownerCode || actor?.code || "")
          .trim()
          .toUpperCase();
        if (code) {
          codes.add(code);
        }
      });
      return Array.from(codes).filter(Boolean);
    },
    actorByOwnerCode() {
      return (this.actors || []).reduce((map, actor) => {
        const code = String(actor?.ownerCode || actor?.code || "")
          .trim()
          .toUpperCase();
        if (code) {
          map[code] = actor;
        }
        return map;
      }, {});
    },
    trashZoneOptions() {
      const preferredOwners = this.actorOwnerCodes;
      const dynamicOwners = new Set();
      (this.inventoryItems || []).forEach((item) => {
        const code = this.normalizeTrashOwnerCode(item?.OWNER);
        if (code !== runtime.TRASH_OWNER_GENERAL) {
          dynamicOwners.add(code);
        }
      });
      (this.trashItems || []).forEach((item) => {
        const code = this.normalizeTrashOwnerCode(item?.OWNER);
        if (code !== runtime.TRASH_OWNER_GENERAL) {
          dynamicOwners.add(code);
        }
      });
      const owners = Array.from(
        new Set([...preferredOwners, ...Array.from(dynamicOwners)]),
      );
      const options = [
        {
          value: runtime.TRASH_OWNER_GENERAL,
          label: runtime.t("shop.trashView.generalZone"),
        },
      ];
      owners
        .sort((left, right) => left.localeCompare(right, "pl"))
        .forEach((ownerCode) => {
          const ownerLabel = this.trashOwnerLabel(ownerCode);
          const zoneLabel = runtime.t("shop.trashView.ownerZone", {
            ownerCode,
            ownerLabel,
            suffix: runtime.t("shop.trashView.discardZoneSuffix"),
          });
          options.push({
            value: ownerCode,
            label: zoneLabel,
            ownerLabel,
          });
        });
      return options.map((option) => {
        const count = this.trashItemsForOwner(option.value).length;
        const capacity = this.trashCapacityForOwner(option.value);
        const numericCapacity = Number(capacity);
        const isLimited =
          capacity !== null &&
          capacity !== undefined &&
          Number.isFinite(numericCapacity) &&
          numericCapacity >= 0;
        return {
          ...option,
          count,
          capacity: isLimited ? numericCapacity : null,
          free: isLimited ? Math.max(0, numericCapacity - count) : null,
          full: isLimited ? count >= numericCapacity : false,
        };
      });
    },
    activeTrashZoneCount() {
      return this.trashItemsForOwner(this.normalizedTrashZoneOwnerCode).length;
    },
    inventoryOwnerFilterOptions() {
      if (!this.isGM || this.gmMode !== runtime.GM_MODES.INVENTORY) {
        return [];
      }
      const allEntry = {
        value: "all",
        label: runtime.t("shop.filters.sellOwnerAll"),
        count: 0,
      };
      const grouped = new Map([
        [
          runtime.OWNER_CODES.DEFAULT,
          {
            value: runtime.OWNER_CODES.DEFAULT,
            label: this.inventoryOwnerLabel(runtime.OWNER_CODES.DEFAULT),
            count: 0,
          },
        ],
      ]);
      this.actorOwnerCodes.forEach((code) => {
        grouped.set(code, {
          value: code,
          label: this.inventoryOwnerLabel(code),
          count: 0,
        });
      });
      (this.sellItems || []).forEach((item) => {
        if (
          String(
            item?.OWNER_OPT || runtime.OWNER_CODES.DEFAULT,
          ).toUpperCase() === runtime.OWNER_CODES.TRASH
        ) {
          return;
        }
        allEntry.count += 1;
        const ownerCode = this.normalizeInventoryOwnerCode(item?.OWNER_OPT);
        const current = grouped.get(ownerCode) || {
          value: ownerCode,
          label: this.inventoryOwnerLabel(ownerCode),
          count: 0,
        };
        current.count += 1;
        grouped.set(ownerCode, current);
      });
      const bgCodePattern = /^BG(\d+)$/;
      const ownerOptions = Array.from(grouped.values()).sort((left, right) => {
        const leftCode = String(left?.value || "").toUpperCase();
        const rightCode = String(right?.value || "").toUpperCase();
        if (
          leftCode === runtime.OWNER_CODES.DEFAULT &&
          rightCode !== runtime.OWNER_CODES.DEFAULT
        ) {
          return -1;
        }
        if (
          rightCode === runtime.OWNER_CODES.DEFAULT &&
          leftCode !== runtime.OWNER_CODES.DEFAULT
        ) {
          return 1;
        }
        const leftBg = leftCode.match(bgCodePattern);
        const rightBg = rightCode.match(bgCodePattern);
        if (leftBg && rightBg) {
          return Number(leftBg[1]) - Number(rightBg[1]);
        }
        return leftCode.localeCompare(rightCode, "pl");
      });
      return [allEntry, ...ownerOptions];
    },
    visibleSellItems() {
      if (!this.isTrashMode) {
        if (this.isGM && this.gmMode === runtime.GM_MODES.INVENTORY) {
          const activeOwner = this.normalizeInventoryOwnerCode(
            this.inventoryOwnerCodeFilter,
            {
              allowAll: true,
            },
          );
          if (activeOwner === "all") {
            return this.sellItems;
          }
          return (this.sellItems || []).filter(
            (item) =>
              this.normalizeInventoryOwnerCode(item?.OWNER_OPT) === activeOwner,
          );
        }
        return this.sellItems;
      }
      const activeOwner = this.normalizedTrashZoneOwnerCode;
      return (this.sellItems || []).filter(
        (item) => this.normalizeTrashOwnerCode(item?.OWNER) === activeOwner,
      );
    },
    shopTypeOptions() {
      return (this.catalogNodes || [])
        .filter((entry) => entry.level === "type")
        .map((entry) => ({
          value: entry.id,
          label: entry.namePl,
          description: entry.descriptionPl || "",
        }))
        .sort((a, b) => a.label.localeCompare(b.label, "pl"));
    },
  };
};
