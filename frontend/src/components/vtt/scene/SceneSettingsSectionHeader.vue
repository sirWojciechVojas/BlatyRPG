<template>
  <header class="scene-settings__section-header">
    <div>
      <h3>{{ title }}</h3>
      <p>{{ description }}</p>
    </div>
    <div v-if="$slots.default" class="scene-settings__section-actions">
      <slot />
    </div>
  </header>
</template>

<script>
export default {
  name: "SceneSettingsSectionHeader",
  props: {
    title: { type: String, required: true },
    description: { type: String, required: true },
  },
};
</script>
