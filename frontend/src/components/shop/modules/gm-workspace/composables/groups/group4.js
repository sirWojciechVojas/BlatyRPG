import { computed } from "vue";
export const installWorkspaceGroup4 = (deps) => {
  const warehouseContainerOptions = computed(() =>
    (deps.shopState.value.containerState?.containers || [])
      .filter((entry) => entry.is_active !== 0 && entry.is_active !== false)
      .map((entry) => ({
        id: Number(entry.id),
        value: Number(entry.id),
        code: Number(entry.id),
        name:
          entry.name ||
          entry.owner_code ||
          entry.system_key ||
          (entry.container_type || "CONTAINER") + " #" + entry.id,
        labelPl:
          entry.name ||
          entry.owner_code ||
          entry.system_key ||
          (entry.container_type || "CONTAINER") + " #" + entry.id,
        labelEn:
          entry.name ||
          entry.owner_code ||
          entry.system_key ||
          (entry.container_type || "CONTAINER") + " #" + entry.id,
      })),
  );
  const transferTargetOptions = computed(() =>
    warehouseContainerOptions.value.filter(
      (entry) => Number(entry.value) !== Number(deps.transferSourceId.value),
    ),
  );
  const transferSourceItems = computed(() =>
    deps.instanceIndexes.forContainer(deps.transferSourceId.value),
  );
  const filteredTransferSourceItems = computed(() => {
    const needle = String(
      deps.deferredTransferQuery.value || "",
    ).toLocaleLowerCase(deps.locale.value);
    return transferSourceItems.value.filter(
      (item) =>
        !needle ||
        `${deps.itemDisplayName(item)} ${item.DESCRIPTION || ""} ${item.ITEM_CLASS || ""} ${item.ITEM_GENRE || ""}`
          .toLocaleLowerCase(deps.locale.value)
          .includes(needle),
    );
  });
  const transferTargetItems = computed(() =>
    deps.instanceIndexes.forContainer(deps.transferTargetId.value),
  );
  const transferPreviewItems = computed(() => {
    const selected = new Set(deps.transferSelection.map(Number));
    return transferSourceItems.value.filter((item) =>
      selected.has(Number(item.ID)),
    );
  });
  const containerName = (containerId, fallbackKey) =>
    warehouseContainerOptions.value.find(
      (entry) => Number(entry.value) === Number(containerId),
    )?.name || deps.t(fallbackKey);
  const transferSourceName = computed(() =>
    containerName(
      deps.transferSourceId.value,
      "shop.workspace.transfer.selectSource",
    ),
  );
  const transferTargetName = computed(() =>
    containerName(
      deps.transferTargetId.value,
      "shop.workspace.transfer.selectTarget",
    ),
  );
  const canApplyTransfer = computed(
    () =>
      !deps.transferSaving.value &&
      Number(deps.transferSourceId.value) > 0 &&
      Number(deps.transferTargetId.value) > 0 &&
      Number(deps.transferSourceId.value) !==
        Number(deps.transferTargetId.value) &&
      transferPreviewItems.value.length > 0,
  );
  const defaultStackContainerId = computed(() => {
    const container = (
      deps.shopState.value.containerState?.containers || []
    ).find(
      (entry) => String(entry.system_key || "").toUpperCase() === "DEFAULT",
    );
    return container ? Number(container.id) : null;
  });
  function localizedDomainLabel(entry) {
    return String(deps.locale.value).startsWith("pl")
      ? entry?.labelPl || entry?.labelEn || entry?.code || ""
      : entry?.labelEn || entry?.labelPl || entry?.code || "";
  }
  function localizedRecordLabel(entry, fallback = "") {
    return String(deps.locale.value).startsWith("pl")
      ? entry?.labelPl ||
          entry?.namePl ||
          entry?.labelEn ||
          entry?.nameEn ||
          fallback
      : entry?.labelEn ||
          entry?.nameEn ||
          entry?.labelPl ||
          entry?.namePl ||
          fallback;
  }
  function domainOptionLabel(entry) {
    const label = localizedDomainLabel(entry);
    return label && label !== entry?.code
      ? `${label} (${entry.code})`
      : String(entry?.code || "");
  }
  function domainLabel(group, code) {
    const entry = (deps.itemDictionaries.value?.[group] || []).find(
      (candidate) => candidate.code === code,
    );
    return entry ? localizedDomainLabel(entry) : String(code || "—");
  }
  function instanceFilterCount(filterId) {
    if (filterId === "all") return deps.allItemInstances.value.length;
    const kind = {
      unassigned: "UNASSIGNED",
      character: "CHARACTER",
      shop: "SHOP",
      trash: "TRASH",
    }[filterId];
    return deps.allItemInstances.value.filter(
      (item) => item.LOCATION_KIND === kind,
    ).length;
  }
  function instanceOwnerLabel(item) {
    if (item.LOCATION_KIND === "CHARACTER") {
      return [item.LOCATION_OWNER_NAME, item.LOCATION_OWNER_CODE]
        .filter(Boolean)
        .join(" · ");
    }
    if (item.LOCATION_KIND === "SHOP") {
      return (
        item.LOCATION_SHOP_NAME ||
        deps.t("shop.workspace.instanceStack.noOwner")
      );
    }
    return deps.t("shop.workspace.instanceStack.noOwner");
  }
  Object.assign(deps, {
    warehouseContainerOptions,
    transferTargetOptions,
    transferSourceItems,
    filteredTransferSourceItems,
    transferTargetItems,
    transferPreviewItems,
    containerName,
    transferSourceName,
    transferTargetName,
    canApplyTransfer,
    defaultStackContainerId,
    localizedDomainLabel,
    localizedRecordLabel,
    domainOptionLabel,
    domainLabel,
    instanceFilterCount,
    instanceOwnerLabel,
  });
};
