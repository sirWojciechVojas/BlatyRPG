<!-- Panel GM sklepu: ShopWorkspaceCatalogBrowser. -->
<template>
  <div class="workspace-list-panel catalog-list-panel">
    <CompactToolbar :label="catalogModeTitle"
      ><input
        v-model.trim="catalogQuery"
        type="search"
        :placeholder="$t('ui.search')"
      /><select
        v-model="catalogType"
        class="form-select form-select-sm gm-combobox gm-combobox--type"
      >
        <option value="">{{ $t("shop.workspace.allTypes") }}</option>
        <option
          v-for="entry in itemClassOptions"
          :key="entry.code"
          :value="entry.code"
        >
          {{ domainOptionLabel(entry) }}
        </option></select
      ><template #actions
        ><button
          v-if="catalogMode === 'templates'"
          type="button"
          class="primary"
          @click="newTemplate"
        >
          {{ $t("shop.workspace.catalogModes.newTemplate") }}
        </button></template
      ></CompactToolbar
    >
    <ItemList
      :items="filteredTemplates"
      item-key="ID"
      :selected-keys="selectedCatalogKeys"
      :density="density"
      :label="catalogModeTitle"
      :empty-label="$t('shop.workspace.empty')"
      @select="selectCatalogTemplate"
      @details="selectCatalogTemplate"
      ><template #default="{ item }"
        ><span class="item-icon-leading"
          ><ItemIcon
            :item="item"
            :size="density === 'comfortable' ? 34 : 30" /></span
        ><strong>{{ item.NAME }}</strong
        ><span
          >{{ domainLabel("classes", item.ITEM_CLASS) }} ·
          {{ domainLabel("genres", item.ITEM_GENRE) }}</span
        ><span class="catalog-price-cell"
          ><CurrencyDisplay
            :brass="item.PRIZE"
            :currency-code="displayCurrencyCode(item.CURRENCY)"
            variant="row"
          /><small>· {{ item.CHARGE }}</small></span
        ></template
      ></ItemList
    >
  </div>
</template>
<script>
import CompactToolbar from "@/components/shop/common/CompactToolbar.vue";
import ItemList from "@/components/shop/common/ItemList.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceCatalogBrowser",
  components: { CompactToolbar, ItemList, ItemIcon, CurrencyDisplay },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
