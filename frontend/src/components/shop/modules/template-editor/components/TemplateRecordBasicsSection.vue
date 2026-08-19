<!-- Komponent modułu Szablony - dodaj/edytuj. Ten plik odpowiada za podstawowe pola formularza szablonu przedmiotu. -->
<template>
  <template v-if="mode === 'fields'">
    <div
      class="template-record-field template-record-field--id row g-1 align-items-center"
    >
      <label
        class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
        :for="`${fieldPrefix}-ID`"
      >
        ID
      </label>
      <div class="col-8 col-sm-9 col-xl-8 min-w-0">
        <input
          :id="`${fieldPrefix}-ID`"
          :type="ctx.fieldInputType('ID')"
          :value="form.ID"
          class="form-control form-control-sm w-100 trade-input template-record-input"
          name="ID"
          :readonly="ctx.isReadOnlyField('ID', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('ID', readOnlyContext),
            'is-invalid': hasError('ID'),
          }"
          @input="updateField('ID', $event)"
        />
        <div v-if="hasError('ID')" class="trade-field-error">
          {{ error("ID") }}
        </div>
      </div>
    </div>

    <div
      class="template-record-field template-record-field--name row g-1 align-items-center"
    >
      <label
        class="col-4 col-sm-3 col-xl-4 trade-label template-record-label"
        :for="`${fieldPrefix}-NAME`"
      >
        NAME
      </label>
      <div class="col-8 col-sm-9 col-xl-8 min-w-0">
        <input
          :id="`${fieldPrefix}-NAME`"
          :type="ctx.fieldInputType('NAME')"
          :value="form.NAME"
          class="form-control form-control-sm w-100 trade-input template-record-input"
          name="NAME"
          :readonly="ctx.isReadOnlyField('NAME', readOnlyContext)"
          :class="{
            'is-readonly': ctx.isReadOnlyField('NAME', readOnlyContext),
            'is-invalid': hasError('NAME'),
          }"
          @input="updateField('NAME', $event)"
        />
        <div v-if="hasError('NAME')" class="trade-field-error">
          {{ error("NAME") }}
        </div>
      </div>
    </div>
  </template>

  <section
    v-else
    class="template-record-section template-record-section--description"
  >
    <div class="template-record-section-title">
      <span>{{ $t("shop.templateEditor.descriptionSection") }}</span>
    </div>

    <div class="template-record-copy-stack d-flex flex-column gap-2">
      <div class="template-record-copy-field">
        <label
          class="trade-label template-record-copy-label"
          :for="`${fieldPrefix}-DESCRIPTION`"
        >
          DESCRIPTION
        </label>
        <textarea
          :id="`${fieldPrefix}-DESCRIPTION`"
          :value="form.DESCRIPTION"
          class="form-control form-control-sm w-100 noR trade-textarea template-record-textarea"
          :class="{ 'is-invalid': hasError('DESCRIPTION') }"
          name="DESCRIPTION"
          rows="3"
          @input="updateField('DESCRIPTION', $event)"
        ></textarea>
        <div v-if="hasError('DESCRIPTION')" class="trade-field-error">
          {{ error("DESCRIPTION") }}
        </div>
      </div>

      <div class="template-record-copy-field">
        <label
          class="trade-label template-record-copy-label"
          :for="`${fieldPrefix}-DETAILS`"
        >
          DETAILS
        </label>
        <textarea
          :id="`${fieldPrefix}-DETAILS`"
          :value="form.DETAILS"
          class="form-control form-control-sm w-100 noR trade-textarea template-record-textarea"
          name="DETAILS"
          rows="3"
          @input="updateField('DETAILS', $event)"
        ></textarea>
      </div>
    </div>
  </section>
</template>

<script setup>
import { useTradeModalContext } from "@/components/shop/shopContext";

defineProps({
  mode: {
    type: String,
    default: "fields",
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
