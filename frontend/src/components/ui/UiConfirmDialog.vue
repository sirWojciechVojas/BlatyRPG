<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="ui-confirm-dialog__backdrop"
      data-ui-confirm-dialog
    >
      <section
        ref="dialog"
        class="ui-confirm-dialog"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :aria-describedby="descriptionId"
        :aria-busy="busy || undefined"
        tabindex="-1"
        @keydown="handleKeydown"
      >
        <header class="ui-confirm-dialog__header">
          <h2 :id="titleId">{{ title }}</h2>
        </header>

        <div class="ui-confirm-dialog__body">
          <p :id="descriptionId">{{ description }}</p>
        </div>

        <footer class="ui-confirm-dialog__actions">
          <UiButton
            data-confirm-dialog-cancel
            :disabled="busy"
            @click="requestCancel('button')"
          >
            {{ cancelLabel }}
          </UiButton>
          <UiButton
            data-confirm-dialog-confirm
            :variant="danger ? 'danger' : 'primary'"
            :disabled="busy"
            :loading="busy"
            @click="requestConfirm"
          >
            {{ confirmLabel }}
          </UiButton>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script>
import UiButton from "./UiButton.vue";

const FOCUSABLE_SELECTOR = [
  "button:not([disabled])",
  "[href]",
  "input:not([disabled])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  '[tabindex]:not([tabindex="-1"])',
].join(",");

let dialogSequence = 0;

export default {
  name: "UiConfirmDialog",
  components: { UiButton },
  props: {
    modelValue: Boolean,
    title: {
      type: String,
      required: true,
    },
    description: {
      type: String,
      required: true,
    },
    confirmLabel: {
      type: String,
      default: "Confirm",
    },
    cancelLabel: {
      type: String,
      default: "Cancel",
    },
    busy: Boolean,
    danger: Boolean,
  },
  emits: ["update:modelValue", "confirm", "cancel"],
  data() {
    dialogSequence += 1;

    return {
      titleId: "ui-confirm-dialog-title-" + dialogSequence,
      descriptionId: "ui-confirm-dialog-description-" + dialogSequence,
      previouslyFocusedElement: null,
    };
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(isOpen, wasOpen) {
        if (isOpen && !wasOpen) {
          this.captureFocus();
          this.$nextTick(() => this.focusInitialControl());
        } else if (!isOpen && wasOpen) {
          this.restoreFocus();
        }
      },
    },
  },
  beforeUnmount() {
    this.restoreFocusImmediately();
  },
  methods: {
    captureFocus() {
      const activeElement = document.activeElement;
      this.previouslyFocusedElement =
        activeElement && typeof activeElement.focus === "function"
          ? activeElement
          : null;
    },
    focusInitialControl() {
      const dialog = this.$refs.dialog;
      const cancelButton = dialog?.querySelector(
        "[data-confirm-dialog-cancel]",
      );

      if (cancelButton && !cancelButton.disabled) {
        cancelButton.focus();
      } else {
        dialog?.focus();
      }
    },
    getFocusableElements() {
      const dialog = this.$refs.dialog;
      if (!dialog) {
        return [];
      }

      return Array.from(dialog.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
        (element) =>
          !element.hasAttribute("hidden") &&
          element.getAttribute("aria-hidden") !== "true",
      );
    },
    handleKeydown(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        this.requestCancel("escape");
        return;
      }

      if (event.key === "Tab") {
        this.trapFocus(event);
      }
    },
    trapFocus(event) {
      const focusableElements = this.getFocusableElements();
      const firstElement = focusableElements[0];
      const lastElement = focusableElements.at(-1);

      if (!firstElement || !lastElement) {
        event.preventDefault();
        this.$refs.dialog?.focus();
        return;
      }

      const activeElement = document.activeElement;
      const leavingBackward =
        event.shiftKey &&
        (activeElement === firstElement ||
          !this.$refs.dialog?.contains(activeElement));
      const leavingForward =
        !event.shiftKey &&
        (activeElement === lastElement ||
          !this.$refs.dialog?.contains(activeElement));

      if (leavingBackward || leavingForward) {
        event.preventDefault();
        (leavingBackward ? lastElement : firstElement).focus();
      }
    },
    requestCancel(reason) {
      if (this.busy) {
        return;
      }

      this.$emit("cancel", reason);
      this.$emit("update:modelValue", false);
    },
    requestConfirm() {
      if (!this.busy) {
        this.$emit("confirm");
      }
    },
    restoreFocus() {
      const element = this.previouslyFocusedElement;
      this.previouslyFocusedElement = null;
      this.$nextTick(() => {
        if (element?.isConnected) {
          element.focus();
        }
      });
    },
    restoreFocusImmediately() {
      const element = this.previouslyFocusedElement;
      this.previouslyFocusedElement = null;
      if (element?.isConnected) {
        element.focus();
      }
    },
  },
};
</script>

<style scoped>
.ui-confirm-dialog__backdrop {
  position: fixed;
  inset: 0;
  z-index: var(--ui-z-modal-backdrop);
  display: grid;
  padding: var(--ui-space-5);
  place-items: center;
  background: var(--ui-color-backdrop);
}

.ui-confirm-dialog {
  z-index: var(--ui-z-modal);
  width: min(28rem, 100%);
  overflow: hidden;
  border: var(--ui-border);
  border-radius: var(--ui-radius-md);
  background: var(--ui-color-surface-raised);
  color: var(--ui-color-text);
  box-shadow: var(--ui-shadow-lg);
}

.ui-confirm-dialog:focus {
  outline: none;
}

.ui-confirm-dialog__header,
.ui-confirm-dialog__body,
.ui-confirm-dialog__actions {
  padding: var(--ui-space-4);
}

.ui-confirm-dialog__header {
  border-bottom: var(--ui-border);
}

.ui-confirm-dialog__header h2,
.ui-confirm-dialog__body p {
  margin: 0;
}

.ui-confirm-dialog__header h2 {
  font-size: var(--ui-font-size-lg);
}

.ui-confirm-dialog__body {
  color: var(--ui-color-text-muted);
}

.ui-confirm-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--ui-space-2);
  border-top: var(--ui-border);
}
</style>
