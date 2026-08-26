<template>
  <aside class="light-management" @pointerdown.stop>
    <header>
      <strong>{{ $t("vtt.light.list") }}</strong>
      <button type="button" :disabled="busy" @click="$emit('add')">
        ＋ {{ $t("vtt.light.add") }}
      </button>
    </header>
    <p v-if="!lights.length">{{ $t("vtt.light.empty") }}</p>
    <ol v-else>
      <li
        v-for="(light, index) in lights"
        :key="light.id"
        :class="{ selected: light.id === selectedId }"
      >
        <button
          type="button"
          class="light-management__select"
          @click="$emit('select', light.id)"
        >
          <i :style="{ backgroundColor: light.color }" />
          <span>#{{ index + 1 }}</span>
        </button>
        <select
          :value="light.sourceType"
          :title="$t('vtt.light.sourceType')"
          :disabled="busy"
          @change="update(light, { sourceType: $event.target.value })"
        >
          <option value="light">{{ $t("vtt.light.types.light") }}</option>
          <option value="darkness">{{ $t("vtt.light.types.darkness") }}</option>
        </select>
        <input
          type="color"
          :value="light.color.slice(0, 7)"
          :title="$t('vtt.light.color')"
          :disabled="busy"
          @change="update(light, { color: $event.target.value })"
        />
        <label>
          X
          <input
            type="number"
            :value="light.x"
            :disabled="busy"
            @change="coordinate(light, 'x', $event.target.value)"
          />
        </label>
        <label>
          Y
          <input
            type="number"
            :value="light.y"
            :disabled="busy"
            @change="coordinate(light, 'y', $event.target.value)"
          />
        </label>
        <button
          type="button"
          :title="$t('vtt.light.edit')"
          :disabled="busy"
          @click="$emit('edit', light.id)"
        >
          ⚙
        </button>
        <button
          type="button"
          class="light-management__danger"
          :title="$t('vtt.light.delete')"
          :disabled="busy"
          @click="$emit('delete', light)"
        >
          ×
        </button>
      </li>
    </ol>
  </aside>
</template>

<script>
export default {
  name: "LightManagementPanel",
  props: {
    lights: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "add", "update", "edit", "delete"],
  methods: {
    update(light, changes) {
      this.$emit("update", { light, changes });
    },
    coordinate(light, field, value) {
      const number = Number(value);
      if (Number.isFinite(number)) this.update(light, { [field]: number });
    },
  },
};
</script>
