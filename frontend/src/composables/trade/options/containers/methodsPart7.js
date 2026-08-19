export const createContainersMethodsPart7 = (runtime) => {
  return {
    snapshotInstanceEntry(instanceId) {
      const entry = this.containerState.containerInstanceItems.find(
        (row) => Number(row.instanceId) === Number(instanceId),
      );
      if (!entry) {
        return null;
      }
      return {
        containerId: entry.containerId,
        priceOverride: entry.priceOverride ?? null,
      };
    },
    restoreInstanceEntry(instanceId, snapshot) {
      const list = this.containerState.containerInstanceItems;
      const index = list.findIndex(
        (row) => Number(row.instanceId) === Number(instanceId),
      );
      if (!snapshot) {
        if (index >= 0) {
          list.splice(index, 1);
        }
        return;
      }
      if (index >= 0) {
        list[index].containerId = snapshot.containerId;
        list[index].priceOverride = snapshot.priceOverride ?? null;
        return;
      }
      list.push({
        containerId: snapshot.containerId,
        instanceId,
        priceOverride: snapshot.priceOverride ?? null,
      });
    },
    buildTemplateUndoAction(templateId, fromContainerId, toContainerId) {
      return {
        kind: "template",
        templateId,
        fromContainerId,
        toContainerId,
        fromState: this.snapshotTemplateStack(fromContainerId, templateId),
        toState: this.snapshotTemplateStack(toContainerId, templateId),
      };
    },
    buildInstanceUndoAction(instanceId) {
      return {
        kind: "instance",
        instanceId,
        prevEntry: this.snapshotInstanceEntry(instanceId),
      };
    },
    removeInstances(instanceIds) {
      if (!instanceIds.length) {
        return;
      }
      const idSet = new Set(instanceIds.map((id) => Number(id)));
      const instances = this.containerState.itemInstances;
      for (let i = instances.length - 1; i >= 0; i -= 1) {
        if (idSet.has(Number(instances[i].id))) {
          instances.splice(i, 1);
        }
      }
      const containerItems = this.containerState.containerInstanceItems;
      for (let i = containerItems.length - 1; i >= 0; i -= 1) {
        if (idSet.has(Number(containerItems[i].instanceId))) {
          containerItems.splice(i, 1);
        }
      }
    },
    getTemplateById(templateId) {
      return this.containerState.itemTemplates.find(
        (entry) => Number(entry.id) === Number(templateId),
      );
    },
    getInstanceById(instanceId) {
      return this.containerState.itemInstances.find(
        (entry) => Number(entry.id) === Number(instanceId),
      );
    },
    getTemplateStackEntry(containerId, templateId) {
      return this.containerState.containerTemplateItems.find(
        (row) =>
          Number(row.containerId) === Number(containerId) &&
          Number(row.templateId) === Number(templateId),
      );
    },
    normalizeShopBuyQuantity(item) {
      const requested = runtime.clampQuantityUtil(this.shopBuyQuantity, 1);
      if (item.quantity === null) {
        return requested;
      }
      const available = Number(item.quantity || 0);
      return runtime.clampQuantityUtil(requested, 0, available);
    },
    normalizeGmMoveQuantity(item) {
      const requested = runtime.clampQuantityUtil(this.gmMoveQuantity, 1);
      if (item?.quantity === null) {
        return requested;
      }
      const available = Number(item?.quantity || 0);
      return runtime.clampQuantityUtil(requested, 0, available);
    },
  };
};
