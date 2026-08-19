<template>
  <aside class="gm-tool-panel">
    <header class="gm-tool-panel__header">
      <div>
        <small>{{
          canManage ? $t("vtt.table.gm.kicker") : $t("vtt.table.player.kicker")
        }}</small>
        <h2>
          {{
            canManage
              ? $t("vtt.table.gm.title")
              : $t("vtt.scene.navigation.title")
          }}
        </h2>
      </div>
      <span v-if="canManage" class="scene-badge">MG</span>
    </header>

    <div v-if="canManage" class="gm-tool-panel__actions">
      <button
        type="button"
        class="scene-button scene-button--primary"
        :disabled="busy"
        @click="$emit('create')"
      >
        {{ $t("vtt.scene.actions.create") }}
      </button>
      <button
        type="button"
        class="scene-button"
        :disabled="busy || !selectedId"
        @click="$emit('edit')"
      >
        {{ $t("vtt.table.gm.map") }}
      </button>
      <button type="button" class="scene-button" @click="$emit('characters')">
        {{ $t("vtt.table.gm.tokens") }}
      </button>
    </div>

    <SceneNavigation
      :scenes="scenes"
      :selected-id="selectedId"
      :active-id="activeId"
      :can-manage="false"
      :busy="busy"
      :show-header="false"
      @select="$emit('select', $event)"
    />

    <section
      v-if="canManage"
      class="gm-tool-panel__future"
      aria-labelledby="future-tools-title"
    >
      <h3 id="future-tools-title">{{ $t("vtt.table.gm.future") }}</h3>
      <div>
        <button v-for="tool in futureTools" :key="tool" type="button" disabled>
          {{ $t(`vtt.table.gm.${tool}`) }}
        </button>
      </div>
    </section>
  </aside>
</template>

<script>
import SceneNavigation from "@/components/vtt/scene/SceneNavigation.vue";

export default {
  name: "GmToolPanel",
  components: { SceneNavigation },
  props: {
    scenes: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    activeId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "create", "edit", "characters"],
  data: () => ({ futureTools: ["walls", "lighting", "measurements"] }),
};
</script>
