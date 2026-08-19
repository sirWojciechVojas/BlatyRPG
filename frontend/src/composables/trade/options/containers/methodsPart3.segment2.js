export const createContainersMethodsPart3Segment2 = (runtime) => {
  return {
    syncContainerStateToStore() {
      const containerState = this.containerState;
      if (!containerState) {
        return;
      }
      const containerMap = containerState.containers.reduce(
        (acc, container) => {
          acc[Number(container.id)] = container;
          return acc;
        },
        {},
      );
      const actorMap = containerState.actors.reduce((acc, actor) => {
        acc[Number(actor.id)] = actor;
        return acc;
      }, {});
      const instanceMap = containerState.itemInstances.reduce(
        (acc, instance) => {
          acc[Number(instance.id)] = instance;
          return acc;
        },
        {},
      );
      const inventoryItems = [];
      const trashItems = [];
      containerState.containerInstanceItems.forEach((row) => {
        const container = containerMap[Number(row.containerId)];
        if (!container) {
          return;
        }
        if (container.type === "SHOP") {
          // Shop instances are synchronized to shop entries, never to BG inventory/trash.
          return;
        }
        const instance = instanceMap[Number(row.instanceId)];
        if (!instance) {
          return;
        }
        const template =
          this.templateItemsMap[Number(instance.templateId)] || {};
        const meta = this.containerInstanceMeta[instance.id] || {};
        const isTrashContainer =
          (container.type === "SYSTEM" && container.systemKey === "TRASH") ||
          container.type === "TRASH";
        const owner =
          container.type === "CHARACTER"
            ? actorMap[Number(container.actorId)]?.code || meta.OWNER || "BG1"
            : container.type === "TRASH"
              ? actorMap[Number(container.actorId)]?.code ||
                container.ownerCode ||
                meta.OWNER ||
                "BG1"
              : container.type === "SYSTEM" && container.systemKey === "DEFAULT"
                ? "DEFAULT"
                : meta.OWNER || runtime.TRASH_OWNER_GENERAL;
        const ownerOpt = isTrashContainer ? "TRASH" : "DEFAULT";
        const itemPlace = runtime.resolveItemPlace(meta, "PLECY");
        const storeItem = {
          ID: Number(instance.id),
          INV_ID: Number(instance.templateId),
          ITEM_PLACE: itemPlace,
          SLOT: itemPlace,
          PERSONAL_PSEU: meta.PERSONAL_PSEU || "Nowy",
          PERSONAL_DESC:
            meta.PERSONAL_DESC || instance.note || template.DESCRIPTION || "",
          PERSONAL_COST: Number(meta.PERSONAL_COST || 0),
          QUANTITY: Number(meta.QUANTITY || 1),
          OWNER_OPT: ownerOpt,
          OWNER: owner,
          NAME:
            meta.NAME ||
            instance.nameOverride ||
            template.NAME ||
            `Przedmiot ${instance.id}`,
          DESCRIPTION: meta.DESCRIPTION || template.DESCRIPTION || "",
          IMG_CLASS:
            meta.IMG_CLASS || template.IMG_CLASS || this.defaultIconClass(),
          PRIZE: Number(meta.PRIZE ?? template.PRIZE ?? 0),
          CHARGE: Number(meta.CHARGE ?? template.CHARGE ?? 0),
        };
        if (ownerOpt === "TRASH") {
          storeItem.TRASH_KIND =
            String(meta.TRASH_KIND || "").toUpperCase() === "TEMPLATE"
              ? "TEMPLATE"
              : "ITEM";
          storeItem.TRASH_SOURCE_ID = Number.isFinite(
            Number(meta.TRASH_SOURCE_ID),
          )
            ? Number(meta.TRASH_SOURCE_ID)
            : null;
        }
        this.containerInstanceMeta[instance.id] =
          this.cloneStoreItem(storeItem);
        if (ownerOpt === "TRASH") {
          trashItems.push(storeItem);
        } else {
          inventoryItems.push(storeItem);
        }
      });
      this.setInventoryItems(inventoryItems);
      this.setTrashItems(trashItems);
      const shopEntriesById = new Map();
      const ensureShopEntries = (shopId) => {
        if (!shopEntriesById.has(shopId)) {
          shopEntriesById.set(shopId, []);
        }
        return shopEntriesById.get(shopId);
      };
      containerState.containerTemplateItems.forEach((row) => {
        if (row.quantity === 0) {
          return;
        }
        const container = containerMap[Number(row.containerId)];
        if (container?.type !== "SHOP") {
          return;
        }
        const template = this.templateItemsMap[Number(row.templateId)] || {};
        ensureShopEntries(container.shopId).push(
          runtime.normalizeShopEntryForContainer(
            {
              INV_ID: Number(row.templateId),
              ITEM_PLACE: "STOISKO",
              SLOT: "STOISKO",
              PERSONAL_PSEU: template.NAME || `Przedmiot ${row.templateId}`,
              PERSONAL_DESC: template.DESCRIPTION || "",
              PERSONAL_COST: Number(template.PRIZE || 0),
              QUANTITY:
                row.quantity === null
                  ? 1
                  : runtime.toRoundedQuantity(Number(row.quantity), 1),
              OWNER_OPT: "DEFAULT",
              OWNER: "BG1",
              NAME: template.NAME,
              DESCRIPTION: template.DESCRIPTION || "",
              IMG_CLASS: template.IMG_CLASS,
              PRIZE: Number(template.PRIZE || 0),
              CHARGE: Number(template.CHARGE || 0),
            },
            template,
          ),
        );
      });
      containerState.containerInstanceItems.forEach((row) => {
        const container = containerMap[Number(row.containerId)];
        if (container?.type !== "SHOP") {
          return;
        }
        const instance = instanceMap[Number(row.instanceId)];
        if (!instance) {
          return;
        }
        const template =
          this.templateItemsMap[Number(instance.templateId)] || {};
        const meta = this.containerInstanceMeta[instance.id] || {};
        const itemPlace = runtime.resolveItemPlace(meta, "STOISKO");
        ensureShopEntries(container.shopId).push(
          runtime.normalizeShopEntryForContainer(
            {
              INV_ID: Number(instance.templateId),
              ITEM_PLACE: itemPlace,
              SLOT: itemPlace,
              PERSONAL_PSEU:
                meta.PERSONAL_PSEU ||
                meta.NAME ||
                instance.nameOverride ||
                template.NAME ||
                `Przedmiot ${instance.id}`,
              PERSONAL_DESC:
                meta.PERSONAL_DESC ||
                instance.note ||
                template.DESCRIPTION ||
                "",
              PERSONAL_COST: Number(meta.PERSONAL_COST ?? template.PRIZE ?? 0),
              QUANTITY: runtime.toRoundedQuantity(meta.QUANTITY, 1),
              OWNER_OPT: "DEFAULT",
              OWNER: "BG1",
              NAME:
                meta.NAME ||
                instance.nameOverride ||
                template.NAME ||
                `Przedmiot ${instance.id}`,
              DESCRIPTION:
                meta.DESCRIPTION ||
                meta.PERSONAL_DESC ||
                instance.note ||
                template.DESCRIPTION ||
                "",
              IMG_CLASS:
                meta.IMG_CLASS || template.IMG_CLASS || this.defaultIconClass(),
              PRIZE: Number(meta.PRIZE ?? template.PRIZE ?? 0),
              CHARGE: Number(meta.CHARGE ?? template.CHARGE ?? 0),
            },
            template,
          ),
        );
      });
      this.shops.forEach((shop) => {
        const shopEntries = runtime.aggregateShopEntries(
          (shopEntriesById.get(shop.id) || []).filter(Boolean),
          this.templateItemsMap || {},
        );
        this.setShopAssortment({
          shopId: shop.id,
          shopEntries,
        });
      });
      if (typeof this.persistTradingData === "function") {
        this.persistTradingData();
      }
    },
  };
};
