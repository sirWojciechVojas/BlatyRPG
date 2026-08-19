<!-- Responsibility: TradeFieldTriggerButton shop interface component. -->
<template>
  <button
    type="button"
    class="btn btn-sm trade-field-trigger col-md-2"
    :aria-label="label"
    :title="label"
    @click="ctx.openClassEdit(field, target)"
  >
    <span class="trade-field-trigger__icon" aria-hidden="true">
      <span class="bi bi-chevron-right" aria-hidden="true"></span>
    </span>
  </button>
</template>

<script setup>
import { computed } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import i18n from "@/i18n";

const props = defineProps({
  field: {
    type: String,
    required: true,
  },
  target: {
    type: String,
    required: true,
  },
});

const ctx = useTradeModalContext();
const label = computed(() =>
  i18n.global.t("modals.fieldEdit.actions.editField", {
    field: props.field,
  }),
);
</script>
