<template>
  <main class="scene-workspace">
    <section
      v-if="!moduleReady || initialLoading"
      class="scene-workspace__state"
      role="status"
    >
      <h1>{{ $t("vtt.scene.workspace.title") }}</h1>
      <p>{{ $t("vtt.scene.workspace.loading") }}</p>
    </section>

    <section
      v-else-if="state.unauthorized"
      class="scene-workspace__state"
      role="alert"
    >
      <h1>{{ $t("vtt.scene.workspace.unauthorizedTitle") }}</h1>
      <p>{{ $t("vtt.scene.workspace.unauthorizedBody") }}</p>
    </section>

    <div
      v-else
      class="scene-workspace__layout"
      :class="{ 'scene-workspace__layout--settings': settingsOpen }"
    >
      <SceneNavigation
        :scenes="scenes"
        :selected-id="state.selectedSceneId"
        :active-id="state.activeSceneId"
        :can-manage="canManage"
        :busy="busy"
        @select="selectScene"
        @create="openCreate"
      />

      <section class="scene-workspace__main">
        <div v-if="state.error" class="scene-workspace__notice" role="alert">
          <span>{{ errorMessage }}</span>
          <button type="button" class="scene-button" @click="refresh">
            {{ $t("vtt.scene.actions.retry") }}
          </button>
        </div>
        <SceneToolbar
          :scene="selectedScene"
          :is-active="selectedScene?.id === state.activeSceneId"
          :can-manage="canManage"
          :busy="busy"
          :zoom-percent="zoomPercent"
          @zoom-out="zoomOut"
          @zoom-in="zoomIn"
          @fit="fitCanvas"
          @refresh="refresh"
          @activate="activate"
          @settings="openEdit"
        />
        <SceneCanvas
          :key="
            selectedScene
              ? `${selectedScene.id}:${selectedScene.revision}`
              : 'empty'
          "
          ref="canvas"
          :scene="selectedScene"
          @camera-change="zoomPercent = $event.zoomPercent"
        />
      </section>

      <SceneSettingsPanel
        v-if="settingsOpen && canManage"
        :scene="settingsMode === 'edit' ? selectedScene : null"
        :mode="settingsMode"
        :busy="busy"
        @save="saveSettings"
        @cancel="settingsOpen = false"
        @delete="deleteScene"
      />
    </div>
  </main>
</template>

<script>
import SceneCanvas from "@/components/vtt/scene/SceneCanvas.vue";
import SceneNavigation from "@/components/vtt/scene/SceneNavigation.vue";
import SceneSettingsPanel from "@/components/vtt/scene/SceneSettingsPanel.vue";
import SceneToolbar from "@/components/vtt/scene/SceneToolbar.vue";
import { ensureVttStoreModule } from "@/store/modules/loadVttModule";

const emptyState = () => ({
  scenes: [],
  selectedSceneId: null,
  activeSceneId: null,
  capabilities: { canManage: false, canViewHidden: false },
  phase: "idle",
  error: null,
  unauthorized: false,
});

export default {
  name: "SceneWorkspaceView",
  components: {
    SceneCanvas,
    SceneNavigation,
    SceneSettingsPanel,
    SceneToolbar,
  },
  data: () => ({
    moduleReady: false,
    settingsOpen: false,
    settingsMode: "edit",
    zoomPercent: 100,
  }),
  computed: {
    state() {
      return this.$store.state.vtt || emptyState();
    },
    scenes() {
      return this.$store.getters["vtt/sortedScenes"] || this.state.scenes;
    },
    selectedScene() {
      return this.$store.getters["vtt/selectedScene"] || null;
    },
    canManage() {
      return this.$store.getters["vtt/canManage"] === true;
    },
    busy() {
      return ["loading", "saving"].includes(this.state.phase);
    },
    initialLoading() {
      return this.state.phase === "loading" && !this.state.scenes.length;
    },
    errorMessage() {
      if (this.state.error?.network) {
        return this.$t("vtt.scene.errors.network");
      }
      if (this.state.error?.status === 409) {
        return this.$t("vtt.scene.errors.conflict");
      }
      const details = this.state.error?.details;
      if (this.state.error?.status === 422 && details) {
        const fields = Object.keys(details).map((field) => {
          const camel = field.replace(/_([a-z])/g, (_match, char) =>
            char.toUpperCase(),
          );
          const key = `vtt.scene.fields.${camel}`;
          const label = this.$t(key);
          return label === key ? field : label;
        });
        return this.$t("vtt.scene.errors.validation", {
          fields: fields.join(", "),
        });
      }
      return this.$t("vtt.scene.errors.generic");
    },
  },
  watch: {
    "$route.params.campaignId": "loadCampaign",
  },
  created() {
    this.loadCampaign();
  },
  methods: {
    zoomOut() {
      this.$refs.canvas?.zoomBy(1 / 1.2);
    },
    zoomIn() {
      this.$refs.canvas?.zoomBy(1.2);
    },
    fitCanvas() {
      this.$refs.canvas?.fit();
    },
    campaignId() {
      const raw = this.$route.params.campaignId;
      const numeric = Number(raw);
      return Number.isFinite(numeric) ? numeric : raw;
    },
    async loadCampaign() {
      await ensureVttStoreModule(this.$store);
      this.moduleReady = true;
      this.$store.commit("vtt/SET_CAMPAIGN", this.campaignId());
      await this.$store.dispatch("vtt/initialize").catch(() => {});
    },
    selectScene(sceneId) {
      this.settingsOpen = false;
      this.$store.dispatch("vtt/selectScene", sceneId).catch(() => {});
    },
    refresh() {
      this.$store.dispatch("vtt/initialize").catch(() => {});
    },
    openCreate() {
      this.settingsMode = "create";
      this.settingsOpen = true;
    },
    openEdit() {
      this.settingsMode = "edit";
      this.settingsOpen = true;
    },
    async saveSettings(payload) {
      const action =
        this.settingsMode === "create"
          ? "vtt/createScene"
          : "vtt/updateSelectedScene";
      try {
        await this.$store.dispatch(action, payload);
        this.settingsOpen = false;
      } catch (_error) {
        // Store exposes the API error in a visible workspace notice.
      }
    },
    async deleteScene() {
      const message = this.$t("vtt.scene.actions.deleteConfirm");
      if (typeof window !== "undefined" && !window.confirm(message)) return;
      try {
        await this.$store.dispatch("vtt/deleteSelectedScene");
        this.settingsOpen = false;
      } catch (_error) {
        // Store exposes the API error in a visible workspace notice.
      }
    },
    activate() {
      this.$store.dispatch("vtt/activateSelectedScene").catch(() => {});
    },
  },
};
</script>

<style src="@/components/vtt/scene/scene-workspace.css"></style>
