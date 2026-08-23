<template>
  <div class="admin-tab-grid admin-campaigns-layout">
    <section class="admin-panel admin-panel--table">
      <header class="admin-section-heading">
        <div>
          <h2>{{ $t("admin.campaigns.title") }}</h2>
          <p>{{ $t("admin.campaigns.description") }}</p>
        </div>
        <div class="admin-filters">
          <input
            v-model.trim="query"
            type="search"
            :placeholder="$t('admin.actions.searchCampaigns')"
          />
          <select v-model="status" :aria-label="$t('admin.fields.status')">
            <option value="">{{ $t("admin.status.all") }}</option>
            <option value="active">{{ $t("admin.status.active") }}</option>
            <option value="paused">{{ $t("admin.status.paused") }}</option>
            <option value="archived">{{ $t("admin.status.archived") }}</option>
          </select>
        </div>
      </header>
      <AdminCampaignTable :campaigns="filteredCampaigns" />
    </section>
    <section class="admin-panel admin-chart-card">
      <header>
        <h2>{{ $t("admin.charts.membershipRoles") }}</h2>
      </header>
      <AdminBarChart
        :items="analytics.membershipRoles"
        :title="$t('admin.charts.membershipRoles')"
        label-prefix="admin.campaignRoles"
      />
    </section>
  </div>
</template>

<script>
import AdminBarChart from "./AdminBarChart.vue";
import AdminCampaignTable from "./AdminCampaignTable.vue";

export default {
  name: "AdminCampaignsTab",
  components: { AdminBarChart, AdminCampaignTable },
  props: {
    campaigns: { type: Array, required: true },
    analytics: { type: Object, required: true },
  },
  data: () => ({ query: "", status: "" }),
  computed: {
    filteredCampaigns() {
      const query = this.query.toLocaleLowerCase();
      return this.campaigns.filter((campaign) => {
        const matchesStatus = !this.status || campaign.status === this.status;
        const matchesQuery =
          !query ||
          [campaign.name, campaign.systemType, campaign.gameMasterName].some(
            (value) => String(value).toLocaleLowerCase().includes(query),
          );
        return matchesStatus && matchesQuery;
      });
    },
  },
};
</script>
