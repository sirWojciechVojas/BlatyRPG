<!-- Responsibility: CompactToolbar shop interface component. -->
<template>
  <div class="compact-toolbar" role="toolbar" :aria-label="label">
    <div class="compact-toolbar__main"><slot /></div>
    <div v-if="$slots.actions" class="compact-toolbar__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({ label: { type: String, default: "" } });
</script>

<style scoped>
.compact-toolbar {
  display: flex;
  min-height: 2.5rem;
  align-items: center;
  justify-content: space-between;
  gap: 0.55rem;
  padding: 0.35rem 0.5rem;
  border-bottom: 1px solid var(--shop-border, #343841);
  background: rgba(20, 22, 26, 0.96);
}
.compact-toolbar__main,
.compact-toolbar__actions {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.4rem;
}
.compact-toolbar__actions {
  justify-content: flex-end;
}
@media (max-width: 720px) {
  .compact-toolbar {
    align-items: stretch;
    flex-direction: column;
  }
  .compact-toolbar__main,
  .compact-toolbar__actions {
    flex-wrap: wrap;
  }
}
</style>
