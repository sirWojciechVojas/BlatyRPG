import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

const OWNER_FILTER_ALL = "all";
const bgCodePattern = /^BG(\d+)$/;

export const useDefaultStackView = () => {
  const ctx = useTradeModalContext();
  const ownerZoneHubRef = ref(null);
  const ownerZoneSearchInputRef = ref(null);
  const ownerZoneSearch = ref("");
  const isOwnerPanelOpen = ref(false);

  const normalizeOwnerFilterValue = (value) => {
    const raw = String(value || "")
      .trim()
      .toUpperCase();
    if (!raw || raw === "ALL") {
      return OWNER_FILTER_ALL;
    }
    return raw;
  };

  const compareOwnerCodes = (leftCode, rightCode) => {
    const left = normalizeOwnerFilterValue(leftCode);
    const right = normalizeOwnerFilterValue(rightCode);
    if (left === OWNER_FILTER_ALL && right === OWNER_FILTER_ALL) {
      return 0;
    }
    if (left === OWNER_FILTER_ALL) {
      return -1;
    }
    if (right === OWNER_FILTER_ALL) {
      return 1;
    }
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

  const sortedOwnerFilterOptions = computed(() => {
    const options = Array.isArray(ctx.inventoryOwnerFilterOptions)
      ? ctx.inventoryOwnerFilterOptions
      : [];
    const allOption = options.find(
      (entry) => normalizeOwnerFilterValue(entry?.value) === OWNER_FILTER_ALL,
    );
    const ownerOptions = options
      .filter(
        (entry) => normalizeOwnerFilterValue(entry?.value) !== OWNER_FILTER_ALL,
      )
      .sort((left, right) => compareOwnerCodes(left?.value, right?.value));
    return allOption ? [allOption, ...ownerOptions] : ownerOptions;
  });

  const activeOwnerFilterValue = computed(() =>
    normalizeOwnerFilterValue(ctx.inventoryOwnerCodeFilterModel),
  );

  const activeOwnerFilterOption = computed(
    () =>
      sortedOwnerFilterOptions.value.find(
        (entry) =>
          normalizeOwnerFilterValue(entry?.value) ===
          activeOwnerFilterValue.value,
      ) ||
      sortedOwnerFilterOptions.value[0] ||
      null,
  );

  const activeOwnerFilterOrdinal = computed(() => {
    const total = sortedOwnerFilterOptions.value.length;
    if (!total) {
      return "0/0";
    }
    const index = sortedOwnerFilterOptions.value.findIndex(
      (entry) =>
        normalizeOwnerFilterValue(entry?.value) ===
        activeOwnerFilterValue.value,
    );
    return `${index >= 0 ? index + 1 : 1}/${total}`;
  });

  const ownerFilterTotalCount = computed(() => {
    const allOption = sortedOwnerFilterOptions.value.find(
      (entry) => normalizeOwnerFilterValue(entry?.value) === OWNER_FILTER_ALL,
    );
    if (allOption) {
      return Number(allOption?.count || 0);
    }
    return sortedOwnerFilterOptions.value.reduce(
      (sum, entry) => sum + Number(entry?.count || 0),
      0,
    );
  });

  const filteredOwnerFilterOptions = computed(() => {
    const phrase = String(ownerZoneSearch.value || "")
      .trim()
      .toLowerCase();
    if (!phrase) {
      return sortedOwnerFilterOptions.value;
    }
    return sortedOwnerFilterOptions.value.filter((entry) => {
      const label = String(entry?.label || "").toLowerCase();
      const value = String(entry?.value || "").toLowerCase();
      return label.includes(phrase) || value.includes(phrase);
    });
  });

  const hotOwnerFilterOptions = computed(() => {
    const activeCode = activeOwnerFilterValue.value;
    const nonEmpty = sortedOwnerFilterOptions.value.filter(
      (entry) => Number(entry?.count || 0) > 0,
    );
    const source = nonEmpty.length ? nonEmpty : sortedOwnerFilterOptions.value;
    const top = source.slice(0, 5);
    const activeOption = sortedOwnerFilterOptions.value.find(
      (entry) => normalizeOwnerFilterValue(entry?.value) === activeCode,
    );
    if (
      activeOption &&
      !top.some(
        (entry) =>
          normalizeOwnerFilterValue(entry?.value) ===
          normalizeOwnerFilterValue(activeOption?.value),
      )
    ) {
      top.unshift(activeOption);
    }
    return Array.from(
      new Map(
        top.map((entry) => [normalizeOwnerFilterValue(entry?.value), entry]),
      ).values(),
    ).slice(0, 5);
  });

  const ownerCode = (option) => {
    const value = normalizeOwnerFilterValue(option?.value);
    if (value === OWNER_FILTER_ALL) {
      return "ALL";
    }
    if (value === "DEFAULT") {
      return "STOS";
    }
    if (bgCodePattern.test(value)) {
      return value;
    }
    return value.length <= 4 ? value : value.slice(0, 4);
  };

  const ownerMeterStyle = (option) => {
    const count = Number(option?.count || 0);
    const total = Math.max(1, Number(ownerFilterTotalCount.value || 0));
    const fill = Math.max(0, Math.min(100, Math.round((count / total) * 100)));
    return { width: `${fill}%` };
  };

  const closeOwnerPanel = () => {
    isOwnerPanelOpen.value = false;
    ownerZoneSearch.value = "";
  };

  const selectOwnerFilter = (ownerCodeValue, options = {}) => {
    ctx.inventoryOwnerCodeFilterModel =
      normalizeOwnerFilterValue(ownerCodeValue);
    if (options.closePanel !== false) {
      closeOwnerPanel();
    }
  };

  const shiftOwnerFilter = (step) => {
    const options = sortedOwnerFilterOptions.value;
    if (!options.length) {
      return;
    }
    const currentIndex = options.findIndex(
      (entry) =>
        normalizeOwnerFilterValue(entry?.value) ===
        activeOwnerFilterValue.value,
    );
    const fromIndex = currentIndex >= 0 ? currentIndex : 0;
    const targetIndex = (fromIndex + step + options.length) % options.length;
    selectOwnerFilter(options[targetIndex]?.value, { closePanel: false });
  };

  const selectPreviousOwnerFilter = () => shiftOwnerFilter(-1);
  const selectNextOwnerFilter = () => shiftOwnerFilter(1);

  const openOwnerPanel = async () => {
    if (isOwnerPanelOpen.value || !sortedOwnerFilterOptions.value.length) {
      return;
    }
    isOwnerPanelOpen.value = true;
    await nextTick();
    ownerZoneSearchInputRef.value?.focus();
  };

  const toggleOwnerPanel = () => {
    if (isOwnerPanelOpen.value) {
      closeOwnerPanel();
      return;
    }
    openOwnerPanel();
  };

  const handlePointerDown = (event) => {
    if (!isOwnerPanelOpen.value) {
      return;
    }
    const target = event.target;
    if (!(target instanceof Node) || ownerZoneHubRef.value?.contains(target)) {
      return;
    }
    closeOwnerPanel();
  };

  const handleWindowKeydown = (event) => {
    if (isOwnerPanelOpen.value && event.key === "Escape") {
      closeOwnerPanel();
    }
  };

  watch(
    () =>
      sortedOwnerFilterOptions.value.map((entry) =>
        normalizeOwnerFilterValue(entry?.value),
      ),
    (codes) => {
      if (!codes.length || codes.includes(activeOwnerFilterValue.value)) {
        return;
      }
      selectOwnerFilter(codes[0], { closePanel: false });
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
    activeOwnerFilterOption,
    activeOwnerFilterOrdinal,
    activeOwnerFilterValue,
    ctx,
    filteredOwnerFilterOptions,
    hotOwnerFilterOptions,
    isOwnerPanelOpen,
    normalizeOwnerFilterValue,
    openOwnerPanel,
    ownerCode,
    ownerFilterTotalCount,
    ownerMeterStyle,
    ownerZoneHubRef,
    ownerZoneSearch,
    ownerZoneSearchInputRef,
    selectNextOwnerFilter,
    selectOwnerFilter,
    selectPreviousOwnerFilter,
    sortedOwnerFilterOptions,
    toggleOwnerPanel,
  };
};
