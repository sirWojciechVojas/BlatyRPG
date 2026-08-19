<template>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>{{ $t("admin.fields.campaign") }}</th>
          <th>{{ $t("admin.fields.system") }}</th>
          <th>{{ $t("admin.fields.gameMaster") }}</th>
          <th>{{ $t("admin.fields.members") }}</th>
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
          <td>
            <span :class="['admin-status', { inactive: !campaign.isActive }]">
              {{
                $t(
                  campaign.isActive
                    ? "admin.status.active"
                    : "admin.status.inactive",
                )
              }}
            </span>
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
};
</script>
