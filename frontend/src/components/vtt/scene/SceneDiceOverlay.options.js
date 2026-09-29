import DiceRoller from "@/components/dice/DiceRoller.vue";

export default {
  name: "SceneDiceOverlay",
  components: { DiceRoller },
  props: {
    visible: { type: Boolean, default: false },
    mode: {
      type: String,
      default: "quick",
      validator: (value) => ["quick", "selector"].includes(value),
    },
    rollRequest: { type: Number, default: 0 },
  },
  emits: ["close", "error", "roll-complete"],
  data: () => ({
    ready: false,
    error: false,
    pendingAction: "",
    lastRollRequest: 0,
    diceDisplayList: [
      "dc",
      "d4",
      "d6",
      "d8",
      "d10",
      "D10",
      "d100",
      "d12",
      "d20",
    ],
  }),
  watch: {
    visible: {
      immediate: true,
      handler(visible) {
        if (!visible) {
          this.pendingAction = "";
          this.$refs.roller?.clear?.();
          return;
        }
        this.error = false;
        this.queueVisibleAction();
      },
    },
    mode(mode) {
      if (!this.visible || mode !== "selector") return;
      this.pendingAction = "selector";
      this.flushPendingAction();
    },
    rollRequest: {
      immediate: true,
      handler(request) {
        if (!this.visible || Number(request) <= this.lastRollRequest) return;
        this.queueRoll(request);
      },
    },
  },
  mounted() {
    window.addEventListener("keydown", this.handleKeydown);
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.handleKeydown);
    this.$refs.roller?.clear?.();
  },
  methods: {
    handleReady() {
      this.ready = true;
      this.flushPendingAction();
    },
    handleError(error) {
      this.error = true;
      this.$emit("error", error);
    },
    handleRollComplete(result) {
      this.$emit("roll-complete", result);
    },
    queueVisibleAction() {
      if (this.mode === "selector") {
        this.pendingAction = "selector";
        this.flushPendingAction();
        return;
      }
      if (this.rollRequest > this.lastRollRequest) {
        this.queueRoll(this.rollRequest);
      }
    },
    queueRoll(request) {
      this.lastRollRequest = Number(request) || 0;
      this.pendingAction = "roll";
      this.flushPendingAction();
    },
    flushPendingAction() {
      if (!this.visible || !this.ready || !this.pendingAction) return;
      const action = this.pendingAction;
      this.pendingAction = "";
      this.$nextTick(() => {
        if (!this.visible) return;
        const roller = this.$refs.roller;
        roller?.resize?.();
        if (action === "selector") {
          roller?.clear?.();
          roller?.showSelector?.();
        } else {
          roller?.roll?.("1d100+1d10");
        }
        this.$refs.closeButton?.focus?.({ preventScroll: true });
      });
    },
    requestClose() {
      this.pendingAction = "";
      this.$refs.roller?.clear?.();
      this.$emit("close");
    },
    handleKeydown(event) {
      if (!this.visible || event.key !== "Escape") return;
      event.preventDefault();
      event.stopPropagation();
      this.requestClose();
    },
  },
};
