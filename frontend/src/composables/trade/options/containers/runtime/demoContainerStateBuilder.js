export const buildDemoContainerState = (
  vm,
  runtime,
  { templates, shops, inventoryItems, trashItems },
) => {
  const ownerCodes = new Set(vm.actorOwnerCodes || []);
  inventoryItems.forEach((item) => {
    const code = String(item.OWNER || "")
      .trim()
      .toUpperCase();
    if (/^BG\d+$/.test(code)) {
      ownerCodes.add(code);
    }
  });
  trashItems.forEach((item) => {
    const code = String(item.OWNER || "")
      .trim()
      .toUpperCase();
    if (/^BG\d+$/.test(code)) {
      ownerCodes.add(code);
    }
  });
  const actors = Array.from(ownerCodes)
    .sort()
    .map((code, index) => ({
      id:
        vm.actorByOwnerCode?.[code]?.id ??
        vm.actors?.find(
          (actor) =>
            String(actor.ownerCode || actor.code).toUpperCase() === code,
        )?.id ??
        index + 1,
      type: "PC",
      code,
      name: vm.actorByOwnerCode?.[code]?.name || code,
    }));
  let nextContainerId = 1;
  const containers = [
    {
      id: nextContainerId++,
      type: "SYSTEM",
      systemKey: "DEFAULT",
      name: "DEFAULT",
    },
    {
      id: nextContainerId++,
      type: "SYSTEM",
      systemKey: "TRASH",
      capacity: runtime.GENERAL_TRASH_SLOT_CAPACITY,
      name: "TRASH",
    },
  ];
  const actorContainerByCode = {};
  const playerTrashContainerByCode = {};
  actors.forEach((actor) => {
    const actorContainerId = nextContainerId++;
    actorContainerByCode[actor.code] = actorContainerId;
    containers.push({
      id: actorContainerId,
      type: "CHARACTER",
      actorId: actor.id,
      name: `${actor.code} - Ekwipunek`,
    });
    const trashContainerId = nextContainerId++;
    playerTrashContainerByCode[actor.code] = trashContainerId;
    containers.push({
      id: trashContainerId,
      type: "TRASH",
      actorId: actor.id,
      ownerCode: actor.code,
      capacity: runtime.PLAYER_TRASH_SLOT_CAPACITY,
      name: `${actor.name} - ${runtime.t("shop.trashView.discardZoneSuffix")}`,
    });
  });
  const shopContainerById = {};
  shops.forEach((shop) => {
    const id = nextContainerId++;
    shopContainerById[shop.id] = id;
    containers.push({
      id,
      type: "SHOP",
      shopId: shop.id,
      name: `Sklep ${shop.name}`,
    });
  });
  const itemTemplates = templates.map((item) => ({
    id: Number(item.ID),
    name: item.NAME,
    category: item.ITEM_CLASS || item.ITEM_GENRE || "",
    isStackable: vm.isTemplateStackable(item),
    basePrice: Number(item.PRIZE || 0),
    baseData: {
      ...item,
    },
  }));
  const itemInstances = [];
  const containerInstanceItems = [];
  const instanceMeta = {};
  const defaultContainerId = containers.find(
    (container) =>
      container.type === "SYSTEM" && container.systemKey === "DEFAULT",
  )?.id;
  const generalTrashContainerId = containers.find(
    (container) =>
      container.type === "SYSTEM" && container.systemKey === "TRASH",
  )?.id;
  const nextInstanceId = () => {
    const ids = itemInstances
      .map((entry) => Number(entry.id))
      .filter(Number.isFinite);
    return ids.length ? Math.max(...ids) + 1 : 1;
  };
  const addInstance = (item, containerId) => {
    let instanceId = Number(item.ID);
    if (!Number.isFinite(instanceId)) {
      instanceId = nextInstanceId();
    }
    while (itemInstances.some((entry) => Number(entry.id) === instanceId)) {
      instanceId = nextInstanceId();
    }
    itemInstances.push({
      id: instanceId,
      templateId: Number(item.INV_ID),
      nameOverride: item.NAME || null,
      dataOverride: null,
      note: item.PERSONAL_DESC || item.DESCRIPTION || "",
    });
    containerInstanceItems.push({
      containerId,
      instanceId,
      priceOverride: null,
    });
    instanceMeta[instanceId] = vm.cloneStoreItem({
      ...item,
      ID: instanceId,
    });
  };
  inventoryItems.forEach((item) => {
    const owner = String(item.OWNER || "")
      .trim()
      .toUpperCase();
    let containerId = defaultContainerId;
    const ownerOpt = String(item.OWNER_OPT || "")
      .trim()
      .toUpperCase();
    if (ownerOpt === "TRASH") {
      containerId =
        playerTrashContainerByCode[owner] ??
        generalTrashContainerId ??
        defaultContainerId;
    } else if (
      ownerOpt === runtime.OWNER_CODES.DEFAULT ||
      owner === "DEFAULT"
    ) {
      containerId = defaultContainerId;
    } else if (actorContainerByCode[owner]) {
      containerId = actorContainerByCode[owner];
    }
    addInstance(item, containerId);
  });
  trashItems.forEach((item) => {
    const owner = String(item.OWNER || "")
      .trim()
      .toUpperCase();
    const containerId =
      playerTrashContainerByCode[owner] ??
      generalTrashContainerId ??
      defaultContainerId;
    addInstance(
      {
        ...item,
        OWNER_OPT: "TRASH",
      },
      containerId,
    );
  });
  const containerTemplateItems = [];
  shops.forEach((shop) => {
    const containerId = shopContainerById[shop.id];
    if (!containerId) {
      return;
    }
    const shopEntries = runtime.shopEntriesForStore(shop);
    shopEntries.forEach((entry) => {
      const normalized = runtime.normalizeShopEntryForContainer(
        entry,
        vm.templateItemsMap?.[
          Number(runtime.resolveTemplateIdFromEntry(entry))
        ] || {},
      );
      if (!normalized) {
        return;
      }
      addInstance(
        {
          ...normalized,
          ID: undefined,
          QUANTITY: runtime.toRoundedQuantity(normalized.QUANTITY, 1),
        },
        containerId,
      );
    });
  });
  vm.containerInstanceMeta = instanceMeta;
  vm.containerState = {
    containers,
    actors,
    shops: shops.map((shop) => ({
      id: shop.id,
      code: `SHOP${shop.id}`,
      name: shop.name,
      ownerActorId: null,
    })),
    itemTemplates,
    itemInstances,
    containerTemplateItems,
    containerInstanceItems,
    itemMovements: [],
  };
  vm.containerUndoStack = [];
};
