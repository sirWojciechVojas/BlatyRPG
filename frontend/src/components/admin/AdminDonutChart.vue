<template>
  <div class="admin-donut-chart" role="img" :aria-label="title">
    <div class="admin-donut" :style="{ background: gradient }">
      <span
        ><strong>{{ total }}</strong
        >{{ $t("admin.charts.total") }}</span
      >
    </div>
    <ul>
      <li v-for="(item, index) in items" :key="item.key">
        <i :style="{ background: colors[index % colors.length] }"></i>
        <span>{{ label(item.key) }}</span
        ><strong>{{ item.value }}</strong>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: "AdminDonutChart",
  props: {
    items: { type: Array, default: () => [] },
    title: { type: String, default: "" },
    labelPrefix: { type: String, default: "" },
  },
  data: () => ({ colors: ["#d8b778", "#8f6338", "#6f8d68", "#b8785d"] }),
  computed: {
    total() {
      return this.items.reduce((sum, item) => sum + Number(item.value || 0), 0);
    },
    gradient() {
      if (!this.total) return "var(--ui-color-surface-raised)";
      let cursor = 0;
      const stops = this.items.map((item, index) => {
        const start = cursor;
        cursor += (Number(item.value || 0) / this.total) * 100;
        return `${this.colors[index % this.colors.length]} ${start}% ${cursor}%`;
      });
      return `conic-gradient(${stops.join(",")})`;
    },
  },
  methods: {
    label(key) {
      const translationKey = this.labelPrefix
        ? `${this.labelPrefix}.${key}`
        : "";
      return translationKey && this.$te(translationKey)
        ? this.$t(translationKey)
        : key;
    },
  },
};
</script>

<style scoped>
.admin-donut-chart {
  display: grid;
  grid-template-columns: 6.5rem 1fr;
  gap: 1rem;
  align-items: center;
  height: 100%;
  min-height: 7rem;
}
.admin-donut {
  display: grid;
  width: 6.5rem;
  aspect-ratio: 1;
  place-items: center;
  border-radius: 50%;
}
.admin-donut::before {
  content: "";
  grid-area: 1/1;
  width: 66%;
  aspect-ratio: 1;
  border-radius: 50%;
  background: var(--ui-color-surface);
}
.admin-donut span {
  z-index: 1;
  grid-area: 1/1;
  color: var(--ui-color-text-faint);
  font-size: var(--ui-font-size-xs);
  text-align: center;
}
.admin-donut strong {
  display: block;
  color: var(--ui-color-text);
  font-size: 1.25rem;
}
.admin-donut-chart ul {
  display: grid;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
.admin-donut-chart li {
  display: grid;
  grid-template-columns: 0.5rem 1fr auto;
  gap: 0.45rem;
  align-items: center;
  color: var(--ui-color-text-muted);
  font-size: var(--ui-font-size-sm);
}
.admin-donut-chart li i {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
}
.admin-donut-chart li strong {
  color: var(--ui-color-text);
}
</style>
