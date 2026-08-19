<!-- Panel GM sklepu: ShopWorkspaceHeader. -->
<template>
  <header class="shop-workspace__header">
    <router-link to="/" class="shop-workspace__brand">BR</router-link>
    <h1>{{ $t("shop.workspace.title") }}</h1>
    <nav :aria-label="$t('shop.workspace.navigation')">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        :class="{ active: activeModule === tab.id }"
        :title="$t(tab.title || tab.label)"
        @click="activateModule(tab)"
      >
        {{ $t(tab.label) }}
      </button>
    </nav>
    <StatusChip
      v-if="['loading', 'error', 'offline'].includes(apiStatus)"
      :label="apiStatusLabel"
      :tone="apiStatus === 'error' ? 'danger' : 'info'"
      dot
    />
  </header>
</template>
<script>
import StatusChip from "@/components/shop/common/StatusChip.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceHeader",
  components: { StatusChip },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
