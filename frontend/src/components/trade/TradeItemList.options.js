import TradeItemRow from "@/components/trade/TradeItemRow.vue";

export default {
  name: "TradeItemList",
  components: {
    TradeItemRow,
  },
  props: {
    mode: {
      type: String,
      required: true,
    },
    items: {
      type: Array,
      default: () => [],
    },
    rowClass: {
      type: String,
      default: "",
    },
    listClass: {
      type: [String, Array, Object],
      default: "",
    },
    activeIds: {
      type: Array,
      default: () => [],
    },
    actId: {
      type: [Number, String],
      default: null,
    },
    showTempHidden: {
      type: Boolean,
      default: false,
    },
    iconClassForItem: {
      type: Function,
      required: true,
    },
    imageSrcForItem: {
      type: Function,
      default: () => "",
    },
    selectedQuantityForItem: {
      type: Function,
      default: () => 1,
    },
    canAdjustQuantityForItem: {
      type: Function,
      default: () => false,
    },
    showPrice: {
      type: Boolean,
      default: true,
    },
    emptyText: {
      type: String,
      default: "",
    },
    loading: {
      type: Boolean,
      default: false,
    },
    loadingText: {
      type: String,
      default: "",
    },
    errorText: {
      type: String,
      default: "",
    },
    retryLabel: {
      type: String,
      default: "",
    },
    density: {
      type: String,
      default: "compact",
    },
    visibleRows: {
      type: Number,
      default: 0,
    },
  },
  emits: ["select", "open-detail", "qty-step", "qty-input", "retry"],
  data() {
    return {
      scrollTop: 0,
      viewportHeight: 420,
      viewportWidth:
        typeof window === "undefined" ? 1280 : Number(window.innerWidth),
      measuredRowHeight: 0,
      rowGap: 0,
      viewportObserver: null,
      scrollSnapTimer: null,
      isSnappingScroll: false,
    };
  },
  computed: {
    normalizedItems() {
      return Array.isArray(this.items) ? this.items : [];
    },
    visibleItems() {
      if (this.loading || this.errorText) {
        return [];
      }
      return this.normalizedItems.slice(
        this.visibleStart,
        this.visibleStart + this.visibleCount,
      );
    },
    rowHeight() {
      const fallback =
        this.density === "comfortable"
          ? 48
          : this.viewportWidth <= 600
            ? 44
            : 40;
      return this.measuredRowHeight || fallback;
    },
    rowStride() {
      return this.rowHeight + this.rowGap;
    },
    virtualContentHeight() {
      if (!this.normalizedItems.length) {
        return 0;
      }
      return this.normalizedItems.length * this.rowStride - this.rowGap;
    },
    visibleStart() {
      return Math.max(0, Math.floor(this.scrollTop / this.rowStride) - 6);
    },
    visibleCount() {
      return Math.ceil(this.viewportHeight / this.rowStride) + 12;
    },
    showEmptyState() {
      return !this.loading && !this.errorText && !this.normalizedItems.length;
    },
    resolvedEmptyText() {
      return this.emptyText || this.$t("shop.common.emptyItems");
    },
    resolvedLoadingText() {
      return this.loadingText || this.$t("common.loading");
    },
    resolvedRetryLabel() {
      return this.retryLabel || this.$t("actions.refresh");
    },
  },
  watch: {
    items: {
      immediate: true,
      handler() {
        this.resetViewport();
        this.$nextTick(this.syncLayoutMetrics);
      },
    },
    loading() {
      this.resetViewport();
    },
    errorText() {
      this.resetViewport();
    },
    density() {
      this.measuredRowHeight = 0;
      this.$nextTick(this.syncLayoutMetrics);
    },
  },
  mounted() {
    this.syncLayoutMetrics();
    if (typeof ResizeObserver !== "undefined" && this.$refs.viewport) {
      this.viewportObserver = new ResizeObserver(this.syncLayoutMetrics);
      this.viewportObserver.observe(this.$refs.viewport);
    }
    window.addEventListener("resize", this.handleResize);
  },
  updated() {
    this.syncLayoutMetrics();
  },
  beforeUnmount() {
    this.viewportObserver?.disconnect();
    clearTimeout(this.scrollSnapTimer);
    window.removeEventListener("resize", this.handleResize);
  },
  methods: {
    resetViewport() {
      this.scrollTop = 0;
      if (this.$refs.viewport) {
        this.$refs.viewport.scrollTop = 0;
      }
    },
    handleScroll(event) {
      this.scrollTop = Number(event.currentTarget.scrollTop || 0);
      this.viewportHeight = Number(event.currentTarget.clientHeight || 420);
      clearTimeout(this.scrollSnapTimer);
      this.scrollSnapTimer = setTimeout(this.snapToWholeRow, 90);
    },
    handleScrollEnd() {
      clearTimeout(this.scrollSnapTimer);
      this.snapToWholeRow();
    },
    snapToWholeRow() {
      const viewport = this.$refs.viewport;
      if (!viewport || this.isSnappingScroll || this.rowStride <= 0) {
        return;
      }
      const visibleRows = Math.max(1, Number(this.visibleRows) || 1);
      const lastStart = Math.max(0, this.normalizedItems.length - visibleRows);
      const targetIndex = Math.min(
        lastStart,
        Math.max(0, Math.round(viewport.scrollTop / this.rowStride)),
      );
      const targetScrollTop = targetIndex * this.rowStride;
      if (Math.abs(viewport.scrollTop - targetScrollTop) < 0.5) {
        return;
      }
      this.isSnappingScroll = true;
      viewport.scrollTop = targetScrollTop;
      this.scrollTop = targetScrollTop;
      this.$nextTick(() => {
        this.isSnappingScroll = false;
      });
    },
    handleResize() {
      this.viewportWidth = Number(window.innerWidth || 1280);
      this.syncLayoutMetrics();
    },
    syncLayoutMetrics() {
      const viewport = this.$refs.viewport;
      if (!viewport) {
        return;
      }

      const viewportHeight = Number(viewport.clientHeight || 420);
      if (viewportHeight !== this.viewportHeight) {
        this.viewportHeight = viewportHeight;
      }

      const windowElement = viewport.querySelector(".trade-virtual-window");
      const rowElement = viewport.querySelector(".trade-modal__row");
      const rowHeight = Number(rowElement?.getBoundingClientRect().height || 0);
      const configuredGap = Number.parseFloat(
        getComputedStyle(viewport).getPropertyValue("--trade-list-gap"),
      );
      const renderedGap = Number.parseFloat(
        windowElement ? getComputedStyle(windowElement).rowGap : "",
      );
      const rowGap = Number.isFinite(configuredGap)
        ? configuredGap
        : Number.isFinite(renderedGap)
          ? renderedGap
          : 0;

      const visibleRows = Math.max(
        0,
        Math.round(Number(this.visibleRows) || 0),
      );
      let effectiveRowHeight = rowHeight;
      if (visibleRows > 0 && viewportHeight > 0) {
        const fittedRowHeight =
          (viewportHeight - Math.max(0, visibleRows - 1) * rowGap) /
          visibleRows;
        effectiveRowHeight = Math.max(1, fittedRowHeight);
        viewport.style.setProperty(
          "--trade-item-row-height",
          `${effectiveRowHeight}px`,
        );
      } else {
        viewport.style.removeProperty("--trade-item-row-height");
      }

      if (
        effectiveRowHeight > 0 &&
        Math.abs(effectiveRowHeight - this.measuredRowHeight) > 0.5
      ) {
        this.measuredRowHeight = effectiveRowHeight;
      }
      if (Math.abs(rowGap - this.rowGap) > 0.5) {
        this.rowGap = rowGap;
      }
    },
    isActive(item) {
      return this.activeIds.some((id) => Number(id) === Number(item.ID));
    },
    isAct(item) {
      return this.actId !== null && Number(this.actId) === Number(item.ID);
    },
    handleOpenDetail(item, mode) {
      this.$emit("open-detail", item, mode);
    },
    selectedQuantityFor(item) {
      const raw = Number(this.selectedQuantityForItem(item));
      return Number.isFinite(raw) && raw > 0 ? raw : 1;
    },
    canAdjustQuantityFor(item) {
      return !!this.canAdjustQuantityForItem(item);
    },
  },
};
