<template>
  <main class="compendium-page">
    <CompendiumWorkspace
      :campaign-id="campaignId"
      :universe-id="universeId"
      :can-see-gm-hint="canSeeGmHint"
      @materialized="handleMaterialized"
    />
  </main>
</template>

<script>
import CompendiumWorkspace from "@/components/vtt/compendium/CompendiumWorkspace.vue";

export default {
  name: "CompendiumView",
  components: { CompendiumWorkspace },
  computed: {
    campaignId() {
      return this.$route.params.campaignId || null;
    },
    universeId() {
      return this.$route.params.universeId || null;
    },
    canSeeGmHint() {
      if (this.universeId) return true;
      const context = this.$store.state.campaignContext || {};
      if (Number(context.campaignId) !== Number(this.campaignId)) return false;
      const role = String(
        context.currentCampaign?.campaignRole || "",
      ).toLowerCase();
      return (
        context.capabilities?.canManage === true ||
        role === "gm" ||
        role === "game_master" ||
        role === "assistant"
      );
    },
  },
  methods: {
    handleMaterialized() {
      // Campaign context refreshes on the next table entry; the imported NPC is an independent copy.
    },
  },
};
</script>

<style scoped>
.compendium-page {
  height: calc(100vh - var(--app-header-height, 0px));
  min-height: 560px;
}
</style>
