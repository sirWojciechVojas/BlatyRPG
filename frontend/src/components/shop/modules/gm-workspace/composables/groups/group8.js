export const installWorkspaceGroup8 = (deps) => {
  async function personalizeTemplate() {
    let template = deps.templateDraft;
    if (!deps.templateDraft.ID || deps.formStatus.value.template !== "clean") {
      const saved = await deps.saveTemplate();
      if (!saved) return;
      template = saved;
    }
    const created = await deps.store.dispatch("shop/createItemInstanceRecord", {
      templateId: Number(template.ID),
      containerId: deps.defaultStackContainerId.value,
      name: template.NAME,
      description: template.DESCRIPTION,
      details: template.DETAILS,
      itemClass: template.ITEM_CLASS,
      itemGenre: template.ITEM_GENRE,
      imgClass: template.IMG_CLASS,
      price: Number(template.PRIZE || 0),
      currencyCode: deps.displayCurrencyCode(template.CURRENCY),
      charge: Number(template.CHARGE || 0),
      attributes: [...(template.ATTRIBUTES || [])],
      weapon: {
        ...(template.WEAPON || {}),
      },
      ownerCode: deps.profileDraft.ownerCode || "BG1",
    });
    if (!created) return;
    deps.activeTab.value = "warehouse";
    deps.activeModule.value = "inventory";
    deps.warehouseTab.value = "items";
    deps.instanceLocationFilter.value = "unassigned";
    deps.warehouseQuery.value = "";
    const createdId = Number(created.ID ?? created.id);
    const instance = deps.allItemInstances.value.find(
      (item) => Number(item.ID) === createdId,
    );
    if (instance) {
      deps.warehouseSelection.splice(
        0,
        deps.warehouseSelection.length,
        createdId,
      );
      deps.editStackInstance(instance);
    }
  }
  async function duplicateTemplate() {
    const created = await deps.store.dispatch("shop/duplicateTemplateRecord", {
      templateId: deps.templateDraft.ID,
    });
    if (created) deps.editTemplate(created);
  }
  async function archiveTemplate() {
    if (!window.confirm(deps.t("shop.workspace.archiveQuestion"))) return;
    if (
      await deps.store.dispatch(
        "shop/deleteTemplateRecord",
        deps.templateDraft.ID,
      )
    )
      deps.newTemplate();
  }
  async function openArchive() {
    deps.activeTab.value = "warehouse";
    deps.activeModule.value = "trash";
    deps.warehouseTab.value = "archive";
    await deps.store.dispatch("shop/loadArchivedTemplates");
  }
  async function restoreTemplate(item) {
    await deps.store.dispatch("shop/restoreArchivedTemplate", item.ID);
  }
  async function archiveWarehouseSelection() {
    const state = deps.shopState.value.containerState || {};
    const containers = state.containers || [];
    const target =
      containers.find(
        (entry) =>
          entry.container_type === "TRASH" &&
          String(entry.owner_code || "").toUpperCase() ===
            String(deps.profileDraft.ownerCode || "").toUpperCase(),
      ) || containers.find((entry) => entry.system_key === "TRASH");
    if (target) {
      await moveWarehouseSelection(Number(target.id));
    }
  }
  function selectedWarehousePlacements() {
    const selectedItems = deps.allItemInstances.value.filter((item) =>
      deps.warehouseSelection.map(Number).includes(Number(item.ID)),
    );
    return selectedItems.map((item) => ({
      fromContainerId: Number(item.CONTAINER_ID),
      instanceId: Number(item.ID),
      quantity: 1,
    }));
  }
  async function moveWarehouseSelection(targetContainerId) {
    const moves = selectedWarehousePlacements()
      .filter(
        (placement) =>
          Number(placement.fromContainerId) !== Number(targetContainerId),
      )
      .map((placement) => ({
        ...placement,
        toContainerId: Number(targetContainerId),
        ownerCode: deps.profileDraft.ownerCode,
      }));
    if (!moves.length) return;
    const result = await deps.store.dispatch("shop/moveContainerItems", {
      moves,
      ownerCode: deps.profileDraft.ownerCode,
    });
    if (result?.ok) {
      await deps.store.dispatch("shop/loadTradingData", {
        campaignId: deps.shopState.value.campaignId,
        ownerCode: deps.profileDraft.ownerCode,
        forceReload: true,
      });
      deps.warehouseSelection.splice(0);
      deps.warehouseTargetId.value = null;
    }
  }
  function clearTransferSelection() {
    deps.transferSelection.splice(0);
  }
  function swapTransferContainers() {
    const source = deps.transferSourceId.value;
    deps.transferSourceId.value = deps.transferTargetId.value;
    deps.transferTargetId.value = source;
    clearTransferSelection();
  }
  async function applyTransferPreview() {
    if (!deps.canApplyTransfer.value) return;
    deps.transferSaving.value = true;
    try {
      const moves = deps.transferPreviewItems.value.map((item) => ({
        fromContainerId: Number(deps.transferSourceId.value),
        toContainerId: Number(deps.transferTargetId.value),
        instanceId: Number(item.ID),
        quantity: 1,
        ownerCode: deps.profileDraft.ownerCode,
      }));
      const result = await deps.store.dispatch("shop/moveContainerItems", {
        moves,
        ownerCode: deps.profileDraft.ownerCode,
      });
      if (!result?.ok) return;
      await deps.store.dispatch("shop/loadTradingData", {
        campaignId: deps.shopState.value.campaignId,
        ownerCode: deps.profileDraft.ownerCode,
        forceReload: true,
      });
      clearTransferSelection();
    } finally {
      deps.transferSaving.value = false;
    }
  }
  function requestDuplicate(mode) {
    deps.duplicateMode.value = mode;
  }
  async function duplicateActiveShop() {
    const mode = deps.duplicateMode.value;
    deps.duplicateMode.value = "";
    await deps.store.dispatch("shop/duplicateShop", {
      shopId: deps.activeShopId.value,
      copyMode: mode,
      ownerCode: deps.profileDraft.ownerCode,
    });
  }
  async function deleteActiveShop() {
    deps.confirmDeleteShop.value = false;
    await deps.store.dispatch("shop/deleteShop", {
      shopId: deps.activeShopId.value,
    });
  }
  function download(name, type, content) {
    const url = URL.createObjectURL(
      new Blob([content], {
        type,
      }),
    );
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = name;
    anchor.click();
    URL.revokeObjectURL(url);
  }
  Object.assign(deps, {
    personalizeTemplate,
    duplicateTemplate,
    archiveTemplate,
    openArchive,
    restoreTemplate,
    archiveWarehouseSelection,
    selectedWarehousePlacements,
    moveWarehouseSelection,
    clearTransferSelection,
    swapTransferContainers,
    applyTransferPreview,
    requestDuplicate,
    duplicateActiveShop,
    deleteActiveShop,
    download,
  });
};
