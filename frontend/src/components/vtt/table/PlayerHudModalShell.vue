<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="player-hud-modal-shell__backdrop"
      data-player-hud-modal-backdrop
      @click.self="requestClose('backdrop')"
    >
      <section
        ref="dialog"
        class="player-hud-modal-shell"
        :style="shellStyle"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
      >
        <header class="player-hud-modal-shell__header">
          <h2 :id="titleId">{{ title }}</h2>
          <button
            ref="closeButton"
            type="button"
            class="player-hud-modal-shell__close"
            :title="closeLabel"
            :aria-label="closeLabel"
            data-player-hud-modal-close
            @click="requestClose('button')"
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </header>

        <div class="player-hud-modal-shell__body" :class="contentClass">
          <slot />
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script>
const FOCUSABLE_SELECTOR = [
  "button:not([disabled])",
  "[href]",
  "input:not([disabled])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  '[tabindex]:not([tabindex="-1"])',
].join(",");

let modalSequence = 0;

export default {
  name: "PlayerHudModalShell",
  props: {
    modelValue: { type: Boolean, default: false },
    title: { type: String, required: true },
    width: { type: [Number, String], default: 720 },
    height: { type: [Number, String], default: 420 },
    contentClass: { type: [String, Array, Object], default: "" },
    closeLabel: { type: String, required: true },
  },
  emits: ["update:modelValue", "request-close"],
  data() {
    modalSequence += 1;
    return {
      titleId: `player-hud-modal-title-${modalSequence}`,
      previouslyFocusedElement: null,
      previousBodyOverflow: "",
    };
  },
  computed: {
    shellStyle() {
      const cssSize = (value, fallback) => {
        if (typeof value === "number" && Number.isFinite(value)) {
          return `${value}px`;
        }
        const normalized = String(value || "").trim();
        return normalized || `${fallback}px`;
      };
      return {
        "--player-hud-modal-width": cssSize(this.width, 720),
        "--player-hud-modal-height": cssSize(this.height, 420),
      };
    },
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(isOpen, wasOpen) {
        if (isOpen && !wasOpen) {
          this.openModal();
        } else if (!isOpen && wasOpen) {
          this.closeModal();
        }
      },
    },
  },
  beforeUnmount() {
    this.closeModal(true);
  },
  methods: {
    openModal() {
      const activeElement = document.activeElement;
      this.previouslyFocusedElement =
        activeElement && typeof activeElement.focus === "function"
          ? activeElement
          : null;
      this.previousBodyOverflow = document.body.style.overflow;
      document.body.style.overflow = "hidden";
      document.addEventListener("keydown", this.handleKeydown);
      this.$nextTick(() => {
        (this.$refs.closeButton || this.$refs.dialog)?.focus();
      });
    },
    closeModal(immediate = false) {
      document.removeEventListener("keydown", this.handleKeydown);
      if (typeof document !== "undefined") {
        document.body.style.overflow = this.previousBodyOverflow;
      }
      const element = this.previouslyFocusedElement;
      this.previouslyFocusedElement = null;
      if (!element?.isConnected) return;
      if (immediate) {
        element.focus();
      } else {
        this.$nextTick(() => element.focus());
      }
    },
    requestClose(reason) {
      this.$emit("request-close", reason);
    },
    handleKeydown(event) {
      if (!this.modelValue || event.defaultPrevented) return;
      if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        this.requestClose("escape");
        return;
      }
      if (event.key === "Tab" && this.$refs.dialog?.contains(event.target)) {
        this.trapFocus(event);
      }
    },
    focusableElements() {
      return Array.from(
        this.$refs.dialog?.querySelectorAll(FOCUSABLE_SELECTOR) || [],
      ).filter(
        (element) =>
          !element.hasAttribute("hidden") &&
          element.getAttribute("aria-hidden") !== "true",
      );
    },
    trapFocus(event) {
      const focusable = this.focusableElements();
      const first = focusable[0];
      const last = focusable.at(-1);
      if (!first || !last) {
        event.preventDefault();
        this.$refs.dialog?.focus();
        return;
      }
      const active = document.activeElement;
      const leavingBackward =
        event.shiftKey &&
        (active === first || !this.$refs.dialog?.contains(active));
      const leavingForward =
        !event.shiftKey &&
        (active === last || !this.$refs.dialog?.contains(active));
      if (leavingBackward || leavingForward) {
        event.preventDefault();
        (leavingBackward ? last : first).focus();
      }
    },
  },
};
</script>

<style src="./player-hud-modal-shell.css"></style>
