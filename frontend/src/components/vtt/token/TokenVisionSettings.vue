<template>
  <section class="token-vision-settings">
    <header>
      <div>
        <small>{{ $t("vtt.token.vision.kicker") }}</small>
        <h3>{{ $t("vtt.token.vision.title") }}</h3>
      </div>
      <label class="token-vision-settings__switch">
        <input v-model="draft.enabled" type="checkbox" />
        {{ $t("vtt.token.vision.enabled") }}
      </label>
    </header>
    <div
      :class="{ disabled: !draft.enabled }"
      class="token-vision-settings__grid"
    >
      <label>
        <span>{{ $t("vtt.token.vision.mode") }}</span>
        <select v-model="draft.mode">
          <option value="basic">
            {{ $t("vtt.token.vision.modes.basic") }}
          </option>
          <option value="darkvision">
            {{ $t("vtt.token.vision.modes.darkvision") }}
          </option>
          <option value="light_amplification">
            {{ $t("vtt.token.vision.modes.lightAmplification") }}
          </option>
          <option value="monochromatic">
            {{ $t("vtt.token.vision.modes.monochromatic") }}
          </option>
          <option value="tremorsense">
            {{ $t("vtt.token.vision.modes.tremorsense") }}
          </option>
        </select>
      </label>
      <label>
        <span>{{ $t("vtt.token.vision.range") }}</span>
        <input
          v-model.number="draft.range"
          type="number"
          min="0"
          max="100000"
          step="1"
        />
      </label>
      <label>
        <span>{{ $t("vtt.token.vision.angle") }}</span>
        <select v-model.number="draft.angle">
          <option :value="360">360°</option>
          <option :value="180">180°</option>
          <option :value="120">120°</option>
          <option :value="90">90°</option>
          <option :value="60">60°</option>
        </select>
      </label>
      <label>
        <span>{{ $t("vtt.token.vision.minimumRadius") }}</span>
        <input
          v-model.number="draft.minimumRadius"
          type="number"
          min="0"
          max="100000"
          step="1"
        />
      </label>
      <label
        v-if="draft.mode === 'darkvision'"
        class="token-vision-settings__check"
      >
        <input v-model="draft.darkvision" type="checkbox" />
        <span>{{ $t("vtt.token.vision.darkvision") }}</span>
      </label>
      <label v-if="draft.mode === 'darkvision'">
        <span>{{ $t("vtt.token.vision.darkvisionRange") }}</span>
        <input
          v-model.number="draft.darkvisionRange"
          type="number"
          min="0"
          max="100000"
          step="1"
        />
      </label>
      <label class="token-vision-settings__check">
        <input v-model="draft.limitByLight" type="checkbox" />
        <span>{{ $t("vtt.token.vision.limitByLight") }}</span>
      </label>
      <label class="token-vision-settings__check">
        <input v-model="draft.constrainedByWalls" type="checkbox" />
        <span>{{ $t("vtt.token.vision.constrainedByWalls") }}</span>
      </label>
      <label class="token-vision-settings__check token-vision-settings__wide">
        <input v-model="draft.showShape" type="checkbox" />
        <span>{{ $t("vtt.token.vision.showShape") }}</span>
      </label>
      <div
        class="token-vision-settings__appearance token-vision-settings__wide"
        :class="{ disabled: !draft.showShape }"
      >
        <label>
          <span>{{ $t("vtt.token.vision.shapeBorderColor") }}</span>
          <input v-model="draft.shapeBorderColor" type="color" />
        </label>
        <label>
          <span>{{ $t("vtt.token.vision.shapeBorderOpacity") }}</span>
          <output>{{ opacityPercent(draft.shapeBorderOpacity) }}%</output>
          <input
            v-model.number="draft.shapeBorderOpacity"
            type="range"
            min="0"
            max="1"
            step="0.05"
          />
        </label>
        <label>
          <span>{{ $t("vtt.token.vision.shapeFillColor") }}</span>
          <input v-model="draft.shapeFillColor" type="color" />
        </label>
        <label>
          <span>{{ $t("vtt.token.vision.shapeFillOpacity") }}</span>
          <output>{{ opacityPercent(draft.shapeFillOpacity) }}%</output>
          <input
            v-model.number="draft.shapeFillOpacity"
            type="range"
            min="0"
            max="1"
            step="0.05"
          />
        </label>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "TokenVisionSettings",
  props: { modelValue: { type: Object, required: true } },
  emits: ["update:modelValue"],
  computed: {
    draft: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit("update:modelValue", value);
      },
    },
  },
  methods: {
    opacityPercent(value) {
      return Math.round(Math.max(0, Math.min(1, Number(value) || 0)) * 100);
    },
  },
};
</script>
