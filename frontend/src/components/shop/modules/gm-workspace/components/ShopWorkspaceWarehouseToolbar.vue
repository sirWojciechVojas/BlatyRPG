<!-- Panel GM sklepu: ShopWorkspaceWarehouseToolbar. -->
<template>
  <CompactToolbar :label="$t('shop.workspace.warehouse')"
    ><button
      type="button"
      :class="{ active: warehouseTab === 'items' }"
      @click="activateWarehouseItems"
    >
      {{ $t("shop.workspace.items") }}</button
    ><button
      type="button"
      :class="{ active: warehouseTab === 'archive' }"
      @click="openArchive"
    >
      {{ $t("shop.workspace.archive") }}</button
    ><input
      v-model.trim="warehouseQuery"
      type="search"
      :placeholder="$t('ui.search')"
    /><template #actions
      ><template v-if="warehouseTab === 'items' && warehouseSelection.length">
        <select
          v-model.number="warehouseTargetId"
          class="form-select form-select-sm gm-combobox gm-combobox--location"
          :aria-label="$t('shop.workspace.moveTarget')"
        >
          <option :value="null">
            {{ $t("shop.workspace.moveTarget") }}
          </option>
          <option
            v-for="container in warehouseContainerOptions"
            :key="container.id"
            :value="container.id"
          >
            {{ container.name }}
          </option>
        </select>
        <button
          type="button"
          :disabled="!warehouseTargetId"
          @click="moveWarehouseSelection(warehouseTargetId)"
        >
          {{ $t("shop.workspace.moveSelected") }}
        </button>
      </template>
      <button
        v-if="warehouseTab === 'items' && warehouseSelection.length === 1"
        type="button"
        @click="editSelectedStackInstance"
      >
        {{ $t("actions.edit") }}
      </button>
      <button
        v-if="warehouseTab === 'items' && warehouseSelection.length"
        type="button"
        @click="archiveWarehouseSelection"
      >
        {{
          $t("shop.workspace.archiveSelected", {
            count: warehouseSelection.length,
          })
        }}
      </button></template
    ></CompactToolbar
  >
  <nav
    v-if="warehouseTab === 'items'"
    class="instance-filter-tabs nav nav-pills"
    :aria-label="$t('shop.workspace.instanceStack.filters')"
  >
    <button
      v-for="filter in instanceLocationFilters"
      :key="filter.id"
      type="button"
      class="nav-link"
      :class="{ active: instanceLocationFilter === filter.id }"
      @click="instanceLocationFilter = filter.id"
    >
      {{ $t(filter.label) }}
      <span class="badge rounded-pill">{{
        instanceFilterCount(filter.id)
      }}</span>
    </button>
  </nav>
</template>
<script>
import CompactToolbar from "@/components/shop/common/CompactToolbar.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceWarehouseToolbar",
  components: { CompactToolbar },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
