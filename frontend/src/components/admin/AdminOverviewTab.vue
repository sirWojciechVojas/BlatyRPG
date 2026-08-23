<template>
  <div class="admin-tab-grid admin-overview">
    <section class="admin-kpis" :aria-label="$t('admin.metrics.title')">
      <article v-for="metric in metricCards" :key="metric.key">
        <span>{{ $t(`admin.metrics.${metric.key}`) }}</span>
        <strong>{{ metric.value }}</strong>
        <small>{{ $t(`admin.metricsHints.${metric.key}`) }}</small>
      </article>
    </section>

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
    <section class="admin-panel admin-chart-card">
      <header>
        <h2>{{ $t("admin.charts.accounts") }}</h2>
      </header>
      <AdminDonutChart
        :items="analytics.accountRoles"
        :title="$t('admin.charts.accounts')"
        label-prefix="admin.roles"
      />
    </section>
    <section class="admin-panel admin-chart-card">
      <header>
        <h2>{{ $t("admin.charts.statuses") }}</h2>
      </header>
      <AdminBarChart
        :items="analytics.campaignStatuses"
        :title="$t('admin.charts.statuses')"
        label-prefix="admin.status"
      />
    </section>
    <section class="admin-panel admin-chart-card">
      <header>
        <h2>{{ $t("admin.charts.systems") }}</h2>
      </header>
      <AdminBarChart
        :items="analytics.systems"
        :title="$t('admin.charts.systems')"
      />
    </section>
    <section class="admin-panel admin-quick-card">
      <header>
        <h2>{{ $t("admin.quick.title") }}</h2>
      </header>
      <button type="button" @click="$emit('navigate', 'users')">
        <span>＋</span>{{ $t("admin.quick.addUser") }}
      </button>
      <button type="button" @click="$emit('navigate', 'campaigns')">
        <span>⌕</span>{{ $t("admin.quick.reviewTables") }}
      </button>
      <router-link :to="{ name: 'landing' }">
        <span>⌂</span>{{ $t("admin.quick.openLanding") }}
      </router-link>
    </section>
  </div>
</template>

<script>
import AdminBarChart from "./AdminBarChart.vue";
import AdminDonutChart from "./AdminDonutChart.vue";
import AdminLineChart from "./AdminLineChart.vue";

export default {
  name: "AdminOverviewTab",
  components: { AdminBarChart, AdminDonutChart, AdminLineChart },
  props: {
    metrics: { type: Object, required: true },
    analytics: { type: Object, required: true },
  },
  emits: ["navigate"],
  computed: {
    metricCards() {
      return [
        { key: "users", value: this.metrics.users },
        { key: "activeSessions", value: this.metrics.activeSessions },
        { key: "campaigns", value: this.metrics.campaigns },
        { key: "activeCampaigns", value: this.metrics.activeCampaigns },
        { key: "memberships", value: this.metrics.memberships },
        { key: "admins", value: this.metrics.admins },
      ];
    },
  },
};
</script>
