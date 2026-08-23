<template>
  <div class="admin-line-chart" role="img" :aria-label="title">
    <svg viewBox="0 0 420 120" preserveAspectRatio="none" aria-hidden="true">
      <path
        v-for="y in [20, 55, 90]"
        :key="y"
        :d="`M8 ${y} H412`"
        class="grid"
      />
      <polyline :points="seriesPoints('users')" class="users" />
      <polyline :points="seriesPoints('campaigns')" class="campaigns" />
    </svg>
    <div class="admin-line-chart__labels">
      <span v-for="point in points" :key="point.label">{{ point.label }}</span>
    </div>
    <div class="admin-line-chart__legend">
      <span class="users">{{ $t("admin.metrics.users") }}</span>
      <span class="campaigns">{{ $t("admin.metrics.campaigns") }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: "AdminLineChart",
  props: {
    points: { type: Array, default: () => [] },
    title: { type: String, default: "" },
  },
  computed: {
    maximum() {
      return Math.max(
        1,
        ...this.points.flatMap((item) => [item.users, item.campaigns]),
      );
    },
  },
  methods: {
    seriesPoints(key) {
      const last = Math.max(1, this.points.length - 1);
      return this.points
        .map((point, index) => {
          const x = 8 + (index / last) * 404;
          const y = 102 - (Number(point[key] || 0) / this.maximum) * 88;
          return `${x},${y}`;
        })
        .join(" ");
    },
  },
};
</script>

<style scoped>
.admin-line-chart {
  position: relative;
  min-height: 9rem;
}
.admin-line-chart svg {
  display: block;
  width: 100%;
  height: 7.3rem;
  overflow: visible;
}
.admin-line-chart path.grid {
  fill: none;
  stroke: rgba(216, 183, 120, 0.12);
  stroke-width: 1;
}
.admin-line-chart polyline {
  fill: none;
  stroke-width: 2.5;
  vector-effect: non-scaling-stroke;
}
.admin-line-chart polyline.users {
  stroke: var(--ui-color-accent);
}
.admin-line-chart polyline.campaigns {
  stroke: #7f9d78;
}
.admin-line-chart__labels {
  display: flex;
  justify-content: space-between;
  color: var(--ui-color-text-faint);
  font-size: 0.62rem;
}
.admin-line-chart__legend {
  position: absolute;
  top: 0;
  right: 0;
  display: flex;
  gap: 0.75rem;
  font-size: var(--ui-font-size-xs);
}
.admin-line-chart__legend span::before {
  content: "";
  display: inline-block;
  width: 0.45rem;
  height: 0.45rem;
  margin-right: 0.3rem;
  border-radius: 50%;
  background: currentColor;
}
.admin-line-chart__legend .users {
  color: var(--ui-color-accent);
}
.admin-line-chart__legend .campaigns {
  color: #8fae86;
}
</style>
