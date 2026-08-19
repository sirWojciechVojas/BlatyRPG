<template>
  <div class="ui-field" :class="{ 'ui-field--invalid': Boolean(error) }">
    <label v-if="label" class="ui-field__label" :for="controlId">
      <span>{{ label }}</span>
      <span v-if="required" class="ui-field__required" aria-hidden="true"
        >*</span
      >
    </label>

    <div class="ui-field__control">
      <slot :id="controlId" :control-attrs="controlAttrs" />
    </div>

    <p v-if="error" :id="messageId" class="ui-field__message" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="messageId" class="ui-field__hint">
      {{ hint }}
    </p>
  </div>
</template>

<script>
let fieldSequence = 0;

export default {
  name: "UiField",
  props: {
    id: {
      type: String,
      default: "",
    },
    label: {
      type: String,
      default: "",
    },
    hint: {
      type: String,
      default: "",
    },
    error: {
      type: String,
      default: "",
    },
    required: Boolean,
  },
  data() {
    fieldSequence += 1;
    return {
      generatedId: "ui-field-" + fieldSequence,
    };
  },
  computed: {
    controlId() {
      return this.id || this.generatedId;
    },
    messageId() {
      return this.hint || this.error ? this.controlId + "-message" : undefined;
    },
    controlAttrs() {
      return {
        id: this.controlId,
        required: this.required || undefined,
        "aria-describedby": this.messageId,
        "aria-invalid": this.error ? "true" : undefined,
      };
    },
  },
};
</script>
