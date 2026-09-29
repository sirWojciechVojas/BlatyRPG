<template>
  <section class="scene-manager-panel">
    <div v-if="canManage" class="scene-manager-panel__actions">
      <button
        type="button"
        class="scene-button"
        :disabled="busy"
        @click="$emit('create')"
      >
        + {{ $t("vtt.scene.actions.create") }}
      </button>
      <button
        type="button"
        class="scene-button"
        :disabled="busy || !scene"
        @click="$emit('duplicate')"
      >
        ⧉ {{ $t("vtt.scene.actions.duplicate") }}
      </button>
      <button
        type="button"
        class="scene-button"
        :disabled="busy || !scene"
        @click="$emit('edit')"
      >
        ⚙ {{ $t("vtt.scene.actions.settings") }}
      </button>
    </div>

    <SceneNavigation
      :scenes="scenes"
      :selected-id="selectedId"
      :active-id="activeId"
      :can-manage="canManage"
      :busy="busy"
      :show-header="false"
      @select="$emit('select', $event)"
    />

    <footer v-if="scene" class="scene-manager-panel__details">
      <dl>
        <div>
          <dt>{{ $t("vtt.scene.fields.dimensions") }}</dt>
          <dd>{{ scene.width }} × {{ scene.height }}</dd>
        </div>
        <div>
          <dt>{{ $t("vtt.scene.settings.grid") }}</dt>
          <dd>{{ $t(`vtt.scene.grid.${scene.gridType}`) }}</dd>
        </div>
        <div>
          <dt>{{ $t("vtt.scene.fields.gridDistance") }}</dt>
          <dd>{{ scene.gridDistance }} {{ scene.gridUnit }}</dd>
        </div>
      </dl>
      <div class="scene-manager-panel__footer-actions">
        <button
          v-if="scene.backgroundUrl"
          type="button"
          class="scene-button"
          :disabled="preloadStatus === 'loading'"
          @click="preload"
        >
          {{ preloadLabel }}
        </button>
        <button
          v-if="canManage && scene.id !== activeId"
          type="button"
          class="scene-button scene-button--primary"
          :disabled="busy || !scene.isVisible"
          @click="$emit('activate')"
        >
          {{ $t("vtt.scene.actions.activate") }}
        </button>
        <button
          v-if="canManage"
          type="button"
          class="scene-button scene-button--danger"
          :disabled="busy"
          @click="$emit('delete')"
        >
          {{ $t("vtt.scene.actions.delete") }}
        </button>
      </div>
    </footer>
  </section>
</template>

<script>
import SceneNavigation from "./SceneNavigation.vue";

const preloadedBackgrounds = new Set();

export default {
  name: "SceneManagerPanel",
  components: { SceneNavigation },
  props: {
    scene: { type: Object, default: null },
    scenes: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    activeId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "create", "duplicate", "edit", "delete", "activate"],
  data: () => ({ preloadStatus: "idle" }),
  computed: {
    preloadLabel() {
      const status = preloadedBackgrounds.has(this.scene?.backgroundUrl)
        ? "ready"
        : this.preloadStatus;
      return this.$t(`vtt.scene.preload.${status}`);
    },
  },
  watch: {
    "scene.backgroundUrl"() {
      this.preloadStatus = "idle";
    },
  },
  methods: {
    preload() {
      const url = this.scene?.backgroundUrl;
      if (!url || preloadedBackgrounds.has(url)) return;
      this.preloadStatus = "loading";
      const image = new Image();
      image.onload = () => {
        preloadedBackgrounds.add(url);
        this.preloadStatus = "ready";
      };
      image.onerror = () => {
        this.preloadStatus = "error";
      };
      image.src = url;
    },
  },
};
</script>
