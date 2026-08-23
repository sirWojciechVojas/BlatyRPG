<template>
  <div class="admin-bars" role="img" :aria-label="title">
    <div v-for="item in visibleItems" :key="item.key" class="admin-bars__row">
      <span :title="label(item.key)">{{ label(item.key) }}</span>
      <div class="admin-bars__track">
        <i :style="{ width: `${percentage(item.value)}%` }"></i>
      </div>
      <strong>{{ item.value }}</strong>
    </div>
    <p v-if="!visibleItems.length" class="admin-chart-empty">
      {{ $t("admin.charts.noData") }}
    </p>
  </div>
</template>

<script>
export default {
  name: "AdminBarChart",
  props: {
    items: { type: Array, default: () => [] },
    title: { type: String, default: "" },
    labelPrefix: { type: String, default: "" },
  },
  computed: {
    visibleItems() {
      return this.items.filter((item) => Number(item.value) > 0);
    },
    maximum() {
      return Math.max(
        1,
        ...this.visibleItems.map((item) => Number(item.value)),
      );
    },
  },
  methods: {
    percentage(value) {
      return Math.max(3, (Number(value) / this.maximum) * 100);
    },
    label(key) {
      const translationKey = this.labelPrefix
        ? `${this.labelPrefix}.${key}`
        : "";
      return translationKey && this.$te(translationKey)
        ? this.$t(translationKey)
        : key || "—";
    },
  },
};
</script>

<style scoped>
.admin-bars {
  display: grid;
  gap: 0.55rem;
  min-height: 6rem;
  align-content: center;
}
.admin-bars__row {
  display: grid;
  grid-template-columns: minmax(5.5rem, 8rem) 1fr 2rem;
  gap: 0.55rem;
  align-items: center;
  font-size: var(--ui-font-size-sm);
}
.admin-bars__row > span {
  overflow: hidden;
  color: var(--ui-color-text-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.admin-bars__track {
  height: 0.38rem;
  overflow: hidden;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.07);
}
.admin-bars__track i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(
    90deg,
    var(--ui-color-accent-strong),
    var(--ui-color-accent)
  );
}
.admin-bars__row strong {
  color: var(--ui-color-text);
  text-align: right;
}
.admin-chart-empty {
  margin: 0;
  color: var(--ui-color-text-faint);
  text-align: center;
}
</style>
