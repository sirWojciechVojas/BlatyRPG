import { resolveItemIconClass } from "@/lib/trade/shopItemIconResolver";

export const installWorkspaceGroup7 = (deps) => {
  function selectedOfferInstanceIds() {
    const selectedGroups = new Set(deps.offerSelection.map(String));
    return [
      ...new Set(
        deps.groupedOffer.value
          .filter((group) => selectedGroups.has(String(group.OFFER_KEY)))
          .flatMap((group) => group.INSTANCE_IDS || [])
          .map(Number)
          .filter(Number.isFinite),
      ),
    ];
  }
  async function moveSelectedOfferItems(targetContainerId) {
    const instanceIds = selectedOfferInstanceIds();
    const instances = deps.allItemInstances.value.filter((item) =>
      instanceIds.includes(Number(item.ID)),
    );
    const moves = instances
      .filter((item) => Number(item.CONTAINER_ID) !== Number(targetContainerId))
      .map((item) => ({
        fromContainerId: Number(item.CONTAINER_ID),
        toContainerId: Number(targetContainerId),
        instanceId: Number(item.ID),
        quantity: 1,
      }));
    if (!Number(targetContainerId) || !moves.length) return false;
    const result = await deps.store.dispatch("shop/moveContainerItems", {
      moves,
      ownerCode: deps.profileDraft.ownerCode,
    });
    if (!result?.ok) return false;
    await deps.store.dispatch("shop/loadTradingData", {
      campaignId: deps.shopState.value.campaignId,
      ownerCode: deps.profileDraft.ownerCode,
      forceReload: true,
    });
    deps.offerSelection.splice(0);
    return true;
  }
  async function quickTransferOffer() {
    if (await moveSelectedOfferItems(deps.offerTargetId.value)) {
      deps.offerTargetId.value = null;
    }
  }
  function toggleAllOfferItems() {
    const visible = new Set(deps.filteredOfferKeys.value);
    if (deps.allFilteredOfferSelected.value) {
      const remaining = deps.offerSelection.filter(
        (key) => !visible.has(String(key)),
      );
      deps.offerSelection.splice(0, deps.offerSelection.length, ...remaining);
      return;
    }
    const selected = new Set(deps.offerSelection.map(String));
    deps.filteredOfferKeys.value.forEach((key) => {
      if (!selected.has(key)) deps.offerSelection.push(key);
    });
  }
  async function moveOfferSelectionToTrash() {
    if (deps.offerSelectionBusy.value || !deps.offerSelection.length) return;
    const containers = deps.shopState.value.containerState?.containers || [];
    const ownerCode = String(deps.profileDraft.ownerCode || "").toUpperCase();
    const target =
      containers.find(
        (entry) =>
          String(entry.container_type || "").toUpperCase() === "TRASH" &&
          String(entry.owner_code || "").toUpperCase() === ownerCode,
      ) ||
      containers.find(
        (entry) => String(entry.system_key || "").toUpperCase() === "TRASH",
      );
    if (!target) return;
    deps.offerSelectionBusy.value = true;
    try {
      await moveSelectedOfferItems(Number(target.id));
    } finally {
      deps.offerSelectionBusy.value = false;
    }
  }
  function handlePricingModifierToggle({ key, enabled }) {
    deps.updatePricingModifier(key, enabled);
  }
  function setCatalogMode(mode) {
    deps.catalogMode.value = mode;
    if (mode === "instances" && deps.templateDraft.ID) {
      hydrateInstanceFromTemplate(deps.templateDraft);
    }
  }
  function selectCatalogTemplate(item) {
    if (deps.catalogMode.value === "instances") {
      hydrateInstanceFromTemplate(item);
      return;
    }
    editTemplate(item);
  }
  function hydrateInstanceFromTemplate(item) {
    deps.hydratingInstance = true;
    Object.assign(deps.instanceDraft, deps.emptyInstance(), {
      templateId: Number(item.ID),
      containerId: deps.defaultStackContainerId.value,
      name: item.NAME || "",
      description: item.DESCRIPTION || "",
      details: item.DETAILS || "",
      itemClass: item.ITEM_CLASS || "TOOL",
      itemGenre: item.ITEM_GENRE || "UTILITY",
      imgClass: item.IMG_CLASS || "v0001",
      price: Number(item.PRIZE || 0),
      currencyCode: deps.displayCurrencyCode(item.CURRENCY),
      charge: Number(item.CHARGE || 0),
      attributes: [...(item.ATTRIBUTES || [])],
    });
    deps.hydratingInstance = false;
    deps.store.commit("shop/setFormStatus", {
      scope: "instance",
      status: "clean",
    });
  }
  function openIconPicker(target) {
    deps.iconPickerTarget.value = target;
    deps.iconPickerOpen.value = true;
  }
  function setSelectedIcon(code) {
    if (deps.iconPickerTarget.value === "stackInstance") {
      deps.stackInstanceDraft.imgClass = code;
    } else if (deps.iconPickerTarget.value === "instance") {
      deps.instanceDraft.imgClass = code;
    } else {
      deps.templateDraft.IMG_CLASS = code;
      deps.templateIconManuallySelected.value = true;
      deps.markTemplateDirty();
    }
  }
  function refreshTemplateIcon() {
    if (!deps.templateIconManuallySelected.value) {
      deps.templateDraft.IMG_CLASS = resolveItemIconClass(deps.templateDraft);
    }
    deps.markTemplateDirty();
  }
  function addAttribute(draft, property, code) {
    const normalized = String(code || "")
      .trim()
      .toUpperCase();
    if (!normalized) return;
    if (!Array.isArray(draft[property])) draft[property] = [];
    if (!draft[property].includes(normalized)) draft[property].push(normalized);
  }
  function addTemplateAttribute() {
    addAttribute(
      deps.templateDraft,
      "ATTRIBUTES",
      deps.templateAttributeToAdd.value,
    );
    deps.templateAttributeToAdd.value = "";
    deps.markTemplateDirty();
  }
  function addInstanceAttribute() {
    addAttribute(
      deps.instanceDraft,
      "attributes",
      deps.instanceAttributeToAdd.value,
    );
    deps.instanceAttributeToAdd.value = "";
  }
  function removeAttribute(draft, code, afterRemove = null) {
    const property = Array.isArray(draft.ATTRIBUTES)
      ? "ATTRIBUTES"
      : "attributes";
    draft[property] = (draft[property] || []).filter((value) => value !== code);
    if (typeof afterRemove === "function") afterRemove();
  }
  function toggleStackAttribute(code, checked) {
    const normalized = String(code || "").toUpperCase();
    const selected = new Set(deps.stackInstanceDraft.attributes || []);
    if (checked) selected.add(normalized);
    else selected.delete(normalized);
    deps.stackInstanceDraft.attributes = [...selected];
  }
  async function createInstance() {
    if (!deps.canCreateInstance.value) return;
    const created = await deps.store.dispatch("shop/createItemInstanceRecord", {
      ...JSON.parse(JSON.stringify(deps.instanceDraft)),
      ownerCode: deps.profileDraft.ownerCode || "BG1",
    });
    if (created) {
      deps.store.commit("shop/setFormStatus", {
        scope: "instance",
        status: "clean",
      });
    }
  }
  function newTemplate() {
    deps.templateIconManuallySelected.value = false;
    Object.assign(deps.templateDraft, deps.emptyTemplate(), {
      CURRENCY: deps.defaultCurrencyCode.value,
    });
    deps.store.commit("shop/setFormStatus", {
      scope: "template",
      status: "dirty",
    });
  }
  function editTemplate(item) {
    deps.templateIconManuallySelected.value = /^v\d{4}$/u.test(
      String(item.IMG_CLASS || "").toLowerCase(),
    );
    Object.assign(
      deps.templateDraft,
      deps.emptyTemplate(),
      JSON.parse(JSON.stringify(item)),
      {
        CURRENCY: deps.displayCurrencyCode(item.CURRENCY),
        WEAPON: {
          ...deps.emptyTemplate().WEAPON,
          ...(item.WEAPON || {}),
        },
      },
    );
    deps.store.commit("shop/setFormStatus", {
      scope: "template",
      status: "clean",
    });
  }
  async function saveTemplate() {
    deps.store.commit("shop/setFormStatus", {
      scope: "template",
      status: "saving",
    });
    const action = deps.templateDraft.ID
      ? "shop/saveTemplateRecord"
      : "shop/createTemplateRecord";
    const saved = await deps.store.dispatch(
      action,
      JSON.parse(JSON.stringify(deps.templateDraft)),
    );
    deps.store.commit("shop/setFormStatus", {
      scope: "template",
      status: saved ? "clean" : "error",
    });
    if (saved) editTemplate(saved);
    return saved || null;
  }
  Object.assign(deps, {
    quickTransferOffer,
    selectedOfferInstanceIds,
    moveSelectedOfferItems,
    toggleAllOfferItems,
    moveOfferSelectionToTrash,
    handlePricingModifierToggle,
    setCatalogMode,
    selectCatalogTemplate,
    hydrateInstanceFromTemplate,
    openIconPicker,
    setSelectedIcon,
    refreshTemplateIcon,
    addAttribute,
    addTemplateAttribute,
    addInstanceAttribute,
    removeAttribute,
    toggleStackAttribute,
    createInstance,
    newTemplate,
    editTemplate,
    saveTemplate,
  });
};
