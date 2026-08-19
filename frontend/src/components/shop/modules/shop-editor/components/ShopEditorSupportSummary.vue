<!-- Komponent modułu Sklep - dodaj/edytuj. Ten plik renderuje sekcje wspierające edytora, takie jak aliasy szyldu i automatyczne tagi. -->
<template>
  <div class="shop-editor-support-row">
    <ShopEditorSectionCard
      section-class="shop-editor-support-panel"
      :title="$t('shop.shopEditor.signboardAliases')"
    >
      <ShopEditorField
        for-id="shop-editor-signboard-alt"
        field-class="shop-editor-field--full"
        :label="$t('shop.shopEditor.signboardAliases')"
        :tooltip="$t('shop.shopEditor.tooltips.signboardAliases')"
        tooltip-align="right"
      >
        <textarea
          id="shop-editor-signboard-alt"
          rows="3"
          class="form-control w-100 noR trade-textarea shop-editor-aliases-textarea shop-editor-aliases-textarea--compact"
          :value="signboardAltNamesText || ''"
          @input="
            emit('update-field', {
              field: 'signboardAltNamesText',
              value: $event.target.value,
            })
          "
        ></textarea>
      </ShopEditorField>
    </ShopEditorSectionCard>

    <ShopEditorSectionCard
      section-class="shop-editor-support-panel shop-editor-support-panel--tags"
      :title="$t('shop.shopEditor.autoTags')"
    >
      <ShopEditorField
        for-id="shop-editor-categories"
        field-class="shop-editor-field--full"
        :label="$t('shop.shopEditor.autoTags')"
        :tooltip="$t('shop.shopEditor.tooltips.autoTags')"
        tooltip-align="right"
      >
        <div
          id="shop-editor-categories"
          class="shop-editor-tags-field shop-editor-tags-field--compact"
          :class="{ 'shop-editor-tags-field--empty': !autoTagItems.length }"
        >
          <template v-if="autoTagItems.length">
            <span
              v-for="tag in autoTagItems"
              :key="`shop-auto-tag-${tag}`"
              class="shop-editor-tag"
            >
              {{ tag }}
            </span>
          </template>
          <span v-else class="shop-editor-tags-placeholder">
            {{ $t("shop.shopEditor.emptyAutoTags") }}
          </span>
        </div>
      </ShopEditorField>
    </ShopEditorSectionCard>
  </div>
</template>

<script setup>
import ShopEditorField from "@/components/shop/modules/shop-editor/components/ShopEditorField.vue";
import ShopEditorSectionCard from "@/components/shop/modules/shop-editor/components/ShopEditorSectionCard.vue";

defineProps({
  autoTagItems: {
    type: Array,
    default: () => [],
  },
  signboardAltNamesText: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update-field"]);
</script>
