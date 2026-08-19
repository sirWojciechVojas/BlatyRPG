<template>
  <aside class="table-utility-drawer" :aria-labelledby="headingId">
    <header class="table-utility-drawer__header">
      <div>
        <small>{{ $t("vtt.table.drawer.kicker") }}</small>
        <h2 :id="headingId">{{ title }}</h2>
      </div>
      <button
        type="button"
        :aria-label="$t('vtt.table.drawer.close')"
        @click="$emit('close')"
      >
        ×
      </button>
    </header>
    <div class="table-utility-drawer__body">
      <slot />
    </div>
  </aside>
</template>

<script>
export default {
  name: "TableUtilityDrawer",
  props: {
    title: { type: String, required: true },
    panelId: { type: String, required: true },
  },
  emits: ["close"],
  computed: {
    headingId() {
      return `table-drawer-${this.panelId}`;
    },
  },
};
</script>
