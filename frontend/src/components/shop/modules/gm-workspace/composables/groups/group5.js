export const installWorkspaceGroup5 = (deps) => {
  function instanceKindLabel(item) {
    const kind = String(item.LOCATION_KIND || "SYSTEM").toLowerCase();
    return deps.t(`shop.workspace.instanceStack.kinds.${kind}`);
  }
  function editStackInstance(item) {
    if (!item?.ID) return;
    if (
      deps.stackInstanceDraft.id &&
      Number(deps.stackInstanceDraft.id) !== Number(item.ID) &&
      deps.formStatus.value.stackInstance === "dirty" &&
      !window.confirm(deps.t("shop.workspace.unsavedQuestion"))
    ) {
      return;
    }
    deps.hydratingStackInstance = true;
    Object.assign(deps.stackInstanceDraft, deps.emptyStackInstance(), {
      id: Number(item.ID),
      templateId: Number(item.INV_ID),
      containerId: Number(item.CONTAINER_ID),
      originalContainerId: Number(item.CONTAINER_ID),
      name: item.PERSONAL_PSEU || item.NAME || "",
      description: item.PERSONAL_DESC || item.DESCRIPTION || "",
      details: item.DETAILS || "",
      itemClass: item.ITEM_CLASS || "TOOL",
      itemGenre: item.ITEM_GENRE || "UTILITY",
      imgClass: item.IMG_CLASS || "v0001",
      price: Number(item.PERSONAL_COST ?? item.ACTIVE_PRICE ?? item.PRIZE ?? 0),
      currencyCode: item.CURRENCY || deps.defaultCurrencyCode.value,
      charge: Number(item.CHARGE || 0),
      attributes: [...(item.ATTRIBUTES || [])],
      weapon: {
        ...deps.emptyStackInstance().weapon,
        ...(item.WEAPON || {}),
      },
    });
    deps.hydratingStackInstance = false;
    deps.store.commit("shop/setFormStatus", {
      scope: "stackInstance",
      status: "clean",
    });
  }
  function editSelectedStackInstance() {
    if (deps.warehouseSelection.length !== 1) return;
    const item = deps.allItemInstances.value.find(
      (entry) => Number(entry.ID) === Number(deps.warehouseSelection[0]),
    );
    if (item) editStackInstance(item);
  }
  function closeStackInstanceEditor() {
    if (
      deps.formStatus.value.stackInstance === "dirty" &&
      !window.confirm(deps.t("shop.workspace.unsavedQuestion"))
    ) {
      return;
    }
    deps.hydratingStackInstance = true;
    Object.assign(deps.stackInstanceDraft, deps.emptyStackInstance());
    deps.hydratingStackInstance = false;
    deps.store.commit("shop/setFormStatus", {
      scope: "stackInstance",
      status: "clean",
    });
  }
  async function saveStackInstance() {
    if (
      !deps.stackInstanceDraft.id ||
      !String(deps.stackInstanceDraft.name || "").trim()
    )
      return;
    const instanceId = Number(deps.stackInstanceDraft.id);
    const previousContainerId = Number(
      deps.stackInstanceDraft.originalContainerId,
    );
    const targetContainerId = Number(deps.stackInstanceDraft.containerId);
    const saved = await deps.store.dispatch("shop/saveItemInstance", {
      id: instanceId,
      formScope: "stackInstance",
      name: deps.stackInstanceDraft.name,
      description: deps.stackInstanceDraft.description,
      details: deps.stackInstanceDraft.details,
      itemClass: deps.stackInstanceDraft.itemClass,
      itemGenre: deps.stackInstanceDraft.itemGenre,
      imgClass: deps.stackInstanceDraft.imgClass,
      price: Number(deps.stackInstanceDraft.price || 0),
      currencyCode: deps.stackInstanceDraft.currencyCode,
      charge: Number(deps.stackInstanceDraft.charge || 0),
      attributes: [...deps.stackInstanceDraft.attributes],
      weapon: {
        ...deps.stackInstanceDraft.weapon,
      },
      ownerCode: deps.profileDraft.ownerCode || "BG1",
    });
    if (!saved) return;
    if (
      targetContainerId > 0 &&
      previousContainerId > 0 &&
      targetContainerId !== previousContainerId
    ) {
      const moveResult = await deps.store.dispatch("shop/moveContainerItems", {
        ownerCode: deps.profileDraft.ownerCode || "BG1",
        moves: [
          {
            fromContainerId: previousContainerId,
            toContainerId: targetContainerId,
            instanceId,
            quantity: 1,
          },
        ],
      });
      if (!moveResult?.ok) {
        deps.store.commit("shop/setFormStatus", {
          scope: "stackInstance",
          status: "error",
        });
        return;
      }
      deps.instanceLocationFilter.value = "all";
    }
    await deps.store.dispatch("shop/loadTradingData", {
      campaignId: deps.shopState.value.campaignId,
      ownerCode: deps.profileDraft.ownerCode || "BG1",
      forceReload: true,
    });
    const refreshed = deps.allItemInstances.value.find(
      (item) => Number(item.ID) === instanceId,
    );
    deps.warehouseSelection.splice(
      0,
      deps.warehouseSelection.length,
      instanceId,
    );
    if (refreshed) editStackInstance(refreshed);
  }
  function dictionaryDraft(group, entry) {
    const key = `${group}:${entry.id || entry.code}`;
    if (!deps.dictionaryDraftState[key]) {
      deps.dictionaryDraftState[key] = {
        code: entry.code,
        codeUnlocked: false,
        labelPl: entry.labelPl || entry.code,
        labelEn: entry.labelEn || entry.code,
        appliesToText: (entry.appliesTo || []).join(", "),
        mechanics: JSON.parse(JSON.stringify(entry.mechanics || [])),
      };
    }
    return deps.dictionaryDraftState[key];
  }
  async function saveDictionaryEntry(group, entry, values = null) {
    const draft = dictionaryDraft(group, entry);
    const saved = await deps.store.dispatch("shop/saveItemDictionaryEntry", {
      id: entry.id,
      group,
      code: values?.code || draft.code || entry.code,
      labelPl: values?.labelPl ?? draft.labelPl,
      labelEn: values?.labelEn ?? draft.labelEn,
      appliesTo: Array.isArray(values?.appliesTo)
        ? values.appliesTo
        : draft.appliesToText
            .split(",")
            .map((value) => value.trim().toUpperCase())
            .filter(Boolean),
      mechanics: Array.isArray(values?.mechanics)
        ? values.mechanics
        : draft.mechanics,
      sortOrder: entry.sortOrder || 0,
    });
    if (saved) {
      delete deps.dictionaryDraftState[`${group}:${entry.id || entry.code}`];
    }
    return saved;
  }
  async function archiveDictionaryEntry(entry) {
    if (!window.confirm(deps.t("shop.workspace.dictionaries.archiveQuestion")))
      return;
    await deps.store.dispatch("shop/archiveItemDictionaryEntry", entry.id);
  }
  async function addDictionaryEntry(group, values = null) {
    if (values) {
      return deps.store.dispatch("shop/saveItemDictionaryEntry", {
        group,
        code: values.code,
        labelPl: values.labelPl,
        labelEn: values.labelEn,
        appliesTo: Array.isArray(values.appliesTo) ? values.appliesTo : [],
        mechanics: Array.isArray(values.mechanics) ? values.mechanics : [],
      });
    }
    const code = window.prompt(
      deps.t("shop.workspace.dictionaries.newCode"),
      "NEW_CODE",
    );
    if (!code) return;
    const labelPl = window.prompt(
      deps.t("shop.workspace.dictionaries.newLabelPl"),
      code,
    );
    if (!labelPl) return;
    const labelEn = window.prompt(
      deps.t("shop.workspace.dictionaries.newLabelEn"),
      code,
    );
    if (!labelEn) return;
    return deps.store.dispatch("shop/saveItemDictionaryEntry", {
      group,
      code,
      labelPl,
      labelEn,
      appliesTo: [],
      mechanics: [],
    });
  }
  Object.assign(deps, {
    instanceKindLabel,
    editStackInstance,
    editSelectedStackInstance,
    closeStackInstanceEditor,
    saveStackInstance,
    dictionaryDraft,
    saveDictionaryEntry,
    archiveDictionaryEntry,
    addDictionaryEntry,
  });
};
