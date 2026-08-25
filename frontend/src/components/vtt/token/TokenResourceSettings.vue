<template>
  <section class="token-resource-settings">
    <header>
      <div>
        <h3>{{ $t("vtt.token.resources.title") }}</h3>
        <small>{{ $t("vtt.token.resources.hint") }}</small>
      </div>
      <span>{{
        $t("vtt.token.resources.actor", { name: actor?.name || "—" })
      }}</span>
    </header>
    <div class="token-resource-settings__bars">
      <fieldset v-for="(bar, index) in normalized.bars" :key="`bar-${index}`">
        <legend>
          <label>
            <input
              type="checkbox"
              :checked="bar.enabled"
              @change="update('bars', index, 'enabled', $event.target.checked)"
            />
            {{ $t("vtt.token.resources.bar", { number: index + 1 }) }}
          </label>
        </legend>
        <input
          :value="bar.label"
          :placeholder="$t('vtt.token.resources.label')"
          maxlength="30"
          @input="update('bars', index, 'label', $event.target.value)"
        />
        <input
          :value="bar.color"
          type="color"
          :title="$t('vtt.token.resources.color')"
          @input="update('bars', index, 'color', $event.target.value)"
        />
        <input
          :value="bar.value"
          type="number"
          step="any"
          :title="$t('vtt.token.resources.value')"
          @input="updateNumber('bars', index, 'value', $event.target.value)"
        />
        <input
          :value="bar.max"
          type="number"
          step="any"
          :title="$t('vtt.token.resources.maximum')"
          @input="updateNumber('bars', index, 'max', $event.target.value)"
        />
        <input
          :value="bar.attributePath"
          :list="attributeListId"
          :placeholder="$t('vtt.token.resources.valuePath')"
          @change="
            bind('bars', index, 'attributePath', 'value', $event.target.value)
          "
        />
        <input
          :value="bar.maxAttributePath"
          :list="attributeListId"
          :placeholder="$t('vtt.token.resources.maxPath')"
          @change="
            bind('bars', index, 'maxAttributePath', 'max', $event.target.value)
          "
        />
      </fieldset>
    </div>
    <div class="token-resource-settings__bubbles">
      <fieldset
        v-for="(bubble, index) in normalized.bubbles"
        :key="`bubble-${index}`"
      >
        <legend>
          <label>
            <input
              type="checkbox"
              :checked="bubble.enabled"
              @change="
                update('bubbles', index, 'enabled', $event.target.checked)
              "
            />
            {{ $t("vtt.token.resources.bubble", { number: index + 1 }) }}
          </label>
        </legend>
        <input
          :value="bubble.label"
          :placeholder="$t('vtt.token.resources.label')"
          maxlength="30"
          @input="update('bubbles', index, 'label', $event.target.value)"
        />
        <input
          :value="bubble.value"
          type="number"
          step="any"
          :title="$t('vtt.token.resources.value')"
          @input="updateNumber('bubbles', index, 'value', $event.target.value)"
        />
        <select
          :value="bubble.position"
          @change="update('bubbles', index, 'position', $event.target.value)"
        >
          <option
            v-for="position in positions"
            :key="position"
            :value="position"
          >
            {{ $t(`vtt.token.resourcePositions.${position}`) }}
          </option>
        </select>
        <input
          :value="bubble.attributePath"
          :list="attributeListId"
          :placeholder="$t('vtt.token.resources.valuePath')"
          @change="
            bind(
              'bubbles',
              index,
              'attributePath',
              'value',
              $event.target.value,
            )
          "
        />
      </fieldset>
    </div>
    <datalist :id="attributeListId">
      <option v-for="item in attributes" :key="item.path" :value="item.path">
        {{ item.value }}
      </option>
    </datalist>
  </section>
</template>

<script>
import {
  cloneTokenResources,
  normalizeTokenResources,
  numericActorAttributes,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenResourceSettings",
  props: {
    modelValue: { type: Object, default: () => ({}) },
    actor: { type: Object, default: null },
  },
  emits: ["update:modelValue"],
  data: () => ({
    positions: [
      "top-left",
      "top-center",
      "top-right",
      "bottom-left",
      "bottom-center",
      "bottom-right",
    ],
  }),
  computed: {
    normalized() {
      return normalizeTokenResources(this.modelValue);
    },
    attributes() {
      return numericActorAttributes(this.actor?.data || {});
    },
    attributeListId() {
      return `token-resource-attributes-${this.actor?.id || "none"}`;
    },
  },
  methods: {
    update(group, index, field, value) {
      const next = cloneTokenResources(this.modelValue);
      next[group][index][field] = value;
      this.$emit("update:modelValue", next);
    },
    updateNumber(group, index, field, value) {
      const number = Number(value);
      this.update(group, index, field, Number.isFinite(number) ? number : 0);
    },
    bind(group, index, field, valueField, path) {
      const next = cloneTokenResources(this.modelValue);
      next[group][index][field] = path.trim();
      const attribute = this.attributes.find((item) => item.path === path);
      if (attribute) next[group][index][valueField] = attribute.value;
      this.$emit("update:modelValue", next);
    },
  },
};
</script>
