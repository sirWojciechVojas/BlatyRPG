<template>
  <main class="compendium-page">
    <CompendiumWorkspace
      :campaign-id="campaignId"
      :universe-id="universeId"
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
