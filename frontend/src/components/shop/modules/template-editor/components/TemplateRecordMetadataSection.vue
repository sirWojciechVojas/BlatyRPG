<!-- Komponent modułu Szablony - dodaj/edytuj. Ten plik odpowiada za metadane, klasyfikację i parametry dodatkowe szablonu przedmiotu. -->
<template>
  <div
    class="template-record-field row g-1 align-items-center"
    :class="{ invisible: form.ITEM_CLASS !== 'WEAPON' }"
  >
    <label
      class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
      :for="`${fieldPrefix}-ITEM_ID`"
    >
      ITEM_ID
    </label>
    <div class="col-8 col-sm-9 col-xl-8 min-w-0">
      <div class="input-group input-group-sm template-record-input-group">
        <input
          :id="`${fieldPrefix}-ITEM_ID`"
          :type="ctx.fieldInputType('ITEM_ID')"
          :value="form.ITEM_ID"
          class="form-control form-control-sm trade-input template-record-input"
          name="ITEM_ID"
          :readonly="ctx.isReadOnlyField('ITEM_ID', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('ITEM_ID', readOnlyContext),
            'is-invalid': hasError('ITEM_ID'),
          }"
          @input="updateField('ITEM_ID', $event)"
        />
        <TradeFieldTriggerButton
          class="template-record-trigger"
          field="ITEM_ID"
          :target="editTarget"
        />
      </div>
      <div v-if="hasError('ITEM_ID')" class="trade-field-error">
        {{ error("ITEM_ID") }}
      </div>
    </div>
  </div>

  <div class="template-record-field row g-1 align-items-center">
    <label
      class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
      :for="`${fieldPrefix}-ITEM_CLASS`"
    >
      ITEM_CLASS
    </label>
    <div class="col-8 col-sm-9 col-xl-8 min-w-0">
      <div class="input-group input-group-sm template-record-input-group">
        <input
          :id="`${fieldPrefix}-ITEM_CLASS`"
          :type="ctx.fieldInputType('ITEM_CLASS')"
          :value="form.ITEM_CLASS"
          class="form-control form-control-sm trade-input template-record-input"
          name="ITEM_CLASS"
          :readonly="ctx.isReadOnlyField('ITEM_CLASS', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('ITEM_CLASS', readOnlyContext),
            'is-invalid': hasError('ITEM_CLASS'),
          }"
          @input="updateField('ITEM_CLASS', $event)"
        />
        <TradeFieldTriggerButton
          class="template-record-trigger"
          field="ITEM_CLASS"
          :target="editTarget"
        />
      </div>
      <div v-if="hasError('ITEM_CLASS')" class="trade-field-error">
        {{ error("ITEM_CLASS") }}
      </div>
    </div>
  </div>

  <div class="template-record-field row g-1 align-items-center">
    <label
      class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
      :for="`${fieldPrefix}-IMG_CLASS`"
    >
      IMG_CLASS
    </label>
    <div class="col-8 col-sm-9 col-xl-8 min-w-0">
      <div class="input-group input-group-sm template-record-input-group">
        <input
          :id="`${fieldPrefix}-IMG_CLASS`"
          :type="ctx.fieldInputType('IMG_CLASS')"
          :value="form.IMG_CLASS"
          class="form-control form-control-sm trade-input template-record-input"
          name="IMG_CLASS"
          :readonly="ctx.isReadOnlyField('IMG_CLASS', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('IMG_CLASS', readOnlyContext),
          }"
          @input="updateField('IMG_CLASS', $event)"
        />
        <TradeFieldTriggerButton
          class="template-record-trigger"
          field="IMG_CLASS"
          :target="editTarget"
        />
      </div>
    </div>
  </div>

  <div class="template-record-field row g-1 align-items-center">
    <label
      class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
      :for="`${fieldPrefix}-ITEM_GENRE`"
    >
      ITEM_GENRE
    </label>
    <div class="col-8 col-sm-9 col-xl-8 min-w-0">
      <div class="input-group input-group-sm template-record-input-group">
        <input
          :id="`${fieldPrefix}-ITEM_GENRE`"
          :type="ctx.fieldInputType('ITEM_GENRE')"
          :value="form.ITEM_GENRE"
          class="form-control form-control-sm trade-input template-record-input"
          name="ITEM_GENRE"
          :readonly="ctx.isReadOnlyField('ITEM_GENRE', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('ITEM_GENRE', readOnlyContext),
          }"
          @input="updateField('ITEM_GENRE', $event)"
        />
        <TradeFieldTriggerButton
          class="template-record-trigger"
          field="ITEM_GENRE"
          :target="editTarget"
        />
      </div>
    </div>
  </div>

  <div class="template-record-field row g-1 align-items-center">
    <label
      class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
      :for="`${fieldPrefix}-PRIZE`"
    >
      PRIZE
    </label>
    <div class="col-8 col-sm-9 col-xl-8 min-w-0">
      <div class="input-group input-group-sm template-record-input-group">
        <input
          :id="`${fieldPrefix}-PRIZE`"
          :type="ctx.fieldInputType('PRIZE')"
          :value="form.PRIZE"
          class="form-control form-control-sm trade-input template-record-input"
          name="PRIZE"
          :readonly="ctx.isReadOnlyField('PRIZE', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('PRIZE', readOnlyContext),
            'is-invalid': hasError('PRIZE'),
          }"
          @input="updateField('PRIZE', $event)"
        />
        <TradeFieldTriggerButton
          class="template-record-trigger"
          field="PRIZE"
          :target="editTarget"
        />
      </div>
      <div v-if="hasError('PRIZE')" class="trade-field-error">
        {{ error("PRIZE") }}
      </div>
    </div>
  </div>

  <div class="template-record-field row g-1 align-items-center">
    <label
      class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
      :for="`${fieldPrefix}-CHARGE`"
    >
      CHARGE
    </label>
    <div class="col-8 col-sm-9 col-xl-8 min-w-0">
      <div class="input-group input-group-sm template-record-input-group">
        <input
          :id="`${fieldPrefix}-CHARGE`"
          :type="ctx.fieldInputType('CHARGE')"
          :value="form.CHARGE"
          class="form-control form-control-sm trade-input template-record-input"
          name="CHARGE"
          :readonly="ctx.isReadOnlyField('CHARGE', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('CHARGE', readOnlyContext),
            'is-invalid': hasError('CHARGE'),
          }"
          @input="updateField('CHARGE', $event)"
        />
        <TradeFieldTriggerButton
          class="template-record-trigger"
          field="CHARGE"
          :target="editTarget"
        />
      </div>
      <div v-if="hasError('CHARGE')" class="trade-field-error">
        {{ error("CHARGE") }}
      </div>
    </div>
  </div>
</template>

<script setup>
import TradeFieldTriggerButton from "@/components/shop/common/TradeFieldTriggerButton.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

defineProps({
  editTarget: {
    type: String,
    required: true,
  },
  error: {
    type: Function,
    required: true,
  },
  fieldPrefix: {
    type: String,
    required: true,
  },
  form: {
    type: Object,
    required: true,
  },
  hasError: {
    type: Function,
    required: true,
  },
  readOnlyContext: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["update-field"]);
const ctx = useTradeModalContext();
const updateField = (field, event) => {
  emit("update-field", { field, value: event.target.value });
};
</script>
