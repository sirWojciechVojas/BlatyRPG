<!-- Panel GM sklepu: ShopWorkspaceWarehouse. -->
<template>
  <section class="shop-workspace__body shop-workspace__body--warehouse">
    <ShopWorkspaceWarehouseToolbar />
    <ShopWorkspaceWarehouseItems v-if="warehouseTab === 'items'" />
    <ShopWorkspaceArchive v-else />
  </section>
</template>
<script>
import ShopWorkspaceArchive from "./ShopWorkspaceArchive.vue";
import ShopWorkspaceWarehouseItems from "./ShopWorkspaceWarehouseItems.vue";
import ShopWorkspaceWarehouseToolbar from "./ShopWorkspaceWarehouseToolbar.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceWarehouse",
  components: {
    ShopWorkspaceArchive,
    ShopWorkspaceWarehouseItems,
    ShopWorkspaceWarehouseToolbar,
  },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
