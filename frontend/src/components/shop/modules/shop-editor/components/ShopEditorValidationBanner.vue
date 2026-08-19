<!-- Komponent modułu Sklep - dodaj/edytuj. Ten plik renderuje pasek walidacyjny informujący o brakujących polach wymaganych przez moduł sklepu. -->
<template>
  <div v-if="visible" class="col-md-12">
    <div
      class="shop-editor-validation-strip shop-editor-validation-strip--block"
      role="alert"
    >
      <span class="shop-editor-validation-strip__icon" aria-hidden="true">
        !
      </span>
      <span>
        {{
          $t("shop.shopEditor.validation.fillRequired", {
            fields: missingFields.join(", "),
          })
        }}
      </span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  missingFields: {
    type: Array,
    default: () => [],
  },
  visible: {
    type: Boolean,
    default: false,
  },
});
</script>
