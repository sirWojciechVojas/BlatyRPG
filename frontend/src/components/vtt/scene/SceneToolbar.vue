<template>
  <header class="scene-toolbar">
    <div class="scene-toolbar__identity">
      <h1>{{ scene?.name || $t("vtt.scene.workspace.title") }}</h1>
      <span v-if="isActive" class="scene-badge">
        {{ $t("vtt.scene.status.active") }}
      </span>
    </div>
    <div class="scene-toolbar__actions">
      <button
        type="button"
        class="scene-button"
        :disabled="!scene"
        @click="$emit('zoom-out')"
      >
        −
        <span class="visually-hidden">{{
          $t("vtt.scene.actions.zoomOut")
        }}</span>
      </button>
      <output class="scene-toolbar__zoom">{{ zoomPercent }}%</output>
      <button
        type="button"
        class="scene-button"
        :disabled="!scene"
        @click="$emit('zoom-in')"
      >
        +
        <span class="visually-hidden">{{
          $t("vtt.scene.actions.zoomIn")
        }}</span>
      </button>
      <button
        type="button"
        class="scene-button"
        :disabled="!scene"
        @click="$emit('fit')"
      >
        {{ $t("vtt.scene.actions.fit") }}
      </button>
      <button
        type="button"
        class="scene-button"
        :disabled="busy"
        @click="$emit('refresh')"
      >
        {{ $t("vtt.scene.actions.refresh") }}
      </button>
      <button
        v-if="canManage && scene && !isActive"
        type="button"
        class="scene-button scene-button--primary"
        :disabled="busy || !scene.isVisible"
        :title="
          !scene.isVisible ? $t('vtt.scene.actions.visibleRequired') : null
        "
        @click="$emit('activate')"
      >
        {{ $t("vtt.scene.actions.activate") }}
      </button>
      <button
        v-if="canManage && scene"
        type="button"
        class="scene-button"
        :disabled="busy"
        @click="$emit('settings')"
      >
        {{ $t("vtt.scene.actions.settings") }}
      </button>
    </div>
  </header>
</template>

<script>
export default {
  name: "SceneToolbar",
  props: {
    scene: { type: Object, default: null },
    isActive: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    zoomPercent: { type: Number, default: 100 },
  },
  emits: ["zoom-out", "zoom-in", "fit", "refresh", "activate", "settings"],
};
</script>
