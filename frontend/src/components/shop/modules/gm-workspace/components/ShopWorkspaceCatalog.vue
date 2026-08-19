<!-- Panel GM sklepu: ShopWorkspaceCatalog. -->
<template>
  <section
    class="shop-workspace__body shop-workspace__body--catalog catalog-workspace"
  >
    <header class="catalog-titlebar">
      <div>
        <h2>{{ catalogModeTitle }}</h2>
        <p>{{ catalogModeDescription }}</p>
      </div>
      <nav :aria-label="$t('shop.workspace.catalogModes.navigation')">
        <button
          v-for="mode in catalogModes"
          :key="mode.id"
          type="button"
          :class="{ active: catalogMode === mode.id }"
          @click="setCatalogMode(mode.id)"
        >
          {{ $t(mode.label) }}
        </button>
      </nav>
    </header>
    <template v-if="catalogMode !== 'dictionaries'">
      <ShopWorkspaceCatalogBrowser />
      <ShopWorkspaceTemplateEditor v-if="catalogMode === 'templates'" />
      <ShopWorkspaceInstanceEditor v-else />
    </template>
    <ShopWorkspaceDictionaries v-else />
  </section>
</template>
<script>
import ShopWorkspaceCatalogBrowser from "./ShopWorkspaceCatalogBrowser.vue";
import ShopWorkspaceDictionaries from "./ShopWorkspaceDictionaries.vue";
import ShopWorkspaceInstanceEditor from "./ShopWorkspaceInstanceEditor.vue";
import ShopWorkspaceTemplateEditor from "./ShopWorkspaceTemplateEditor.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceCatalog",
  components: {
    ShopWorkspaceCatalogBrowser,
    ShopWorkspaceDictionaries,
    ShopWorkspaceInstanceEditor,
    ShopWorkspaceTemplateEditor,
  },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
