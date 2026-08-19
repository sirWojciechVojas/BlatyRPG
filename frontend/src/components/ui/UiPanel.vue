<template>
  <component
    :is="as"
    class="ui-panel"
    :class="[
      `ui-panel--${tone}`,
      `ui-panel--${density}`,
      { 'ui-panel--flat': flat },
    ]"
    :aria-labelledby="title ? headingId : undefined"
  >
    <header
      v-if="eyebrow || title || $slots.header || $slots.actions"
      class="ui-panel__header"
    >
      <slot name="header">
        <div class="ui-panel__heading">
          <span v-if="eyebrow" class="ui-panel__eyebrow">{{ eyebrow }}</span>
          <component :is="headingTag" v-if="title" :id="headingId">
            {{ title }}
          </component>
        </div>
      </slot>
      <div v-if="$slots.actions" class="ui-panel__actions">
        <slot name="actions" />
      </div>
    </header>

    <div class="ui-panel__body">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="ui-panel__footer">
      <slot name="footer" />
    </footer>
  </component>
</template>

<script>
let panelSequence = 0;
const PANEL_TONES = ["default", "subtle", "raised"];
const PANEL_DENSITIES = ["compact", "comfortable"];

export default {
  name: "UiPanel",
  props: {
    as: {
      type: String,
      default: "section",
    },
    title: {
      type: String,
      default: "",
    },
    eyebrow: {
      type: String,
      default: "",
    },
    headingTag: {
      type: String,
      default: "h2",
    },
    tone: {
      type: String,
      default: "default",
      validator: (value) => PANEL_TONES.includes(value),
    },
    density: {
      type: String,
      default: "compact",
      validator: (value) => PANEL_DENSITIES.includes(value),
    },
    flat: Boolean,
  },
  data() {
    panelSequence += 1;
    return {
      headingId: "ui-panel-title-" + panelSequence,
    };
  },
};
</script>
