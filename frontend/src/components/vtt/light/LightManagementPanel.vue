<template>
  <aside class="light-management" @pointerdown.stop>
    <header>
      <strong>{{ $t("vtt.light.list") }}</strong>
      <button type="button" :disabled="busy" @click="$emit('add')">
        ＋ {{ $t("vtt.light.add") }}
      </button>
    </header>
    <div class="light-management__head" aria-hidden="true">
      <span>{{ $t("vtt.light.name") }}</span>
      <span>{{ $t("vtt.light.sourceType") }}</span>
      <span>{{ $t("vtt.light.lumensShort") }}</span>
      <span>{{ $t("vtt.light.colorShort") }}</span>
      <span>{{ $t("vtt.light.state") }}</span>
      <span />
    </div>
    <ol>
      <li class="light-management__global">
        <strong class="light-management__name">
          {{ $t("vtt.scene.fields.globalLightLevel") }}
        </strong>
        <span class="light-management__type">
          {{ $t("vtt.light.globalType") }}
        </span>
        <label class="light-management__level">
          <input
            type="number"
            min="0"
            max="100"
            step="5"
            :value="globalPercent"
            :disabled="busy"
            @change="updateGlobal($event.target.value)"
          />%
        </label>
        <i class="light-management__swatch light-management__swatch--global" />
        <button
          type="button"
          class="light-management__toggle"
          :class="{ active: globalLightLevel > 0 }"
          :disabled="busy"
          @click="updateGlobal(globalLightLevel > 0 ? 0 : 100)"
        >
          {{ globalLightLevel > 0 ? "ON" : "OFF" }}
        </button>
        <span
          class="light-management__lock"
          :title="$t('vtt.light.globalLocked')"
        >
          🔒
        </span>
      </li>
      <li
        v-for="light in lights"
        :key="light.id"
        :class="{ selected: light.id === selectedId }"
        @click="$emit('select', light.id)"
      >
        <button type="button" class="light-management__name">
          {{ light.name }}
        </button>
        <span class="light-management__type">
          {{ $t(`vtt.light.types.${light.sourceType}`) }}
        </span>
        <strong>{{ Math.round(light.lumens) }} lm</strong>
        <i
          class="light-management__swatch"
          :style="{ backgroundColor: light.color }"
          :title="light.color"
        />
        <button
          type="button"
          class="light-management__toggle"
          :class="{ active: light.enabled }"
          :disabled="busy"
          @click.stop="update(light, { enabled: !light.enabled })"
        >
          {{ light.enabled ? "ON" : "OFF" }}
        </button>
        <div class="light-management__actions">
          <button
            type="button"
            :title="$t('vtt.light.edit')"
            :disabled="busy"
            @click.stop="$emit('edit', light.id)"
          >
            ⚙
          </button>
          <button
            type="button"
            :title="$t('vtt.light.copy')"
            :disabled="busy"
            @click.stop="$emit('copy', light.id)"
          >
            ⧉
          </button>
          <button
            type="button"
            class="light-management__danger"
            :title="$t('vtt.light.delete')"
            :disabled="busy"
            @click.stop="$emit('delete', light)"
          >
            ×
          </button>
        </div>
      </li>
    </ol>
  </aside>
</template>

<script>
export default {
  name: "LightManagementPanel",
  props: {
    lights: { type: Array, default: () => [] },
    globalLightLevel: { type: Number, default: 0 },
    selectedId: { type: [Number, String], default: null },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "add", "update", "global-update", "edit", "copy", "delete"],
  computed: {
    globalPercent() {
      return Math.round(this.globalLightLevel * 100);
    },
  },
  methods: {
    update(light, changes) {
      this.$emit("update", { light, changes });
    },
    updateGlobal(percent) {
      const level = Math.min(1, Math.max(0, Number(percent) / 100));
      this.$emit("global-update", level);
    },
  },
};
</script>
