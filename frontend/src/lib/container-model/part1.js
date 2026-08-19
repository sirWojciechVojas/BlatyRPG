export const createRuntimePart1 = (runtime) => {
  const nowIso = () => new Date().toISOString();
  Object.assign(runtime, {
    nowIso,
  });
  const PLAYER_TRASH_SLOT_CAPACITY = 16;
  Object.assign(runtime, {
    PLAYER_TRASH_SLOT_CAPACITY,
  });
  const GENERAL_TRASH_SLOT_CAPACITY = null;
  Object.assign(runtime, {
    GENERAL_TRASH_SLOT_CAPACITY,
  });
  const nextId = (items) =>
    items.length
      ? Math.max(...items.map((item) => Number(item.id || item.ID))) + 1
      : 1;
  Object.assign(runtime, {
    nextId,
  });
  const findContainer = (state, id) =>
    state.containers.find((container) => Number(container.id) === Number(id));
  Object.assign(runtime, {
    findContainer,
  });
  const getSystemContainerId = (state, systemKey) =>
    state.containers.find(
      (container) =>
        container.type === "SYSTEM" && container.systemKey === systemKey,
    )?.id ?? null;
  Object.assign(runtime, {
    getSystemContainerId,
  });
  const addMovement = (state, entry) => {
    state.itemMovements.push({
      ...entry,
      movedAt: runtime.nowIso(),
    });
  };
  Object.assign(runtime, {
    addMovement,
  });
  const normalizeAssortmentContainers = (state, containerId) => {
    const container = runtime.findContainer(state, containerId);
    if (!container) {
      return null;
    }
    return container.id;
  };
  Object.assign(runtime, {
    normalizeAssortmentContainers,
  });
  const ensureTemplateStack = (
    state,
    containerId,
    templateId,
    quantity,
    priceOverride,
  ) => {
    const existing = state.containerTemplateItems.find(
      (row) =>
        Number(row.containerId) === Number(containerId) &&
        Number(row.templateId) === Number(templateId),
    );
    if (existing) {
      if (existing.quantity === null) {
        existing.quantity = null;
      } else if (quantity === null) {
        existing.quantity = null;
      } else {
        const current = existing.quantity ?? 0;
        existing.quantity = current + quantity;
      }
      if (priceOverride !== undefined) {
        existing.priceOverride = priceOverride;
      }
      return existing;
    }
    const entry = {
      containerId,
      templateId,
      quantity: quantity ?? 0,
      priceOverride: priceOverride ?? null,
    };
    state.containerTemplateItems.push(entry);
    return entry;
  };
  Object.assign(runtime, {
    ensureTemplateStack,
  });
  const removeTemplateStack = (state, containerId, templateId, quantity) => {
    const entryIndex = state.containerTemplateItems.findIndex(
      (row) =>
        Number(row.containerId) === Number(containerId) &&
        Number(row.templateId) === Number(templateId),
    );
    if (entryIndex < 0) {
      return null;
    }
    const entry = state.containerTemplateItems[entryIndex];
    if (entry.quantity === null) {
      return {
        ...entry,
      };
    }
    const nextQty = Math.max(0, (entry.quantity || 0) - (quantity || 0));
    entry.quantity = nextQty;
    if (!nextQty) {
      state.containerTemplateItems.splice(entryIndex, 1);
    }
    return {
      ...entry,
      quantity: quantity ?? 0,
    };
  };
  Object.assign(runtime, {
    removeTemplateStack,
  });
  const moveTemplateStack = (
    state,
    templateId,
    fromContainerId,
    toContainerId,
    quantity,
    reason,
  ) => {
    const removed = runtime.removeTemplateStack(
      state,
      fromContainerId,
      templateId,
      quantity,
    );
    if (!removed) {
      return;
    }
    const qtyToMove =
      removed.quantity === null
        ? null
        : Math.max(0, quantity ?? removed.quantity ?? 0);
    runtime.ensureTemplateStack(
      state,
      toContainerId,
      templateId,
      qtyToMove,
      removed.priceOverride,
    );
    runtime.addMovement(state, {
      fromContainerId,
      toContainerId,
      templateId,
      quantity: qtyToMove,
      reason,
    });
  };
  Object.assign(runtime, {
    moveTemplateStack,
  });
  const moveInstance = (state, instanceId, toContainerId, reason) => {
    const entry = state.containerInstanceItems.find(
      (row) => Number(row.instanceId) === Number(instanceId),
    );
    const fromContainerId = entry ? entry.containerId : null;
    if (entry) {
      entry.containerId = toContainerId;
    } else {
      state.containerInstanceItems.push({
        containerId: toContainerId,
        instanceId,
        priceOverride: null,
      });
    }
    runtime.addMovement(state, {
      fromContainerId,
      toContainerId,
      instanceId,
      reason,
    });
  };
  Object.assign(runtime, {
    moveInstance,
  });
  const assignTemplateToShop = (
    state,
    templateId,
    shopContainerId,
    quantityOrNull,
    priceOverride,
  ) => {
    runtime.normalizeAssortmentContainers(state, shopContainerId);
    runtime.ensureTemplateStack(
      state,
      shopContainerId,
      templateId,
      quantityOrNull,
      priceOverride,
    );
    runtime.addMovement(state, {
      fromContainerId: null,
      toContainerId: shopContainerId,
      templateId,
      quantity: quantityOrNull ?? null,
      reason: "assignTemplateToShop",
    });
  };
  Object.assign(runtime, {
    assignTemplateToShop,
  });
  const buyFromShop = (state, playerContainerId, shopContainerId, payload) => {
    if (payload.instanceId) {
      runtime.moveInstance(
        state,
        payload.instanceId,
        playerContainerId,
        "buyFromShop",
      );
      return;
    }
    if (!payload.templateId) {
      return;
    }
    const template = state.itemTemplates.find(
      (item) => Number(item.id) === Number(payload.templateId),
    );
    const shopEntry = state.containerTemplateItems.find(
      (row) =>
        Number(row.containerId) === Number(shopContainerId) &&
        Number(row.templateId) === Number(payload.templateId),
    );
    const quantity = Math.max(1, Number(payload.quantity || 1));
    if (!shopEntry) {
      return;
    }
    const isInfinite = shopEntry.quantity === null;
    const actualQty = isInfinite
      ? quantity
      : Math.min(shopEntry.quantity || 0, quantity);
    if (!actualQty) {
      return;
    }
    if (template?.isStackable) {
      runtime.ensureTemplateStack(
        state,
        playerContainerId,
        template.id,
        actualQty,
        null,
      );
      if (!isInfinite) {
        runtime.removeTemplateStack(
          state,
          shopContainerId,
          template.id,
          actualQty,
        );
      }
      runtime.addMovement(state, {
        fromContainerId: shopContainerId,
        toContainerId: playerContainerId,
        templateId: template.id,
        quantity: actualQty,
        reason: "buyFromShop",
      });
      return;
    }
    for (let i = 0; i < actualQty; i += 1) {
      const instanceId = runtime.nextId(state.itemInstances);
      state.itemInstances.push({
        id: instanceId,
        templateId: template.id,
        nameOverride: null,
        dataOverride: null,
        note: "Kupione ze sklepu",
      });
      state.containerInstanceItems.push({
        containerId: playerContainerId,
        instanceId,
        priceOverride: null,
      });
      runtime.addMovement(state, {
        fromContainerId: shopContainerId,
        toContainerId: playerContainerId,
        instanceId,
        templateId: template.id,
        quantity: 1,
        reason: "buyFromShop",
      });
    }
    if (!isInfinite) {
      runtime.removeTemplateStack(
        state,
        shopContainerId,
        template.id,
        actualQty,
      );
    }
  };
  Object.assign(runtime, {
    buyFromShop,
  });
  const trashItem = (state, payload, fromContainerId) => {
    const trashContainerId = runtime.getSystemContainerId(state, "TRASH");
    if (!trashContainerId) {
      return;
    }
    if (payload.instanceId) {
      runtime.moveInstance(
        state,
        payload.instanceId,
        trashContainerId,
        "trashItem",
      );
      return;
    }
    if (!payload.templateId) {
      return;
    }
    const quantity = Math.max(1, Number(payload.quantity || 1));
    runtime.moveTemplateStack(
      state,
      payload.templateId,
      fromContainerId,
      trashContainerId,
      quantity,
      "trashItem",
    );
  };
  Object.assign(runtime, {
    trashItem,
  });
  return {
    nowIso,
    PLAYER_TRASH_SLOT_CAPACITY,
    GENERAL_TRASH_SLOT_CAPACITY,
    nextId,
    findContainer,
    getSystemContainerId,
    addMovement,
    normalizeAssortmentContainers,
    ensureTemplateStack,
    removeTemplateStack,
    moveTemplateStack,
    moveInstance,
    assignTemplateToShop,
    buyFromShop,
    trashItem,
  };
};
