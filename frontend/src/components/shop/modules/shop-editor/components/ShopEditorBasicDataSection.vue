<!-- Komponent modułu Sklep - dodaj/edytuj. Ten plik renderuje sekcję podstawowych danych sklepu, właściciela i szyldu. -->
<template>
  <ShopEditorSectionCard
    :title="$t('shop.shopEditor.layoutSections.basicData')"
  >
    <div class="shop-editor-block-grid shop-editor-block-grid--basic">
      <ShopEditorField
        for-id="shop-editor-shop-select"
        :label="$t('shop.shopEditor.editedShop')"
        :tooltip="$t('shop.shopEditor.tooltips.editedShop')"
        :error="
          showActiveShopError
            ? $t('shop.shopEditor.validation.requiredForSuggestions')
            : ''
        "
      >
        <select
          id="shop-editor-shop-select"
          class="form-control-sm w-100 trade-input"
          :class="{ 'is-invalid': showActiveShopError }"
          :value="activeShopId"
          @change="$emit('shop-change', $event.target.value)"
        >
          <option
            v-for="option in shopOptions"
            :key="`shop-editor-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-type"
        :label="$t('shop.shopEditor.shopType')"
        :tooltip="$t('shop.shopEditor.tooltips.shopType')"
        tooltip-align="right"
        :error="
          showTypeError
            ? $t('shop.shopEditor.validation.requiredForSuggestions')
            : ''
        "
      >
        <select
          id="shop-editor-type"
          class="form-control-sm w-100 trade-input"
          :class="{ 'is-invalid': showTypeError }"
          :value="form.typeId || ''"
          @change="emitFieldUpdate('typeId', $event.target.value)"
        >
          <option value="">{{ $t("shop.common.chooseOption") }}</option>
          <option
            v-for="option in shopTypeOptions"
            :key="`shop-type-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-owner-code"
        :label="$t('shop.shopEditor.ownerCode')"
        :tooltip="$t('shop.shopEditor.tooltips.ownerCode')"
        tooltip-align="right"
      >
        <select
          id="shop-editor-owner-code"
          class="form-control-sm w-100 trade-input"
          :value="form.ownerCode || 'BG1'"
          @change="emitFieldUpdate('ownerCode', $event.target.value)"
        >
          <option
            v-for="option in ownerOptions"
            :key="`shop-owner-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-owner-name"
        :label="$t('shop.shopEditor.ownerName')"
        :tooltip="$t('shop.shopEditor.tooltips.ownerName')"
        tooltip-align="right"
      >
        <input
          id="shop-editor-owner-name"
          type="text"
          class="form-control-sm w-100 trade-input"
          :value="form.ownerName || ''"
          @input="emitFieldUpdate('ownerName', $event.target.value)"
        />
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-signboard"
        field-class="shop-editor-field--full shop-editor-field--featured"
        :label="$t('shop.shopEditor.signboard')"
        :tooltip="$t('shop.shopEditor.tooltips.signboard')"
      >
        <input
          id="shop-editor-signboard"
          type="text"
          class="form-control-sm w-100 trade-input shop-editor-signboard-input shop-editor-signboard-input--lead"
          :value="form.signboardName || ''"
          @input="emitFieldUpdate('signboardName', $event.target.value)"
        />
      </ShopEditorField>
    </div>
  </ShopEditorSectionCard>
</template>

<script setup>
import ShopEditorField from "@/components/shop/modules/shop-editor/components/ShopEditorField.vue";
import ShopEditorSectionCard from "@/components/shop/modules/shop-editor/components/ShopEditorSectionCard.vue";

defineProps({
  activeShopId: {
    type: [Number, String],
    default: "",
  },
  form: {
    type: Object,
    required: true,
  },
  ownerOptions: {
    type: Array,
    default: () => [],
  },
  shopOptions: {
    type: Array,
    default: () => [],
  },
  shopTypeOptions: {
    type: Array,
    default: () => [],
  },
  showActiveShopError: {
    type: Boolean,
    default: false,
  },
  showTypeError: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["shop-change", "update-field"]);

const emitFieldUpdate = (field, value) => {
  emit("update-field", { field, value });
};
</script>
