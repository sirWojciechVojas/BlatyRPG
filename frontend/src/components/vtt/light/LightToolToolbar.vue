<template>
  <nav
    class="light-tool-toolbar"
    :aria-label="$t('vtt.light.toolbar')"
    @pointerdown.stop
  >
    <button type="button" :disabled="busy" @click="$emit('add')">
      ＋ {{ $t("vtt.light.add") }}
    </button>
    <button type="button" :disabled="busy || !light" @click="$emit('copy')">
      ⧉ {{ $t("vtt.light.copyShort") }}
    </button>
    <select
      :value="light?.sourceType || 'light'"
      :title="$t('vtt.light.sourceType')"
      :disabled="busy || !light"
      @change="$emit('update', { sourceType: $event.target.value })"
    >
      <option value="light">{{ $t("vtt.light.types.light") }}</option>
      <option value="darkness">{{ $t("vtt.light.types.darkness") }}</option>
    </select>
    <input
      type="color"
      :value="light?.color?.slice(0, 7) || '#FFD27A'"
      :title="$t('vtt.light.color')"
      :disabled="busy || !light"
      @change="$emit('update', { color: $event.target.value })"
    />
    <button type="button" :disabled="busy || !light" @click="$emit('edit')">
      ⚙ {{ $t("vtt.light.editShort") }}
    </button>
    <button
      type="button"
      :class="{ active: listOpen }"
      @click="$emit('toggle-list')"
    >
      ☷ {{ $t("vtt.light.list") }} ({{ count }})
    </button>
    <button
      type="button"
      class="light-tool-toolbar__danger"
      :disabled="busy || !light"
      @click="$emit('delete')"
    >
      × {{ $t("vtt.light.deleteShort") }}
    </button>
  </nav>
</template>

<script>
export default {
  name: "LightToolToolbar",
  props: {
    light: { type: Object, default: null },
    count: { type: Number, default: 0 },
    listOpen: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["add", "copy", "update", "edit", "toggle-list", "delete"],
};
</script>
