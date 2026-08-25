<template>
  <section class="token-movement-settings">
    <header>
      <div>
        <h3>{{ $t("vtt.token.movement.title") }}</h3>
        <p>{{ $t("vtt.token.movement.description") }}</p>
      </div>
      <strong>{{ remaining }} / {{ range }} PR</strong>
    </header>

    <div class="token-movement-settings__meter">
      <i :style="{ width: `${percent}%` }" />
    </div>

    <div class="token-movement-settings__grid">
      <label>
        <span>{{ $t("vtt.token.movement.range") }}</span>
        <input
          :value="modelValue.movementRange"
          type="number"
          min="0"
          max="10000"
          step="0.5"
          :disabled="!canManage"
          @input="change('movementRange', $event.target.value)"
        />
      </label>
      <label>
        <span>{{ $t("vtt.token.movement.spent") }}</span>
        <input
          :value="modelValue.movementSpent"
          type="number"
          min="0"
          max="10000"
          step="0.5"
          :disabled="!canManage"
          @input="change('movementSpent', $event.target.value)"
        />
      </label>
      <label>
        <span>{{ $t("vtt.token.movement.resetMode") }}</span>
        <select
          :value="modelValue.movementResetMode"
          :disabled="!canManage"
          @change="change('movementResetMode', $event.target.value)"
        >
          <option value="turn">{{ $t("vtt.token.movement.turn") }}</option>
          <option value="round">{{ $t("vtt.token.movement.round") }}</option>
          <option value="manual">{{ $t("vtt.token.movement.manual") }}</option>
        </select>
      </label>
      <button
        v-if="canManage"
        type="button"
        @click="change('movementSpent', 0)"
      >
        {{ $t("vtt.token.movement.reset") }}
      </button>
    </div>
  </section>
</template>

<script>
export default {
  name: "TokenMovementSettings",
  props: {
    modelValue: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
  },
  emits: ["update:modelValue"],
  computed: {
    range() {
      return Math.max(0, Number(this.modelValue.movementRange) || 0);
    },
    spent() {
      return Math.max(0, Number(this.modelValue.movementSpent) || 0);
    },
    remaining() {
      return Math.max(0, this.range - this.spent);
    },
    percent() {
      return this.range
        ? Math.min(100, (this.remaining / this.range) * 100)
        : 0;
    },
  },
  methods: {
    change(key, value) {
      this.$emit("update:modelValue", { ...this.modelValue, [key]: value });
    },
  },
};
</script>
