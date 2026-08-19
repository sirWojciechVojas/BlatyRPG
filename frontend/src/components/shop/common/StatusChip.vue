<!-- Responsibility: StatusChip shop interface component. -->
<template>
  <span class="status-chip" :class="`status-chip--${tone}`">
    <span v-if="dot" class="status-chip__dot" aria-hidden="true" />
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup>
defineProps({
  label: { type: String, default: "" },
  tone: { type: String, default: "neutral" },
  dot: { type: Boolean, default: false },
});
</script>

<style scoped>
.status-chip {
  display: inline-flex;
  min-height: 1.55rem;
  align-items: center;
  gap: 0.3rem;
  border: 1px solid var(--shop-border, #3a3e47);
  border-radius: 999px;
  background: #1c1f24;
  color: #d9dde4;
  padding: 0.12rem 0.48rem;
  font-size: 0.72rem;
  line-height: 1;
  white-space: nowrap;
}
.status-chip__dot {
  width: 0.42rem;
  height: 0.42rem;
  border-radius: 50%;
  background: currentColor;
}
.status-chip--success {
  border-color: #3f6f57;
  color: #8ed5aa;
}
.status-chip--warning {
  border-color: #7a6238;
  color: #e3bb71;
}
.status-chip--danger {
  border-color: #764343;
  color: #ef9292;
}
.status-chip--info {
  border-color: #3c6378;
  color: #8fc8e3;
}
</style>
