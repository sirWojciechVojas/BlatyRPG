<!-- Panel GM sklepu: ShopWorkspaceLedger. -->
<template>
  <section
    class="shop-workspace__body shop-workspace__body--full history-panel"
  >
    <CompactToolbar :label="$t('shop.workspace.history')"
      ><input
        v-model.trim="ledgerFilters.item"
        type="search"
        :placeholder="$t('ui.search')"
      /><select
        v-model="ledgerFilters.transactionType"
        class="form-select form-select-sm gm-combobox gm-combobox--filter"
      >
        <option value="">{{ $t("shop.workspace.allTypes") }}</option>
        <option value="BUY">{{ $t("actions.buy") }}</option>
        <option value="SELL">{{ $t("actions.sell") }}</option></select
      ><template #actions
        ><button type="button" @click="loadLedger(1)">
          {{ $t("actions.refresh") }}</button
        ><button type="button" @click="exportLedgerCsv">CSV</button></template
      ></CompactToolbar
    >
    <div class="history-table-wrap">
      <table class="history-table">
        <colgroup>
          <col class="history-table__col-id" />
          <col class="history-table__col-date" />
          <col class="history-table__col-shop" />
          <col class="history-table__col-actor" />
          <col class="history-table__col-type" />
          <col class="history-table__col-status" />
          <col class="history-table__col-party" />
          <col class="history-table__col-party" />
          <col class="history-table__col-item" />
          <col class="history-table__col-quantity" />
          <col class="history-table__col-total" />
          <col class="history-table__col-currency" />
        </colgroup>
        <thead>
          <tr>
            <th>{{ $t("shop.workspace.id") }}</th>
            <th>{{ $t("shop.workspace.date") }}</th>
            <th>{{ $t("shop.workspace.shopLabel") }}</th>
            <th>{{ $t("shop.workspace.actor") }}</th>
            <th>{{ $t("shop.workspace.type") }}</th>
            <th>{{ $t("shop.workspace.status") }}</th>
            <th>{{ $t("shop.workspace.seller") }}</th>
            <th>{{ $t("shop.workspace.buyer") }}</th>
            <th>{{ $t("shop.workspace.item.name") }}</th>
            <th class="history-table__align-center">
              {{ $t("shop.workspace.quantity") }}
            </th>
            <th class="history-table__align-right">
              {{ $t("shop.workspace.total") }}
            </th>
            <th>{{ $t("shop.workspace.currency") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="entry in ledgerItems"
            :key="entry.id"
            class="history-table__row"
            tabindex="0"
            @click="openLedgerEntry(entry)"
            @keydown.enter.prevent="openLedgerEntry(entry)"
            @keydown.space.prevent="openLedgerEntry(entry)"
          >
            <td class="history-table__id">#{{ entry.id }}</td>
            <td class="history-table__date" :title="entry.createdAt">
              {{ formatDate(entry.createdAt) }}
            </td>
            <td :title="entry.shopName || entry.shopId || '—'">
              {{ entry.shopName || entry.shopId || "—" }}
            </td>
            <td :title="entry.actorName || entry.actorId || '—'">
              {{ entry.actorName || entry.actorId || "—" }}
            </td>
            <td class="history-table__type">{{ entry.transactionType }}</td>
            <td>
              <span
                class="history-table__status"
                :class="ledgerStatusClass(entry.status)"
              >
                <span aria-hidden="true">{{
                  ledgerStatusIcon(entry.status)
                }}</span>
                {{ ledgerStatusLabel(entry.status) }}
              </span>
            </td>
            <td :title="entry.sellerName || entry.sellerId || '—'">
              {{ entry.sellerName || entry.sellerId || "—" }}
            </td>
            <td :title="entry.buyerName || entry.buyerId || '—'">
              {{ entry.buyerName || entry.buyerId || "—" }}
            </td>
            <td class="history-table__item" :title="entry.itemName || '—'">
              <div class="history-table__item-cell">
                <ItemIcon :item="ledgerPrimaryItem(entry)" :size="23" />
                <span>{{ ledgerPrimaryItem(entry).NAME || "—" }}</span>
                <span
                  v-if="ledgerAdditionalItemCount(entry)"
                  class="history-table__more-items"
                  :title="entry.itemName"
                >
                  +{{ ledgerAdditionalItemCount(entry) }}
                </span>
              </div>
            </td>
            <td class="history-table__quantity">{{ entry.quantity }}</td>
            <td class="history-table__total">
              {{ formatLedgerTotal(entry) }}
            </td>
            <td
              class="history-table__currency"
              :title="ledgerCurrencyLabel(entry.currency)"
            >
              {{ ledgerCurrencyLabel(entry.currency) }}
            </td>
          </tr>
          <tr v-if="!ledgerItems.length">
            <td colspan="12" class="history-table__empty">
              {{ $t("shop.workspace.empty") }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <footer class="pagination">
      <button
        type="button"
        :disabled="!ledgerPagination.hasPreviousPage"
        @click="loadLedger(ledgerPagination.page - 1)"
      >
        ‹</button
      ><span
        >{{ ledgerPagination.page }} / {{ ledgerPagination.pageCount }}</span
      ><button
        type="button"
        :disabled="!ledgerPagination.hasNextPage"
        @click="loadLedger(ledgerPagination.page + 1)"
      >
        ›
      </button>
    </footer>
    <ShopWorkspaceLedgerDrawer />
  </section>
</template>
<script>
import CompactToolbar from "@/components/shop/common/CompactToolbar.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import ShopWorkspaceLedgerDrawer from "./ShopWorkspaceLedgerDrawer.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceLedger",
  components: { CompactToolbar, ItemIcon, ShopWorkspaceLedgerDrawer },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
