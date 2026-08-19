export const createContainersMethodsPart5 = (runtime) => {
  return {
    containerItemsFor(containerId) {
      if (!containerId) {
        return [];
      }
      const instances = this.containerState?.containerInstanceItems || [];
      const result = [];
      instances
        .filter((row) => Number(row.containerId) === Number(containerId))
        .forEach((row) => {
          const instance = this.getInstanceById(row.instanceId);
          const template = this.getTemplateById(instance?.templateId);
          const meta = this.containerInstanceMeta[instance?.id] || {};
          const name =
            meta.NAME ||
            instance?.nameOverride ||
            template?.name ||
            `Przedmiot ${row.instanceId}`;
          const description =
            meta.PERSONAL_DESC ||
            meta.DESCRIPTION ||
            instance?.note ||
            template?.category ||
            "Przedmiot";
          const imgClass =
            meta.IMG_CLASS ||
            template?.baseData?.IMG_CLASS ||
            template?.imgClass ||
            null;
          const quantityRaw = Number(meta.QUANTITY);
          const quantity = Number.isFinite(quantityRaw)
            ? Math.max(1, Math.round(quantityRaw))
            : 1;
          const quantityLabel = quantity > 1 ? `x${quantity}` : null;
          const stackCandidateKey = runtime.stackCandidateGroupKey({
            INV_ID: Number(instance?.templateId ?? 0),
            ITEM_PLACE: runtime.resolveItemPlace(meta, ""),
            SLOT: runtime.resolveItemPlace(meta, ""),
            PERSONAL_PSEU: meta.PERSONAL_PSEU || name,
            PERSONAL_DESC: description,
            PERSONAL_COST: Number(
              meta.PERSONAL_COST ?? meta.PRIZE ?? template?.basePrice ?? 0,
            ),
            IMG_CLASS: imgClass || "",
          });
          result.push({
            key: `i:${row.instanceId}`,
            type: "instance",
            instanceId: row.instanceId,
            templateId: instance?.templateId ?? null,
            name,
            description,
            badge: "",
            quantity,
            quantityLabel,
            imgClass,
            stackCandidateKey,
            stackCandidateCount: 1,
            searchText: `${name} ${description}`.toLowerCase(),
          });
        });
      const stackGroups = this.similarStackCount(result);
      result.forEach((item) => {
        const count =
          stackGroups.get(String(item.stackCandidateKey || "")) || 0;
        if (count > 1) {
          item.stackCandidateCount = count;
        }
      });
      return result.sort((a, b) => a.name.localeCompare(b.name, "pl"));
    },
    containerItemInlineLabel(item) {
      if (!item) {
        return "";
      }
      if (item.quantityLabel) {
        return `${item.name} ${item.quantityLabel}`.trim();
      }
      return item.name;
    },
    filteredContainerItems(containerId) {
      const items = this.containerItemsFor(containerId);
      const term = String(this.assortmentSearch || "")
        .trim()
        .toLowerCase();
      if (!term) {
        return items;
      }
      return items.filter((item) => item.searchText.includes(term));
    },
    toggleContainerSelection(itemKey, side) {
      const list =
        side === "right"
          ? this.assortmentRightSelectedKeys
          : this.assortmentLeftSelectedKeys;
      const index = list.indexOf(itemKey);
      if (index >= 0) {
        list.splice(index, 1);
      } else {
        list.push(itemKey);
      }
    },
    isContainerSelected(itemKey, side) {
      return side === "right"
        ? this.assortmentRightSelectedKeys.includes(itemKey)
        : this.assortmentLeftSelectedKeys.includes(itemKey);
    },
    async moveContainerSelection(direction) {
      const fromContainerId =
        direction === "rightToLeft"
          ? this.assortmentRightContainerId
          : this.assortmentLeftContainerId;
      const toContainerId =
        direction === "rightToLeft"
          ? this.assortmentLeftContainerId
          : this.assortmentRightContainerId;
      const notificationZone = direction === "rightToLeft" ? "sell" : "buy";
      if (!fromContainerId || !toContainerId) {
        this.showWalletAlert(runtime.t("shop.alerts.selectContainers"), {
          zone: notificationZone,
          type: "warning",
        });
        return;
      }
      if (Number(fromContainerId) === Number(toContainerId)) {
        this.showWalletAlert(
          runtime.t("shop.alerts.selectDifferentContainers"),
          {
            zone: notificationZone,
            type: "warning",
          },
        );
        return;
      }
      const fromContainer = this.containerById(fromContainerId);
      const toContainer = this.containerById(toContainerId);
      if (this.isAssortmentMode) {
        const isSystemContainer = (container) =>
          container?.type === "SYSTEM" &&
          (container?.systemKey === runtime.SYSTEM_CONTAINER_KEYS.DEFAULT ||
            container?.systemKey === runtime.SYSTEM_CONTAINER_KEYS.TRASH);
        if (direction === "leftToRight") {
          if (!isSystemContainer(fromContainer)) {
            this.showWalletAlert(
              runtime.t("shop.alerts.sourceMustBeStackOrTrash"),
              {
                zone: notificationZone,
                type: "warning",
              },
            );
            return;
          }
          if (toContainer?.type !== "SHOP") {
            this.showWalletAlert(runtime.t("shop.alerts.selectTargetShop"), {
              zone: notificationZone,
              type: "warning",
            });
            return;
          }
        } else if (direction === "rightToLeft") {
          if (fromContainer?.type !== "SHOP") {
            this.showWalletAlert(runtime.t("shop.alerts.sourceMustBeShop"), {
              zone: notificationZone,
              type: "warning",
            });
            return;
          }
          if (!isSystemContainer(toContainer)) {
            this.showWalletAlert(
              runtime.t("shop.alerts.targetMustBeStackOrTrash"),
              {
                zone: notificationZone,
                type: "warning",
              },
            );
            return;
          }
        } else {
          this.showWalletAlert(
            runtime.t("shop.alerts.unknownTransferDirection"),
            {
              zone: notificationZone,
              type: "error",
            },
          );
          return;
        }
      }
      const keys =
        direction === "rightToLeft"
          ? this.assortmentRightSelectedKeys
          : this.assortmentLeftSelectedKeys;
      if (!keys.length) {
        this.showWalletAlert(runtime.t("shop.alerts.noItemsSelected"), {
          zone: notificationZone,
          type: "warning",
        });
        return;
      }
      const movementStart = this.containerState.itemMovements.length;
      const actions = [];
      const availableItems = this.containerItemsFor(fromContainerId);
      keys.forEach((key) => {
        const item = availableItems.find((entry) => entry.key === key);
        if (!item) {
          return;
        }
        if (item.type === "template") {
          const action = this.buildTemplateUndoAction(
            item.templateId,
            fromContainerId,
            toContainerId,
          );
          const quantity =
            item.quantity === null
              ? null
              : Math.max(0, Number(item.quantity || 0));
          if (quantity === 0 && quantity !== null) {
            return;
          }
          actions.push(action);
          runtime.moveTemplateStack(
            this.containerState,
            item.templateId,
            fromContainerId,
            toContainerId,
            quantity,
            "transfer",
          );
          return;
        }
        const action = this.buildInstanceUndoAction(item.instanceId);
        actions.push(action);
        runtime.moveInstance(
          this.containerState,
          item.instanceId,
          toContainerId,
          "transfer",
        );
      });
      const movementCount =
        this.containerState.itemMovements.length - movementStart;
      if (actions.length) {
        this.containerUndoStack.push({
          kind: "group",
          actions,
          movementCount,
        });
      }
      keys.splice(0, keys.length);
      this.syncContainerStateToStore();
      const affectedShopIds = Array.from(
        new Set(
          [fromContainer, toContainer]
            .filter((container) => container?.type === "SHOP")
            .map((container) => Number(container.shopId))
            .filter((shopId) => Number.isFinite(shopId)),
        ),
      );
      if (
        affectedShopIds.length &&
        typeof this.saveShopAssortment === "function"
      ) {
        for (const shopId of affectedShopIds) {
          const saveResult = await this.saveShopAssortment({
            shopId,
          });
          if (!saveResult?.ok) {
            runtime.notifyContainerInfo({
              zone: notificationZone,
              type: "error",
              title: "Nie zapisano asortymentu",
              message: "Backend odrzucil zapis zmian w sklepie.",
              details: saveResult?.reason || "api_error",
            });
            return;
          }
        }
        this.buildContainerStateFromStore();
      }
      runtime.notifyContainerInfo({
        zone: notificationZone,
        type: "success",
        title: "Transfer zakończony",
        message:
          direction === "rightToLeft"
            ? "Przeniesiono ze sklepu"
            : "Dodano do asortymentu",
      });
    },
  };
};
