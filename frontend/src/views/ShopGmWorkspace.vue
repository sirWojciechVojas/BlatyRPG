<!-- Panel GM sklepu: punkt wejścia i lazy-loaded skład zakładek. -->
<template>
  <main
    v-if="workspaceReady"
    class="shop-workspace"
    :style="{ '--shop-wood-background': `url(${woodBackground})` }"
  >
    <ShopWorkspaceHeader />
    <ShopWorkspaceShops v-if="activeTab === 'shops'" />
    <ShopWorkspaceCatalog v-else-if="activeTab === 'catalog'" />
    <ShopWorkspaceWarehouse v-else-if="activeTab === 'warehouse'" />
    <ShopWorkspaceTransfer v-else-if="activeTab === 'transfer'" />
    <ShopWorkspaceLedger v-else />
    <ShopWorkspaceDialogs />
  </main>
  <section v-else class="shop-workspace__loading" role="status">
    <span>{{ workspaceError || $t("ui.loading") }}</span>
    <button v-if="workspaceError" type="button" @click="initializeWorkspace">
      {{ $t("shop.tradeModal.retryLoad") }}
    </button>
  </section>
</template>

<script>
import { provide } from "vue";
import woodBackground from "@/assets/app-ui/img/bg-trading.jpg";
import ShopWorkspaceHeader from "@/components/shop/modules/gm-workspace/components/ShopWorkspaceHeader.vue";
import ShopWorkspaceShops from "@/components/shop/modules/gm-workspace/components/ShopWorkspaceShops.vue";
import ShopWorkspaceCatalog from "@/components/shop/modules/gm-workspace/components/ShopWorkspaceCatalog.vue";
import ShopWorkspaceWarehouse from "@/components/shop/modules/gm-workspace/components/ShopWorkspaceWarehouse.vue";
import ShopWorkspaceTransfer from "@/components/shop/modules/gm-workspace/components/ShopWorkspaceTransfer.vue";
import ShopWorkspaceLedger from "@/components/shop/modules/gm-workspace/components/ShopWorkspaceLedger.vue";
import ShopWorkspaceDialogs from "@/components/shop/modules/gm-workspace/components/ShopWorkspaceDialogs.vue";
import { shopWorkspaceKey } from "@/components/shop/modules/gm-workspace/shopWorkspaceContext";
import { useShopGmWorkspace } from "@/components/shop/modules/gm-workspace/composables/useShopGmWorkspace";

export default {
  name: "ShopGmWorkspace",
  components: {
    ShopWorkspaceHeader,
    ShopWorkspaceShops,
    ShopWorkspaceCatalog,
    ShopWorkspaceWarehouse,
    ShopWorkspaceTransfer,
    ShopWorkspaceLedger,
    ShopWorkspaceDialogs,
  },
  setup() {
    const workspace = useShopGmWorkspace();
    workspace.woodBackground = woodBackground;
    provide(shopWorkspaceKey, workspace);
    return workspace;
  },
};
</script>

<style
  src="@/components/shop/modules/gm-workspace/styles/ShopGmWorkspace.1.css"
></style>
<style
  src="@/components/shop/modules/gm-workspace/styles/ShopGmWorkspace.2.css"
></style>
<style
  src="@/components/shop/modules/gm-workspace/styles/ShopGmWorkspace.3.css"
></style>
<style
  src="@/components/shop/modules/gm-workspace/styles/ShopGmWorkspace.4.css"
></style>
<style
  src="@/components/shop/modules/gm-workspace/styles/ShopGmWorkspace.5.css"
></style>
<style
  src="@/components/shop/modules/gm-workspace/styles/ShopGmWorkspace.6.css"
></style>
