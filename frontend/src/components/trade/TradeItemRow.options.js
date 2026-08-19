import { resolveDisplayedPrice } from "@/lib/trade/shopPriceCalculator";
import TradeCoinLine from "@/components/trade/TradeCoinLine.vue";

export default {
  name: "TradeItemRow",
  components: {
    TradeCoinLine,
  },
  props: {
    item: {
      type: Object,
      required: true,
    },
    mode: {
      type: String,
      required: true,
    },
    rowClass: {
      type: String,
      default: "",
    },
    isAct: {
      type: Boolean,
      default: false,
    },
    isActive: {
      type: Boolean,
      default: false,
    },
    selectedQuantity: {
      type: [Number, String],
      default: 1,
    },
    canAdjustQuantity: {
      type: Boolean,
      default: false,
    },
    showPrice: {
      type: Boolean,
      default: true,
    },
    showTempHidden: {
      type: Boolean,
      default: false,
    },
    iconClass: {
      type: String,
      default: "v0001",
    },
    imageSrc: {
      type: String,
      default: "",
    },
  },
  emits: ["select", "open-detail", "qty-step", "qty-input"],
  data() {
    return {
      showQtyPopover: false,
      imageFailed: false,
    };
  },
  computed: {
    resolvedImageSrc() {
      return this.imageFailed ? "" : String(this.imageSrc || "").trim();
    },
    displayedPrice() {
      return resolveDisplayedPrice(this.item);
    },
    shortDesc() {
      const text = String(
        this.item?.PERSONAL_DESC ||
          this.item?.personalDesc ||
          this.item?.DESCRIPTION ||
          this.item?.description ||
          this.item?.DETAILS ||
          this.item?.details ||
          "",
      ).trim();
      return text || this.$t("shop.itemDetailDialog.noDataShort");
    },
    stackLabel() {
      if (this.item?.QUANTITY !== undefined && this.item?.QUANTITY !== null) {
        const quantity = Number(this.item.QUANTITY);
        if (Number.isFinite(quantity) && quantity >= 2) {
          return Math.max(0, Math.round(quantity));
        }
      }
      return "";
    },
    maxQuantity() {
      const quantity = Number(this.item?.QUANTITY);
      if (!Number.isFinite(quantity)) {
        return 1;
      }
      return Math.max(1, Math.round(quantity));
    },
    canShowQtyTrigger() {
      return this.canAdjustQuantity && this.isActive && this.maxQuantity > 1;
    },
    isUnavailable() {
      if (
        this.item?.AVAILABLE === false ||
        this.item?.available === false ||
        this.item?.UNAVAILABLE === true ||
        this.item?.unavailable === true
      ) {
        return true;
      }
      if (this.item?.QUANTITY === undefined || this.item?.QUANTITY === null) {
        return false;
      }
      const quantity = Number(this.item.QUANTITY);
      return Number.isFinite(quantity) && quantity <= 0;
    },
  },
  watch: {
    imageSrc() {
      this.imageFailed = false;
    },
    canShowQtyTrigger(value) {
      if (!value) {
        this.closeQtyPopover();
      }
    },
    isActive(value) {
      if (!value) {
        this.closeQtyPopover();
      }
    },
    showQtyPopover(value) {
      if (value) {
        this.bindPopoverListeners();
        return;
      }
      this.unbindPopoverListeners();
    },
  },
  beforeUnmount() {
    this.unbindPopoverListeners();
  },
  methods: {
    handleSelect() {
      if (this.isUnavailable) {
        return;
      }
      this.$emit("select", this.item);
    },
    handleImageError() {
      this.imageFailed = true;
    },
    bindPopoverListeners() {
      if (typeof document === "undefined") {
        return;
      }
      document.addEventListener("click", this.handleOutsideClick, true);
      document.addEventListener("keydown", this.handleEscape, true);
    },
    unbindPopoverListeners() {
      if (typeof document === "undefined") {
        return;
      }
      document.removeEventListener("click", this.handleOutsideClick, true);
      document.removeEventListener("keydown", this.handleEscape, true);
    },
    toggleQtyPopover() {
      if (!this.canShowQtyTrigger) {
        return;
      }
      this.showQtyPopover = !this.showQtyPopover;
    },
    closeQtyPopover() {
      this.showQtyPopover = false;
    },
    handleOutsideClick(event) {
      if (!this.showQtyPopover) {
        return;
      }
      const root = this.$refs.rowRoot;
      if (root && root.contains(event.target)) {
        return;
      }
      this.closeQtyPopover();
    },
    handleEscape(event) {
      if (!this.showQtyPopover) {
        return;
      }
      if (event.key === "Escape") {
        this.closeQtyPopover();
      }
    },
    stepQty(delta) {
      const step = Number(delta);
      if (!Number.isFinite(step)) {
        return;
      }
      const currentRaw = Number(this.selectedQuantity);
      const current = Number.isFinite(currentRaw) ? Math.round(currentRaw) : 1;
      const next = Math.max(1, Math.min(this.maxQuantity, current + step));
      this.$emit("qty-step", next - current);
    },
    handleQuantityInput(event) {
      const raw = Number(event?.target?.value);
      if (!Number.isFinite(raw)) {
        return;
      }
      const next = Math.max(1, Math.min(this.maxQuantity, Math.round(raw)));
      this.$emit("qty-input", next);
    },
  },
};
