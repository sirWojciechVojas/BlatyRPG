<template>
  <nav
    class="light-tool-toolbar"
    :aria-label="$t('vtt.light.toolbar')"
    @pointerdown.stop
  >
    <select
      :value="sourceType"
      :title="$t('vtt.light.sourceType')"
      :disabled="busy"
      @change="$emit('source-type', $event.target.value)"
    >
      <option v-for="type in types" :key="type" :value="type">
        {{ $t(`vtt.light.types.${type}`) }}
      </option>
    </select>
    <button type="button" :disabled="busy" @click="$emit('add')">
      ＋ {{ $t("vtt.light.add") }}
    </button>
    <button type="button" :disabled="busy || !light" @click="$emit('copy')">
      ⧉ {{ $t("vtt.light.copyShort") }}
    </button>
    <label class="light-tool-toolbar__lumens" :title="$t('vtt.light.lumens')">
      <input
        type="number"
        min="0"
        max="1000000"
        step="50"
        :value="light?.lumens ?? 800"
        :disabled="busy || !light"
        @change="$emit('update', { lumens: Number($event.target.value) })"
      />
      lm
    </label>
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
import { LIGHT_TYPES } from "@/lib/vtt/lightOptions";

export default {
  name: "LightToolToolbar",
  props: {
    light: { type: Object, default: null },
    sourceType: { type: String, default: "omni" },
    count: { type: Number, default: 0 },
    listOpen: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
  },
  emits: [
    "add",
    "copy",
    "update",
    "source-type",
    "edit",
    "toggle-list",
    "delete",
  ],
  data: () => ({ types: LIGHT_TYPES }),
};
</script>
