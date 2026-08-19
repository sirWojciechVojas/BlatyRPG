<!-- Panel GM sklepu: ShopWorkspaceArchive. -->
<template>
  <div class="archive-grid">
    <section>
      <h2>{{ $t("shop.workspace.archivedItems") }}</h2>
      <ItemList
        :items="trashItems"
        item-key="ID"
        :density="density"
        :label="$t('shop.workspace.archivedItems')"
        :empty-label="$t('shop.workspace.empty')"
        @details="showDetails"
        ><template #default="{ item }"
          ><span class="item-icon-leading"
            ><ItemIcon
              :item="item"
              :size="density === 'comfortable' ? 34 : 30" /></span
          ><strong>{{ item.PERSONAL_PSEU || item.NAME }}</strong
          ><span>{{ item.TRASH_KIND }}</span
          ><span>×{{ item.QUANTITY || 1 }}</span></template
        ></ItemList
      >
    </section>
    <section>
      <h2>{{ $t("shop.workspace.archivedTemplates") }}</h2>
      <ItemList
        :items="archivedTemplateItems"
        item-key="ID"
        :density="density"
        :label="$t('shop.workspace.archivedTemplates')"
        :empty-label="$t('shop.workspace.empty')"
        @select="restoreTemplate"
        ><template #default="{ item }"
          ><span class="item-icon-leading"
            ><ItemIcon
              :item="item"
              :size="density === 'comfortable' ? 34 : 30" /></span
          ><strong>{{ item.NAME }}</strong
          ><span>{{ domainLabel("classes", item.ITEM_CLASS) }}</span
          ><span>{{ $t("actions.restore") }}</span></template
        ></ItemList
      >
    </section>
  </div>
</template>
<script>
import ItemList from "@/components/shop/common/ItemList.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceArchive",
  components: { ItemList, ItemIcon },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
