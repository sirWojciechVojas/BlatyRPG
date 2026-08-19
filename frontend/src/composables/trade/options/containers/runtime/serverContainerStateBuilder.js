export const buildServerContainerState = (
  vm,
  runtime,
  { templates, shops, inventoryItems, trashItems, serverState },
) => {
  if (Array.isArray(serverState?.containers) && serverState.containers.length) {
    const actors = (vm.actors || []).map((actor) => ({
      id: actor.id,
      type: "PC",
      code: String(actor.ownerCode || actor.code || "").toUpperCase(),
      name: actor.name || actor.ownerCode || actor.code || String(actor.id),
      avatar: actor.avatar ?? actor.avatarUrl ?? "",
      avatarUrl: actor.avatarUrl ?? "",
      assetSetId: actor.assetSetId ?? null,
      assets: actor.assets ?? {},
    }));
    const actorByOwnerCode = actors.reduce((map, actor) => {
      if (actor.code) {
        map[actor.code] = actor;
      }
      return map;
    }, {});
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
    const itemInstances = (serverState.itemInstances || []).map((item) => ({
      id: Number(item.id),
      templateId: Number(item.template_id ?? item.templateId),
      nameOverride: item.name_override ?? item.nameOverride ?? null,
      dataOverride:
        item.data_override_json ??
        item.dataOverride ??
        item.data_override ??
        {},
      note: item.note || "",
    }));
    const existingItems = [...inventoryItems, ...trashItems];
    vm.containerInstanceMeta = itemInstances.reduce((map, instance) => {
      const legacy = existingItems.find(
        (item) => Number(item.ID) === Number(instance.id),
      );
      map[instance.id] = {
        ...(instance.dataOverride || {}),
        ...(legacy || {}),
      };
      return map;
    }, {});
    vm.containerState = {
      containers: serverState.containers.map((container) => {
        const ownerCode = String(
          container.owner_code ?? container.ownerCode ?? "",
        ).toUpperCase();
        return {
          id: Number(container.id),
          type: container.container_type ?? container.type,
          systemKey: container.system_key ?? container.systemKey ?? null,
          ownerCode: ownerCode || null,
          actorId: actorByOwnerCode[ownerCode]?.id ?? null,
          shopId: Number(container.shop_id ?? container.shopId) || null,
          name: container.name || "",
          capacity:
            container.capacity === null || container.capacity === undefined
              ? null
              : Number(container.capacity),
        };
      }),
      actors,
      shops: shops.map((shop) => ({
        id: Number(shop.id),
        code: `SHOP${shop.id}`,
        name: shop.name,
        ownerActorId: null,
      })),
      itemTemplates,
      itemInstances,
      containerTemplateItems: (serverState.templateRows || []).map((row) => ({
        containerId: Number(row.container_id ?? row.containerId),
        templateId: Number(row.template_id ?? row.templateId),
        quantity:
          row.quantity === null || row.quantity === undefined
            ? null
            : Number(row.quantity),
        priceOverride: row.price_override ?? row.priceOverride ?? null,
      })),
      containerInstanceItems: (serverState.instanceRows || []).map((row) => ({
        containerId: Number(row.container_id ?? row.containerId),
        instanceId: Number(row.instance_id ?? row.instanceId),
        priceOverride: row.price_override ?? row.priceOverride ?? null,
      })),
      itemMovements: [],
    };
    vm.containerUndoStack = [];
    return true;
  }
  return false;
};
