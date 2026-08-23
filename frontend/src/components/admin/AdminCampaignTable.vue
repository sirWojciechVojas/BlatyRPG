<template>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>{{ $t("admin.fields.campaign") }}</th>
          <th>{{ $t("admin.fields.system") }}</th>
          <th>{{ $t("admin.fields.gameMaster") }}</th>
          <th>{{ $t("admin.fields.members") }}</th>
          <th>{{ $t("admin.fields.lastActivity") }}</th>
          <th>{{ $t("admin.fields.status") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="campaign in campaigns" :key="campaign.id">
          <td>
            <router-link
              :to="{
                name: 'scene-workspace',
                params: { campaignId: campaign.id },
              }"
            >
              {{ campaign.name }}
            </router-link>
          </td>
          <td>{{ campaign.systemType }}</td>
          <td>{{ campaign.gameMasterName || `#${campaign.gameMasterId}` }}</td>
          <td>{{ campaign.memberCount }}</td>
          <td>{{ formatDate(campaign.lastActivityAt) }}</td>
          <td>
            <span
              :class="[
                'admin-status',
                { inactive: campaign.status !== 'active' },
              ]"
            >
              {{ statusLabel(campaign.status) }}
            </span>
          </td>
        </tr>
        <tr v-if="!campaigns.length">
          <td colspan="6" class="admin-table-empty">
            {{ $t("admin.campaigns.empty") }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
export default {
  name: "AdminCampaignTable",
  props: { campaigns: { type: Array, required: true } },
  methods: {
    formatDate(value) {
      if (!value) return "—";
      return new Intl.DateTimeFormat(this.$i18n.locale, {
        dateStyle: "short",
      }).format(new Date(value));
    },
    statusLabel(status) {
      const key = `admin.status.${status}`;
      return this.$te(key) ? this.$t(key) : status;
    },
  },
};
</script>
