<!-- Komponent modułu Szablony - dodaj/edytuj. Ten plik scala sekcje formularza szablonu i deleguje zapis danych wejściowych. -->
<template>
  <form
    class="trade-form-shell template-record-form container-fluid p-0"
    :name="formName"
    @submit.prevent
  >
    <div class="card template-record-panel">
      <div class="card-body p-2">
        <div class="row g-2 align-items-stretch template-record-layout">
          <div class="col-12">
            <section class="template-record-section">
              <div
                class="template-record-section-title d-flex align-items-center gap-2"
              >
                <span>{{ $t("shop.shopView.identifiersSection") }}</span>
                <span>{{
                  $t("shop.templateEditor.classificationSection")
                }}</span>
                <span>{{ $t("shop.templateEditor.economySection") }}</span>
              </div>
              <div class="template-record-fields-grid">
                <TemplateRecordBasicsSection
                  mode="fields"
                  :error="error"
                  :field-prefix="fieldPrefix"
                  :form="form"
                  :has-error="hasError"
                  :read-only-context="readOnlyContext"
                  @update-field="updateField"
                />
                <TemplateRecordMetadataSection
                  :edit-target="editTarget"
                  :error="error"
                  :field-prefix="fieldPrefix"
                  :form="form"
                  :has-error="hasError"
                  :read-only-context="readOnlyContext"
                  @update-field="updateField"
                />
              </div>
            </section>
          </div>
          <div class="col-12">
            <TemplateRecordBasicsSection
              mode="description"
              :error="error"
              :field-prefix="fieldPrefix"
              :form="form"
              :has-error="hasError"
              :read-only-context="readOnlyContext"
              @update-field="updateField"
            />
          </div>
        </div>
      </div>
    </div>
  </form>
</template>

<script setup>
import { computed } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import TemplateRecordBasicsSection from "@/components/shop/modules/template-editor/components/TemplateRecordBasicsSection.vue";
import TemplateRecordMetadataSection from "@/components/shop/modules/template-editor/components/TemplateRecordMetadataSection.vue";

const props = defineProps({
  variant: {
    type: String,
    default: "edit",
  },
});

const ctx = useTradeModalContext();

const form = computed(() =>
  props.variant === "create" ? ctx.localNewTemplateForm : ctx.localTemplateForm,
);
const errors = computed(() =>
  props.variant === "create"
    ? ctx.newTemplateFormErrors
    : ctx.templateFormErrors,
);
const editTarget = computed(() =>
  props.variant === "create" ? "newTemplate" : "template",
);
const fieldPrefix = computed(() =>
  props.variant === "create" ? "sell-add" : "sell",
);
const formName = computed(() =>
  props.variant === "create" ? "inv-add" : "inv",
);
const readOnlyContext = computed(() =>
  props.variant === "create" ? "templateCreate" : "templateEdit",
);

const error = (field) => String(errors.value?.[field] || "");
const hasError = (field) => Boolean(error(field));
const updateField = ({ field, value }) => {
  form.value[field] = value;
};
</script>
