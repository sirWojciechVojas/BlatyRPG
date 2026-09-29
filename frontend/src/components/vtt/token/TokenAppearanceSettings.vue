<template>
  <div class="token-appearance-settings">
    <section class="token-settings-panel__presets">
      <h3>{{ $t("vtt.token.settings.sizePresets") }}</h3>
      <div>
        <button
          v-for="preset in sizePresets"
          :key="preset.key"
          type="button"
          :class="{ active: hasSize(preset.cells) }"
          @click="applySize(preset.cells)"
        >
          <b>{{ formatScale(preset.cells) }}×</b>
          <small>{{ $t(`vtt.token.sizes.${preset.key}`) }}</small>
        </button>
      </div>
    </section>
    <section class="token-settings-panel__grid">
      <label class="token-settings-panel__wide">
        <span>{{ $t("vtt.token.settings.name") }}</span>
        <input
          :value="modelValue.name"
          maxlength="150"
          required
          @input="update('name', $event.target.value)"
        />
      </label>
      <label class="token-settings-panel__wide">
        <span>{{ $t("vtt.token.settings.imageUrl") }}</span>
        <input
          :value="modelValue.imageUrl"
          maxlength="2048"
          @input="update('imageUrl', $event.target.value)"
        />
      </label>
      <label>
        <span>{{ $t("vtt.token.settings.widthCells") }}</span>
        <input
          :value="modelValue.widthCells"
          type="number"
          min="0.25"
          max="100"
          step="0.25"
          @input="updateNumber('widthCells', $event.target.value)"
        />
      </label>
      <label>
        <span>{{ $t("vtt.token.settings.heightCells") }}</span>
        <input
          :value="modelValue.heightCells"
          type="number"
          min="0.25"
          max="100"
          step="0.25"
          @input="updateNumber('heightCells', $event.target.value)"
        />
      </label>
      <label>
        <span>{{ $t("vtt.token.settings.rotation") }}</span>
        <input
          :value="modelValue.rotation"
          type="number"
          step="1"
          @input="updateNumber('rotation', $event.target.value)"
        />
        <small v-if="canManage" class="token-settings-panel__handle-toggle">
          <input
            :checked="modelValue.rotationHandleEnabled"
            type="checkbox"
            @change="update('rotationHandleEnabled', $event.target.checked)"
          />
          {{ $t("vtt.token.settings.enableRotationHandle") }}
        </small>
      </label>
      <label>
        <span>{{ $t("vtt.token.settings.facing") }}</span>
        <input
          :value="modelValue.facing"
          type="number"
          step="1"
          @input="updateNumber('facing', $event.target.value)"
        />
        <small v-if="canManage" class="token-settings-panel__handle-toggle">
          <input
            :checked="modelValue.facingHandleEnabled"
            type="checkbox"
            @change="update('facingHandleEnabled', $event.target.checked)"
          />
          {{ $t("vtt.token.settings.enableFacingHandle") }}
        </small>
      </label>
      <label
        v-if="canManage"
        class="token-settings-panel__wide token-settings-panel__info-toggle"
      >
        <input
          :checked="modelValue.rotationFollowsFacing"
          type="checkbox"
          @change="update('rotationFollowsFacing', $event.target.checked)"
        />
        <span>{{ $t("vtt.token.settings.rotationFollowsFacing") }}</span>
      </label>
      <label>
        <span>{{ $t("vtt.token.settings.elevation") }}</span>
        <input
          :value="modelValue.elevation"
          type="number"
          step="1"
          @input="updateNumber('elevation', $event.target.value)"
        />
      </label>
      <label>
        <span>{{ $t("vtt.token.settings.disposition") }}</span>
        <select
          :value="modelValue.disposition"
          @change="update('disposition', $event.target.value)"
        >
          <option v-for="value in dispositions" :key="value" :value="value">
            {{ $t(`vtt.token.dispositions.${value}`) }}
          </option>
        </select>
      </label>
      <label
        v-if="canManage"
        class="token-settings-panel__wide token-settings-panel__info-toggle"
      >
        <input
          :checked="modelValue.showInfoUnselected"
          type="checkbox"
          @change="update('showInfoUnselected', $event.target.checked)"
        />
        <span>{{ $t("vtt.token.settings.showInfoUnselected") }}</span>
      </label>
    </section>
  </div>
</template>

<script>
import { TOKEN_SIZE_PRESETS } from "@/lib/vtt/tokenSettingsDraft";

export default {
  name: "TokenAppearanceSettings",
  props: {
    modelValue: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
  },
  emits: ["update:modelValue"],
  data: () => ({
    sizePresets: TOKEN_SIZE_PRESETS,
    dispositions: ["friendly", "neutral", "hostile", "secret"],
  }),
  methods: {
    applySize(cells) {
      this.$emit("update:modelValue", {
        ...this.modelValue,
        widthCells: cells,
        heightCells: cells,
      });
    },
    hasSize(cells) {
      return (
        Number(this.modelValue.widthCells) === cells &&
        Number(this.modelValue.heightCells) === cells
      );
    },
    update(field, value) {
      this.$emit("update:modelValue", { ...this.modelValue, [field]: value });
    },
    updateNumber(field, value) {
      const number = Number(value);
      this.update(field, Number.isFinite(number) ? number : 0);
    },
    formatScale(cells) {
      return new Intl.NumberFormat(this.$i18n.locale, {
        maximumFractionDigits: 2,
      }).format(cells);
    },
  },
};
</script>
