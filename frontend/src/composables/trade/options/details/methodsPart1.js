export const createDetailsMethodsPart1 = (runtime) => {
  return {
    formatCoin(brass) {
      return runtime.formatCoinUtil(brass);
    },
    formatItemDetailValue(key, value) {
      if (value === null || value === undefined) {
        return "";
      }
      if (Array.isArray(value)) {
        return value.join(", ");
      }
      if (typeof value === "object") {
        try {
          return JSON.stringify(value);
        } catch (error) {
          return String(value);
        }
      }
      return String(value);
    },
    extractItemDetailStats(item) {
      if (!item) {
        return "";
      }
      const weapon = item.WEAPON || item.weapon;
      if (weapon && typeof weapon === "object" && !Array.isArray(weapon)) {
        const parts = Object.entries(weapon)
          .filter(
            ([, value]) =>
              value !== null && value !== undefined && value !== "",
          )
          .map(
            ([key, value]) =>
              `${key}: ${this.formatItemDetailValue(key, value)}`,
          );
        if (parts.length) {
          return parts.join(", ");
        }
      }
      const statsKeys = [
        "STATS",
        "stats",
        "STATISTICS",
        "STATS_DESC",
        "STATS_TEXT",
        "STATS_DATA",
        "STAT_BLOCK",
      ];
      for (const key of statsKeys) {
        if (
          Object.prototype.hasOwnProperty.call(item, key) &&
          item[key] !== null &&
          item[key] !== undefined &&
          item[key] !== ""
        ) {
          return this.formatItemDetailValue(key, item[key]);
        }
      }
      const fallbackKeys = [
        "ATK",
        "DEF",
        "DMG",
        "ARMOR",
        "RANGE",
        "SPEED",
        "CRIT",
        "BLOCK",
      ];
      const parts = [];
      fallbackKeys.forEach((key) => {
        if (
          Object.prototype.hasOwnProperty.call(item, key) &&
          item[key] !== null &&
          item[key] !== undefined &&
          item[key] !== ""
        ) {
          parts.push(`${key}: ${item[key]}`);
        }
      });
      return parts.join(", ");
    },
    extractItemDetailWeight(item) {
      if (!item) {
        return "";
      }
      const weightKeys = [
        "WEIGHT",
        "weight",
        "WEIGHT_KG",
        "WEIGHT_G",
        "MASS",
        "mass",
        "LOAD",
        "load",
      ];
      for (const key of weightKeys) {
        if (
          Object.prototype.hasOwnProperty.call(item, key) &&
          item[key] !== null &&
          item[key] !== undefined &&
          item[key] !== ""
        ) {
          const value = item[key];
          if (key === "WEIGHT_G") {
            return `${value} g`;
          }
          if (key === "WEIGHT_KG") {
            return `${value} kg`;
          }
          return this.formatItemDetailValue(key, value);
        }
      }
      const weaponLoad =
        item.WEAPON?.LOAD ?? item.weapon?.LOAD ?? item.weapon?.load;
      if (
        weaponLoad !== null &&
        weaponLoad !== undefined &&
        weaponLoad !== ""
      ) {
        return this.formatItemDetailValue("LOAD", weaponLoad);
      }
      return "";
    },
    toggleItemDetailNicknameMode() {
      this.itemDetailNicknameMode =
        this.itemDetailNicknameMode === "replace" ? "append" : "replace";
    },
    async applyItemDetailNickname() {
      if (!this.itemDetailItem) {
        return;
      }
      const nickname = String(this.itemDetailNickname || "").trim();
      const updated = {
        ...this.itemDetailItem,
        PERSONAL_PSEU: nickname,
      };
      await this.applyItemDetailUpdate(updated);
    },
    async applyItemDetailUpdate(updated) {
      if (!updated || !updated.ID) {
        return;
      }
      const id = Number(updated.ID);
      if (!Number.isFinite(id)) {
        return;
      }
      const { inventoryItems, trashItems, templateItems } =
        this.$store.state.shop || {};
      if (Array.isArray(inventoryItems)) {
        const index = inventoryItems.findIndex(
          (entry) => Number(entry.ID) === id,
        );
        if (index >= 0) {
          if (runtime.isShopApiEnabled()) {
            const saved = await this.$store.dispatch("shop/saveItemInstance", {
              id,
              name: updated.PERSONAL_PSEU || updated.NAME || "",
              description: updated.PERSONAL_DESC || updated.DESCRIPTION || "",
              price: updated.PERSONAL_COST ?? updated.PRIZE,
              icon: updated.IMG_CLASS,
              charge: updated.CHARGE,
            });
            if (!saved) {
              this.showWalletAlert(
                runtime.t("shop.alerts.inventorySaveFailed"),
                {
                  zone: "sell",
                  type: "error",
                  title: runtime.t("shop.workspace.status.error"),
                },
              );
              return;
            }
          }
          this.itemDetailItem = {
            ...updated,
          };
          this.itemDetailNickname = updated.PERSONAL_PSEU || "";
          this.updateInventoryItem(updated);
          return;
        }
      }
      if (Array.isArray(trashItems)) {
        const index = trashItems.findIndex((entry) => Number(entry.ID) === id);
        if (index >= 0) {
          if (runtime.isShopApiEnabled()) {
            const saved = await this.$store.dispatch("shop/saveItemInstance", {
              id,
              name: updated.PERSONAL_PSEU || updated.NAME || "",
              description: updated.PERSONAL_DESC || updated.DESCRIPTION || "",
              price: updated.PERSONAL_COST ?? updated.PRIZE,
              icon: updated.IMG_CLASS,
              charge: updated.CHARGE,
            });
            if (!saved) {
              this.showWalletAlert(
                runtime.t("shop.alerts.inventorySaveFailed"),
                {
                  zone: "sell",
                  type: "error",
                  title: runtime.t("shop.workspace.status.error"),
                },
              );
              return;
            }
          }
          this.itemDetailItem = {
            ...updated,
          };
          this.itemDetailNickname = updated.PERSONAL_PSEU || "";
          this.updateTrashItem(updated);
          return;
        }
      }
      if (Array.isArray(templateItems)) {
        const index = templateItems.findIndex(
          (entry) => Number(entry.ID) === id,
        );
        if (index >= 0) {
          const saved =
            typeof this.saveTemplateRecord === "function"
              ? await this.saveTemplateRecord(updated)
              : null;
          if (!saved) {
            this.showWalletAlert(runtime.t("shop.alerts.templateSaveFailed"), {
              zone: "sell",
              type: "error",
              title: "Błąd zapisu",
            });
            return;
          }
          this.itemDetailItem = {
            ...saved,
          };
          this.itemDetailNickname = saved.PERSONAL_PSEU || "";
        }
      }
    },
    openItemDetailDialog(item, source) {
      if (!item) {
        return;
      }
      if (!this.inventoryDetailSpriteUrl) {
        runtime
          .loadInventoryDetailSprite()
          .then((url) => {
            this.inventoryDetailSpriteUrl = url;
          })
          .catch(() => undefined);
      }
      let detailSource = source || "";
      if (detailSource === "sell" && this.isGM) {
        if (this.gmMode === "trash") {
          detailSource = "trash";
        } else if (this.gmMode === "inventory") {
          detailSource = "inventory";
        }
      }
      this.itemDetailItem = {
        ...item,
      };
      this.itemDetailSource = detailSource;
      this.itemDetailNickname = item.PERSONAL_PSEU || "";
      this.showItemDetailDialog = true;
    },
    closeItemDetailDialog() {
      this.showItemDetailDialog = false;
      this.itemDetailItem = null;
      this.itemDetailSource = "";
      this.itemDetailNickname = "";
    },
  };
};
