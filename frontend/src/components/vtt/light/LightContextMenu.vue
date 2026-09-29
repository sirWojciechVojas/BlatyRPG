<template>
  <aside class="light-context-menu" @pointerdown.stop @contextmenu.prevent>
    <header>
      <strong>{{ $t("vtt.light.contextMenu") }}</strong>
      <button type="button" @click="$emit('close')">×</button>
    </header>
    <select
      :value="sourceType"
      :disabled="busy"
      @change="$emit('source-type', $event.target.value)"
    >
      <option v-for="type in types" :key="type" :value="type">
        {{ $t(`vtt.light.types.${type}`) }}
      </option>
    </select>
    <button type="button" :disabled="busy" @click="$emit('add')">
      ＋ {{ $t("vtt.light.addHere") }}
    </button>
    <button type="button" @click="$emit('toggle-list')">
      ☷ {{ $t("vtt.light.list") }}
    </button>
    <template v-if="light">
      <hr />
      <button
        type="button"
        :disabled="busy"
        @click="$emit('update', { enabled: !light.enabled })"
      >
        {{ light.enabled ? "OFF" : "ON" }}
      </button>
      <button type="button" :disabled="busy" @click="$emit('edit')">
        ⚙ {{ $t("vtt.light.editShort") }}
      </button>
      <button type="button" :disabled="busy" @click="$emit('copy')">
        ⧉ {{ $t("vtt.light.copyShort") }}
      </button>
      <button
        class="light-context-menu__danger"
        type="button"
        :disabled="busy"
        @click="$emit('delete')"
      >
        × {{ $t("vtt.light.deleteShort") }}
      </button>
    </template>
  </aside>
</template>

<script>
import { LIGHT_TYPES } from "@/lib/vtt/lightOptions";

export default {
  name: "LightContextMenu",
  props: {
    light: { type: Object, default: null },
    sourceType: { type: String, default: "omni" },
    busy: { type: Boolean, default: false },
  },
  emits: [
    "source-type",
    "add",
    "toggle-list",
    "update",
    "edit",
    "copy",
    "delete",
    "close",
  ],
  data: () => ({ types: LIGHT_TYPES }),
};
</script>
