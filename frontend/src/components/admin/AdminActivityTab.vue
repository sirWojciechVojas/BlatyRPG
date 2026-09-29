<template>
  <div class="admin-tab-grid admin-activity-layout">
    <section class="admin-panel admin-chart-card admin-chart-card--wide">
      <header>
        <h2>{{ $t("admin.charts.growth") }}</h2>
        <span>8 {{ $t("admin.charts.weeks") }}</span>
      </header>
      <AdminLineChart
        :points="analytics.growth"
        :title="$t('admin.charts.growth')"
      />
    </section>
    <section class="admin-panel admin-activity-feed">
      <header>
        <h2>{{ $t("admin.activity.title") }}</h2>
        <span>{{ activity.length }}</span>
      </header>
      <ol>
        <li
          v-for="item in activity"
          :key="`${item.type}-${item.label}-${item.occurredAt}`"
        >
          <i :class="item.type"></i>
          <span
            ><strong>{{ item.label }}</strong
            >{{ $t(`admin.activity.${item.type}`) }}</span
          >
          <time :datetime="item.occurredAt">{{
            formatDate(item.occurredAt)
          }}</time>
        </li>
      </ol>
      <p v-if="!activity.length" class="admin-chart-empty">
        {{ $t("admin.charts.noData") }}
      </p>
    </section>
    <section class="admin-panel admin-chart-card">
      <header>
        <h2>{{ $t("admin.charts.membershipRoles") }}</h2>
      </header>
      <AdminDonutChart
        :items="analytics.membershipRoles"
        :title="$t('admin.charts.membershipRoles')"
        label-prefix="admin.campaignRoles"
      />
    </section>
  </div>
</template>

<script>
import AdminDonutChart from "./AdminDonutChart.vue";
import AdminLineChart from "./AdminLineChart.vue";

export default {
  name: "AdminActivityTab",
  components: { AdminDonutChart, AdminLineChart },
  props: {
    activity: { type: Array, required: true },
    analytics: { type: Object, required: true },
  },
  methods: {
    formatDate(value) {
      if (!value) return "—";
      return new Intl.DateTimeFormat(this.$i18n.locale, {
        dateStyle: "short",
        timeStyle: "short",
      }).format(new Date(value));
    },
  },
};
</script>
