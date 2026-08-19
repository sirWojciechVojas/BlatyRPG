import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

const TRASH_OWNER_GENERAL = "TRASH";
const bgCodePattern = /^BG(\d+)$/;

export const useTrashBinView = () => {
  const ctx = useTradeModalContext();
  const trashZoneHubRef = ref(null);
  const trashZoneSearchInputRef = ref(null);
  const trashZoneSearch = ref("");
  const isZonePanelOpen = ref(false);

  const compareOwnerCodes = (leftCode, rightCode) => {
    const left = String(leftCode || "")
      .trim()
      .toUpperCase();
    const right = String(rightCode || "")
      .trim()
      .toUpperCase();
    const leftMatch = left.match(bgCodePattern);
    const rightMatch = right.match(bgCodePattern);
    if (leftMatch && rightMatch) {
      return Number(leftMatch[1]) - Number(rightMatch[1]);
    }
    if (leftMatch) {
      return -1;
    }
    if (rightMatch) {
      return 1;
    }
    return left.localeCompare(right, "pl");
  };

  const sortedTrashZoneOptions = computed(() => {
    const options = Array.isArray(ctx.trashZoneOptions)
      ? ctx.trashZoneOptions
      : [];
    const generalZone = options.find(
      (zone) => String(zone?.value || "") === TRASH_OWNER_GENERAL,
    );
    const ownerZones = options
      .filter((zone) => String(zone?.value || "") !== TRASH_OWNER_GENERAL)
      .sort((left, right) => compareOwnerCodes(left?.value, right?.value));
    return generalZone ? [generalZone, ...ownerZones] : ownerZones;
  });

  const activeTrashZoneValue = computed(() =>
    String(ctx.trashZoneOwnerCodeModel || TRASH_OWNER_GENERAL),
  );

  const activeTrashZoneOption = computed(
    () =>
      sortedTrashZoneOptions.value.find(
        (zone) => String(zone?.value || "") === activeTrashZoneValue.value,
      ) ||
      sortedTrashZoneOptions.value[0] ||
      null,
  );

  const activeTrashZoneOrdinal = computed(() => {
    const total = sortedTrashZoneOptions.value.length;
    if (!total) {
      return "0/0";
    }
    const index = sortedTrashZoneOptions.value.findIndex(
      (zone) => String(zone?.value || "") === activeTrashZoneValue.value,
    );
    return `${index >= 0 ? index + 1 : 1}/${total}`;
  });

  const capacityLabelForZone = (zone) => {
    const rawCapacity = zone?.capacity;
    const capacity = Number(rawCapacity);
    if (
      rawCapacity === null ||
      rawCapacity === undefined ||
      rawCapacity === ""
    ) {
      return "∞";
    }
    return Number.isFinite(capacity) ? capacity : "∞";
  };

  const activeTrashZoneCapacityLabel = computed(() =>
    capacityLabelForZone(activeTrashZoneOption.value),
  );

  const filteredTrashZoneOptions = computed(() => {
    const phrase = String(trashZoneSearch.value || "")
      .trim()
      .toLowerCase();
    if (!phrase) {
      return sortedTrashZoneOptions.value;
    }
    return sortedTrashZoneOptions.value.filter((zone) => {
      const label = String(zone?.label || "").toLowerCase();
      const code = String(zone?.value || "").toLowerCase();
      return label.includes(phrase) || code.includes(phrase);
    });
  });

  const hotTrashZoneOptions = computed(() => {
    const activeCode = activeTrashZoneValue.value;
    const nonEmpty = sortedTrashZoneOptions.value.filter(
      (zone) => Number(zone?.count || 0) > 0,
    );
    const source = nonEmpty.length ? nonEmpty : sortedTrashZoneOptions.value;
    const top = source.slice(0, 5);
    const activeZone = sortedTrashZoneOptions.value.find(
      (zone) => String(zone?.value || "") === activeCode,
    );
    if (
      activeZone &&
      !top.some((zone) => String(zone?.value || "") === activeCode)
    ) {
      top.unshift(activeZone);
    }
    return Array.from(
      new Map(top.map((zone) => [String(zone?.value || ""), zone])).values(),
    ).slice(0, 5);
  });

  const zoneCode = (zone) => {
    const value = String(zone?.value || "")
      .trim()
      .toUpperCase();
    if (!value) {
      return "--";
    }
    if (value === TRASH_OWNER_GENERAL) {
      return "OG";
    }
    if (bgCodePattern.test(value)) {
      return value;
    }
    return value.length <= 4 ? value : value.slice(0, 4);
  };

  const zoneMeterStyle = (zone) => {
    const count = Number(zone?.count || 0);
    const rawCapacity = zone?.capacity;
    const capacity = Number(rawCapacity);
    if (
      rawCapacity === null ||
      rawCapacity === undefined ||
      rawCapacity === "" ||
      !Number.isFinite(capacity) ||
      capacity <= 0
    ) {
      return { width: "100%" };
    }
    const fill = Math.max(
      0,
      Math.min(100, Math.round((count / capacity) * 100)),
    );
    return { width: `${fill}%` };
  };

  const closeZonePanel = () => {
    isZonePanelOpen.value = false;
    trashZoneSearch.value = "";
  };

  const selectTrashZone = (ownerCode, options = {}) => {
    ctx.trashZoneOwnerCodeModel = ownerCode;
    if (options.closePanel !== false) {
      closeZonePanel();
    }
  };

  const shiftTrashZone = (step) => {
    const options = sortedTrashZoneOptions.value;
    if (!options.length) {
      return;
    }
    const currentIndex = options.findIndex(
      (zone) => String(zone?.value || "") === activeTrashZoneValue.value,
    );
    const fromIndex = currentIndex >= 0 ? currentIndex : 0;
    const targetIndex = (fromIndex + step + options.length) % options.length;
    selectTrashZone(options[targetIndex]?.value, { closePanel: false });
  };

  const selectPreviousTrashZone = () => shiftTrashZone(-1);
  const selectNextTrashZone = () => shiftTrashZone(1);

  const openZonePanel = async () => {
    if (isZonePanelOpen.value) {
      return;
    }
    isZonePanelOpen.value = true;
    await nextTick();
    trashZoneSearchInputRef.value?.focus();
  };

  const toggleZonePanel = () => {
    if (isZonePanelOpen.value) {
      closeZonePanel();
      return;
    }
    openZonePanel();
  };

  const handlePointerDown = (event) => {
    if (!isZonePanelOpen.value) {
      return;
    }
    const target = event.target;
    if (!(target instanceof Node) || trashZoneHubRef.value?.contains(target)) {
      return;
    }
    closeZonePanel();
  };

  const handleWindowKeydown = (event) => {
    if (isZonePanelOpen.value && event.key === "Escape") {
      closeZonePanel();
    }
  };

  watch(
    () => sortedTrashZoneOptions.value.map((zone) => String(zone?.value || "")),
    (codes) => {
      if (!codes.length || codes.includes(activeTrashZoneValue.value)) {
        return;
      }
      selectTrashZone(codes[0], { closePanel: false });
    },
    { deep: true },
  );

  onMounted(() => {
    document.addEventListener("pointerdown", handlePointerDown);
    window.addEventListener("keydown", handleWindowKeydown);
  });

  onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", handlePointerDown);
    window.removeEventListener("keydown", handleWindowKeydown);
  });

  return {
    activeTrashZoneCapacityLabel,
    activeTrashZoneOption,
    activeTrashZoneOrdinal,
    activeTrashZoneValue,
    capacityLabelForZone,
    ctx,
    closeZonePanel,
    filteredTrashZoneOptions,
    hotTrashZoneOptions,
    isZonePanelOpen,
    openZonePanel,
    selectNextTrashZone,
    selectPreviousTrashZone,
    selectTrashZone,
    toggleZonePanel,
    trashZoneHubRef,
    trashZoneSearch,
    trashZoneSearchInputRef,
    zoneCode,
    zoneMeterStyle,
  };
};
