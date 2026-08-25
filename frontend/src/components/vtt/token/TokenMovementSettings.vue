<template>
  <section class="token-movement-settings">
    <header>
      <div>
        <h3>{{ $t("vtt.token.movement.title") }}</h3>
        <p>{{ $t("vtt.token.movement.description") }}</p>
      </div>
      <strong>{{ remaining }} / {{ range }} PR</strong>
    </header>

    <small v-if="movement.index >= 0" class="token-movement-settings__source">
      {{
        $t("vtt.token.movement.resourceSource", {
          bar: movement.index + 1,
        })
      }}
    </small>

    <div class="token-movement-settings__meter">
      <i :style="{ width: `${percent}%` }" />
    </div>

    <div class="token-movement-settings__grid">
      <label>
        <span>{{ $t("vtt.token.movement.range") }}</span>
        <input
          :value="range"
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
          :value="spent"
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
import {
  cloneTokenResources,
  tokenMovementResourceState,
  tokenResourcesWithMovement,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenMovementSettings",
  props: {
    modelValue: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
  },
  emits: ["update:modelValue"],
  computed: {
    movement() {
      return tokenMovementResourceState(
        this.modelValue.resources,
        this.modelValue.movementRange,
        this.modelValue.movementSpent,
      );
    },
    range() {
      return this.movement.range;
    },
    spent() {
      return this.movement.spent;
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
      if (key === "movementResetMode") {
        this.$emit("update:modelValue", { ...this.modelValue, [key]: value });
        return;
      }
      const number = Math.max(0, Number(value) || 0);
      const range = key === "movementRange" ? number : this.range;
      const spent = key === "movementSpent" ? number : this.spent;
      const next = { ...this.modelValue, [key]: number };
      if (this.movement.index >= 0) {
        next.movementRange = range;
        next.movementSpent = spent;
        next.resources = tokenResourcesWithMovement(
          cloneTokenResources(this.modelValue.resources),
          range,
          Math.max(0, range - spent),
        );
      }
      this.$emit("update:modelValue", next);
    },
  },
};
</script>
