<template>
  <section
    class="scene-navigation"
    :aria-labelledby="showHeader ? 'scene-navigation-title' : undefined"
  >
    <header v-if="showHeader" class="scene-navigation__header">
      <h2 id="scene-navigation-title">
        {{ $t("vtt.scene.navigation.title") }}
      </h2>
      <button
        v-if="canManage"
        class="scene-button scene-button--primary"
        type="button"
        :disabled="busy"
        @click="$emit('create')"
      >
        {{ $t("vtt.scene.actions.create") }}
      </button>
    </header>

    <nav :aria-label="$t('vtt.scene.navigation.label')">
      <p v-if="!scenes.length" class="scene-navigation__empty">
        {{ $t("vtt.scene.navigation.empty") }}
      </p>
      <button
        v-for="scene in scenes"
        :key="scene.id"
        class="scene-navigation__item"
        :class="{ 'scene-navigation__item--selected': scene.id === selectedId }"
        type="button"
        :aria-current="scene.id === selectedId ? 'page' : undefined"
        :disabled="busy"
        @click="$emit('select', scene.id)"
      >
        <span class="scene-navigation__name">{{ scene.name }}</span>
        <span class="scene-navigation__badges">
          <span v-if="scene.id === activeId" class="scene-badge">
            {{ $t("vtt.scene.status.active") }}
          </span>
          <span
            v-if="!scene.isVisible && canManage"
            class="scene-badge scene-badge--muted"
          >
            {{ $t("vtt.scene.status.hidden") }}
          </span>
        </span>
      </button>
    </nav>
  </section>
</template>

<script>
export default {
  name: "SceneNavigation",
  props: {
    scenes: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    activeId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    showHeader: { type: Boolean, default: true },
  },
  emits: ["select", "create"],
};
</script>
