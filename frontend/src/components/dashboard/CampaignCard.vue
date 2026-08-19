<template>
  <article class="campaign-card" :class="{ inactive: !campaign.isActive }">
    <header>
      <div>
        <span class="campaign-role">{{ roleLabel }}</span>
        <h3>{{ campaign.name }}</h3>
      </div>
      <span class="campaign-status">
        {{
          campaign.isActive
            ? $t("dashboard.campaign.active")
            : $t("dashboard.campaign.inactive")
        }}
      </span>
    </header>
    <p class="campaign-system">{{ campaign.systemType }}</p>
    <p class="campaign-description">
      {{ campaign.description || $t("dashboard.campaign.noDescription") }}
    </p>
    <div class="campaign-actions">
      <router-link
        class="primary-action"
        :to="{
          name: 'campaign-lobby',
          params: { campaignId: campaign.id },
        }"
      >
        {{ $t("campaignLobby.actions.openLobby") }}
      </router-link>
      <router-link
        class="secondary-action"
        :to="{
          name: 'character-workspace',
          params: { campaignId: campaign.id },
        }"
      >
        {{ $t("dashboard.campaign.openCharacters") }}
      </router-link>
      <router-link
        class="secondary-action"
        :to="{
          name: 'scene-workspace',
          params: { campaignId: campaign.id },
          hash: '#campaign-chat',
        }"
      >
        {{ $t("dashboard.campaign.openChat") }}
      </router-link>
      <router-link
        v-if="campaign.capabilities.canOpenShop"
        class="secondary-action"
        :to="{ name: 'shop-gm', params: { campaignId: campaign.id } }"
      >
        {{ $t("dashboard.campaign.openShop") }}
      </router-link>
    </div>
  </article>
</template>

<script>
export default {
  name: "CampaignCard",
  props: {
    campaign: { type: Object, required: true },
  },
  computed: {
    roleLabel() {
      const key = `dashboard.roles.${this.campaign.membershipRole}`;
      return this.$te(key) ? this.$t(key) : this.campaign.membershipRole;
    },
  },
};
</script>
