<!-- Responsibility: ConfirmDialog shop interface component. -->
<template>
  <div
    v-if="open"
    class="confirm-dialog"
    role="presentation"
    @click.self="$emit('cancel')"
  >
    <section
      role="alertdialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      class="confirm-dialog__panel"
    >
      <h2 :id="titleId">{{ title }}</h2>
      <p>{{ message }}</p>
      <div class="confirm-dialog__actions">
        <button type="button" @click="$emit('cancel')">
          {{ cancelLabel }}
        </button>
        <button
          type="button"
          class="confirm-dialog__confirm"
          @click="$emit('confirm')"
        >
          {{ confirmLabel }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, required: true },
  confirmLabel: { type: String, default: "OK" },
  cancelLabel: { type: String, default: "Cancel" },
  titleId: { type: String, default: "confirm-dialog-title" },
});
defineEmits(["confirm", "cancel"]);
</script>

<style scoped>
.confirm-dialog {
  position: fixed;
  z-index: 1200;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(0, 0, 0, 0.64);
  padding: 1rem;
}
.confirm-dialog__panel {
  width: min(28rem, 100%);
  border: 1px solid #484d57;
  border-radius: 0.45rem;
  background: #191b20;
  color: #eff1f4;
  padding: 1rem;
  box-shadow: 0 1rem 3rem #0009;
}
.confirm-dialog__panel h2 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
}
.confirm-dialog__panel p {
  color: #b7bbc3;
}
.confirm-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
.confirm-dialog__actions button {
  border: 1px solid #414650;
  border-radius: 0.3rem;
  background: #24272d;
  color: inherit;
  padding: 0.4rem 0.7rem;
}
.confirm-dialog__confirm {
  border-color: #9b7944 !important;
  background: #6d5129 !important;
}
</style>
