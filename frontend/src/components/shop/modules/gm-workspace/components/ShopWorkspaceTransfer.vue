<!-- Panel GM sklepu: ShopWorkspaceTransfer. -->
<template>
  <section
    class="shop-workspace__body shop-workspace__body--full transfer-workspace"
  >
    <CompactToolbar :label="$t('shop.workspace.transfer.title')">
      <span class="transfer-workspace__label">{{
        $t("shop.workspace.transfer.from")
      }}</span>
      <DomainCombobox
        v-model="transferSourceId"
        :options="warehouseContainerOptions"
        :placeholder="$t('shop.workspace.transfer.selectSource')"
        tone="location"
        :include-code="false"
      />
      <button
        type="button"
        :title="$t('shop.workspace.transfer.swap')"
        :disabled="!transferSourceId || !transferTargetId"
        @click="swapTransferContainers"
      >
        ⇄
      </button>
      <span class="transfer-workspace__label">{{
        $t("shop.workspace.transfer.to")
      }}</span>
      <DomainCombobox
        v-model="transferTargetId"
        :options="transferTargetOptions"
        :placeholder="$t('shop.workspace.transfer.selectTarget')"
        tone="location"
        :include-code="false"
      />
      <input
        v-model.trim="transferQuery"
        type="search"
        :placeholder="$t('shop.workspace.transfer.searchSource')"
      />
      <template #actions>
        <StatusChip
          :label="
            $t('shop.workspace.transfer.selected', {
              count: transferSelection.length,
            })
          "
          :tone="transferSelection.length ? 'warning' : 'neutral'"
        />
        <button
          type="button"
          :disabled="!transferSelection.length"
          @click="clearTransferSelection"
        >
          {{ $t("shop.workspace.transfer.clear") }}
        </button>
        <button
          type="button"
          class="primary"
          :disabled="!canApplyTransfer"
          @click="applyTransferPreview"
        >
          {{
            transferSaving
              ? $t("shop.workspace.transfer.saving")
              : $t("shop.workspace.transfer.apply", {
                  count: transferSelection.length,
                })
          }}
        </button>
      </template>
    </CompactToolbar>

    <div class="transfer-workspace__grid">
      <section class="transfer-panel">
        <header>
          <div>
            <strong>{{ transferSourceName }}</strong>
            <small>{{
              $t("shop.workspace.transfer.sourceHint", {
                count: transferSourceItems.length,
              })
            }}</small>
          </div>
        </header>
        <ItemList
          :items="filteredTransferSourceItems"
          item-key="ID"
          :selected-keys="transferSelection"
          :density="density"
          :label="$t('shop.workspace.transfer.sourceItems')"
          :empty-label="$t('shop.workspace.transfer.emptySource')"
          @select="toggleSelection(transferSelection, $event.ID)"
          @details="showDetails"
        >
          <template #default="{ item }"
            ><span class="item-icon-leading"
              ><ItemIcon :item="item" :size="30" /><span
                v-if="transferSelection.includes(item.ID)"
                class="item-icon-check"
                >✓</span
              ></span
            ><strong>{{ itemDisplayName(item) }}</strong
            ><span>{{ domainLabel("classes", item.ITEM_CLASS) }}</span
            ><span>#{{ item.ID }}</span></template
          >
        </ItemList>
      </section>

      <section class="transfer-panel transfer-panel--preview">
        <header>
          <div>
            <strong>{{ $t("shop.workspace.transfer.previewTitle") }}</strong>
            <small>{{ $t("shop.workspace.transfer.previewHint") }}</small>
          </div>
        </header>
        <div v-if="transferPreviewItems.length" class="transfer-preview-list">
          <article v-for="item in transferPreviewItems" :key="item.ID">
            <ItemIcon :item="item" :size="30" />
            <div>
              <strong>{{ itemDisplayName(item) }}</strong>
              <small
                >{{ transferSourceName }} <span aria-hidden="true">→</span>
                {{ transferTargetName }}</small
              >
            </div>
            <button
              type="button"
              :aria-label="$t('shop.workspace.transfer.removeFromPreview')"
              @click="toggleSelection(transferSelection, item.ID)"
            >
              ×
            </button>
          </article>
        </div>
        <div v-else class="transfer-panel__empty">
          {{ $t("shop.workspace.transfer.emptyPreview") }}
        </div>
      </section>

      <section class="transfer-panel">
        <header>
          <div>
            <strong>{{ transferTargetName }}</strong>
            <small>{{
              $t("shop.workspace.transfer.targetHint", {
                before: transferTargetItems.length,
                after: transferTargetItems.length + transferSelection.length,
              })
            }}</small>
          </div>
        </header>
        <ItemList
          :items="transferTargetItems"
          item-key="ID"
          :density="density"
          :label="$t('shop.workspace.transfer.targetItems')"
          :empty-label="$t('shop.workspace.transfer.emptyTarget')"
          @select="showDetails"
          @details="showDetails"
        >
          <template #default="{ item }"
            ><span class="item-icon-leading"
              ><ItemIcon :item="item" :size="30" /></span
            ><strong>{{ itemDisplayName(item) }}</strong
            ><span>{{ domainLabel("classes", item.ITEM_CLASS) }}</span
            ><span>#{{ item.ID }}</span></template
          >
        </ItemList>
      </section>
    </div>
  </section>
</template>
<script>
import CompactToolbar from "@/components/shop/common/CompactToolbar.vue";
import DomainCombobox from "@/components/shop/common/DomainCombobox.vue";
import StatusChip from "@/components/shop/common/StatusChip.vue";
import ItemList from "@/components/shop/common/ItemList.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceTransfer",
  components: {
    CompactToolbar,
    DomainCombobox,
    StatusChip,
    ItemList,
    ItemIcon,
  },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
