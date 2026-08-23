<template>
  <section class="user-panel-content" aria-labelledby="account-overview-title">
    <div class="user-panel-section-heading">
      <div>
        <p>{{ $t("auth.userPanel.overview.eyebrow") }}</p>
        <h2 id="account-overview-title">
          {{ $t("auth.userPanel.overview.title") }}
        </h2>
      </div>
      <router-link :to="{ name: 'tables' }">{{
        $t("auth.userPanel.overview.openTables")
      }}</router-link>
    </div>
    <div class="user-panel-metrics" :aria-busy="loading">
      <article>
        <span>{{ $t("auth.userPanel.overview.tables") }}</span
        ><strong>{{ campaigns.length }}</strong>
      </article>
      <article>
        <span>{{ $t("auth.userPanel.overview.managed") }}</span
        ><strong>{{ managedCount }}</strong>
      </article>
      <article>
        <span>{{ $t("auth.userPanel.overview.invitations") }}</span
        ><strong>{{ pendingCount }}</strong>
      </article>
      <article>
        <span>{{ $t("auth.userPanel.overview.sessions") }}</span
        ><strong>{{ sessions.length }}</strong>
      </article>
    </div>
    <div class="user-panel-overview-grid">
      <article class="user-panel-card">
        <header>
          <h3>{{ $t("auth.userPanel.overview.recentTables") }}</h3>
        </header>
        <p v-if="loading">{{ $t("auth.userPanel.loading") }}</p>
        <p v-else-if="!recentCampaigns.length">
          {{ $t("auth.userPanel.overview.noTables") }}
        </p>
        <router-link
          v-for="campaign in recentCampaigns"
          :key="campaign.id"
          class="user-panel-campaign"
          :to="{ name: 'campaign-lobby', params: { campaignId: campaign.id } }"
        >
          <span
            ><strong>{{ campaign.name }}</strong
            ><small>{{ campaignRole(campaign) }}</small></span
          >
          <em :class="{ 'is-paused': !campaign.isActive }">{{
            campaignStatus(campaign)
          }}</em>
        </router-link>
      </article>
      <article class="user-panel-card user-panel-quick-links">
        <header>
          <h3>{{ $t("auth.userPanel.overview.quickActions") }}</h3>
        </header>
        <router-link :to="{ name: 'tables' }"
          ><span>＋</span
          >{{ $t("auth.userPanel.overview.createOrJoin") }}</router-link
        >
        <router-link :to="{ name: 'my-invitations' }"
          ><span>✉</span
          >{{ $t("auth.userPanel.overview.reviewInvitations") }}</router-link
        >
        <router-link :to="{ name: 'dice' }"
          ><span>◆</span
          >{{ $t("auth.userPanel.overview.openDice") }}</router-link
        >
      </article>
    </div>
  </section>
</template>

<script>
export default {
  name: "UserOverviewTab",
  props: {
    campaigns: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    sessions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
  },
  computed: {
    managedCount() {
      return this.campaigns.filter((item) => item.capabilities?.canManage)
        .length;
    },
    pendingCount() {
      return this.invitations.filter((item) => item.status === "pending")
        .length;
    },
    recentCampaigns() {
      return [...this.campaigns]
        .sort((a, b) =>
          String(b.lastActivityAt || b.updatedAt || "").localeCompare(
            String(a.lastActivityAt || a.updatedAt || ""),
          ),
        )
        .slice(0, 5);
    },
  },
  methods: {
    campaignRole(campaign) {
      const role = campaign.capabilities?.canManage ? "gm" : "player";
      return this.$t(`auth.userPanel.overview.roles.${role}`);
    },
    campaignStatus(campaign) {
      const status = campaign.isActive ? "active" : "paused";
      return this.$t(`auth.userPanel.overview.status.${status}`);
    },
  },
};
</script>
