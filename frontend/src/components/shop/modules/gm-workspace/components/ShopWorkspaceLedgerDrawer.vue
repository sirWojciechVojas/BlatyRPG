<!-- Detailed, half-screen transaction drawer for the visible GM ledger. -->
<template>
  <Teleport to="body">
    <Transition name="ledger-drawer">
      <div
        v-if="selectedLedgerEntry"
        class="ledger-drawer-backdrop"
        @click.self="closeLedgerEntry"
      >
        <aside
          class="ledger-drawer"
          role="dialog"
          aria-modal="true"
          tabindex="-1"
          :aria-label="$t('shop.workspace.ledgerDrawer.title')"
        >
          <header class="ledger-drawer__header">
            <div class="ledger-drawer__heading">
              <span
                class="history-table__status"
                :class="ledgerStatusClass(selectedLedgerEntry.status)"
              >
                <span aria-hidden="true">{{
                  ledgerStatusIcon(selectedLedgerEntry.status)
                }}</span>
                {{ ledgerStatusLabel(selectedLedgerEntry.status) }}
              </span>
              <h2>
                {{ $t("shop.workspace.ledgerDrawer.title") }}
                <small>#{{ selectedLedgerEntry.id }}</small>
              </h2>
              <p>
                {{ formatDate(selectedLedgerEntry.createdAt) }} ·
                {{ selectedLedgerEntry.transactionType }}
              </p>
            </div>
            <button
              type="button"
              class="ledger-drawer__close"
              :aria-label="$t('shop.workspace.ledgerDrawer.close')"
              :title="$t('shop.workspace.ledgerDrawer.close')"
              @click="closeLedgerEntry"
            >
              ×
            </button>
          </header>

          <div class="ledger-drawer__body">
            <section class="ledger-drawer__hero">
              <div>
                <span>{{ $t("shop.workspace.ledgerDrawer.value") }}</span>
                <strong>{{ formatLedgerTotal(selectedLedgerEntry) }}</strong>
              </div>
              <div>
                <span>{{ $t("shop.workspace.currency") }}</span>
                <strong>{{
                  ledgerCurrencyLabel(selectedLedgerEntry.currency)
                }}</strong>
              </div>
              <div>
                <span>{{ $t("shop.workspace.quantity") }}</span>
                <strong>{{ selectedLedgerEntry.quantity }}</strong>
              </div>
              <div>
                <span>{{ $t("shop.workspace.ledgerDrawer.lines") }}</span>
                <strong>{{
                  ledgerLineItems(selectedLedgerEntry).length
                }}</strong>
              </div>
            </section>

            <section class="ledger-drawer__section">
              <h3>{{ $t("shop.workspace.ledgerDrawer.items") }}</h3>
              <div class="ledger-drawer__items">
                <article
                  v-for="(item, index) in ledgerLineItems(selectedLedgerEntry)"
                  :key="`${item.templateId || 'item'}-${index}`"
                  class="ledger-drawer__item"
                >
                  <ItemIcon :item="item" :size="38" />
                  <div class="ledger-drawer__item-name">
                    <strong>{{ item.NAME }}</strong>
                    <small>
                      #{{ item.templateId || "—" }} · ×{{
                        item.quantity ?? "—"
                      }}
                    </small>
                  </div>
                  <div class="ledger-drawer__item-price">
                    <small>{{
                      $t("shop.workspace.ledgerDrawer.unitPrice")
                    }}</small>
                    <strong>{{
                      item.unitPrice === null
                        ? "—"
                        : formatLedgerAmount(item.unitPrice, item.currency)
                    }}</strong>
                  </div>
                  <div class="ledger-drawer__item-price">
                    <small>{{
                      $t("shop.workspace.ledgerDrawer.lineTotal")
                    }}</small>
                    <strong>{{
                      item.lineTotal === null
                        ? "—"
                        : formatLedgerAmount(item.lineTotal, item.currency)
                    }}</strong>
                  </div>
                </article>
              </div>
            </section>

            <section class="ledger-drawer__section">
              <h3>{{ $t("shop.workspace.ledgerDrawer.parties") }}</h3>
              <dl class="ledger-drawer__facts">
                <div>
                  <dt>{{ $t("shop.workspace.shopLabel") }}</dt>
                  <dd>
                    {{
                      selectedLedgerEntry.shopName ||
                      selectedLedgerEntry.shopId ||
                      "—"
                    }}
                  </dd>
                </div>
                <div>
                  <dt>{{ $t("shop.workspace.actor") }}</dt>
                  <dd>
                    {{
                      selectedLedgerEntry.actorName ||
                      selectedLedgerEntry.actorId ||
                      "—"
                    }}
                  </dd>
                </div>
                <div>
                  <dt>{{ $t("shop.workspace.seller") }}</dt>
                  <dd>
                    {{
                      selectedLedgerEntry.sellerName ||
                      selectedLedgerEntry.sellerId ||
                      "—"
                    }}
                  </dd>
                </div>
                <div>
                  <dt>{{ $t("shop.workspace.buyer") }}</dt>
                  <dd>
                    {{
                      selectedLedgerEntry.buyerName ||
                      selectedLedgerEntry.buyerId ||
                      "—"
                    }}
                  </dd>
                </div>
              </dl>
            </section>

            <section class="ledger-drawer__section">
              <h3>{{ $t("shop.workspace.ledgerDrawer.pricing") }}</h3>
              <dl class="ledger-drawer__facts ledger-drawer__facts--three">
                <div>
                  <dt>{{ $t("shop.workspace.ledgerDrawer.basePrice") }}</dt>
                  <dd>
                    {{
                      formatLedgerAmount(
                        selectedLedgerEntry.basePrice,
                        selectedLedgerEntry.currency,
                      )
                    }}
                  </dd>
                </div>
                <div>
                  <dt>{{ $t("shop.workspace.ledgerDrawer.finalPrice") }}</dt>
                  <dd>
                    {{
                      formatLedgerAmount(
                        selectedLedgerEntry.finalPrice,
                        selectedLedgerEntry.currency,
                      )
                    }}
                  </dd>
                </div>
                <div>
                  <dt>{{ $t("shop.workspace.ledgerDrawer.difference") }}</dt>
                  <dd>
                    {{
                      formatLedgerAmount(
                        selectedLedgerEntry.difference,
                        selectedLedgerEntry.currency,
                      )
                    }}
                  </dd>
                </div>
              </dl>
            </section>

            <section
              v-if="selectedLedgerEntry.history?.length"
              class="ledger-drawer__section"
            >
              <h3>{{ $t("shop.workspace.ledgerDrawer.history") }}</h3>
              <ol class="ledger-drawer__timeline">
                <li
                  v-for="(event, index) in selectedLedgerEntry.history"
                  :key="`${event.createdAt || event.date}-${index}`"
                >
                  <time>{{ event.createdAt || event.date || "—" }}</time>
                  <strong>{{ event.label || event.action || "—" }}</strong>
                  <p v-if="event.reason || event.note">
                    {{ event.reason || event.note }}
                  </p>
                </li>
              </ol>
            </section>

            <section class="ledger-drawer__section">
              <h3>{{ $t("shop.workspace.ledgerDrawer.audit") }}</h3>
              <dl class="ledger-drawer__facts ledger-drawer__facts--three">
                <div>
                  <dt>{{ $t("shop.workspace.ledgerDrawer.updated") }}</dt>
                  <dd>{{ formatDate(selectedLedgerEntry.updatedAt) }}</dd>
                </div>
                <div>
                  <dt>{{ $t("shop.workspace.ledgerDrawer.performedBy") }}</dt>
                  <dd>{{ selectedLedgerEntry.performedBy || "—" }}</dd>
                </div>
                <div>
                  <dt>{{ $t("shop.workspace.ledgerDrawer.parent") }}</dt>
                  <dd>{{ selectedLedgerEntry.parentTransactionId || "—" }}</dd>
                </div>
              </dl>
              <p v-if="selectedLedgerEntry.correctionReason">
                <strong
                  >{{ $t("shop.workspace.ledgerDrawer.correction") }}:</strong
                >
                {{ selectedLedgerEntry.correctionReason }}
              </p>
              <p v-if="selectedLedgerEntry.gmNote">
                <strong>{{ $t("shop.workspace.ledgerDrawer.note") }}:</strong>
                {{ selectedLedgerEntry.gmNote }}
              </p>
            </section>

            <details class="ledger-drawer__technical">
              <summary>
                {{ $t("shop.workspace.ledgerDrawer.technical") }}
              </summary>
              <h4>{{ $t("shop.workspace.ledgerDrawer.modifiers") }}</h4>
              <pre>{{
                formatLedgerJson(selectedLedgerEntry.priceModifiers)
              }}</pre>
              <h4>{{ $t("shop.workspace.ledgerDrawer.conditions") }}</h4>
              <pre>{{
                formatLedgerJson(selectedLedgerEntry.conditionsSnapshot)
              }}</pre>
              <h4>{{ $t("shop.workspace.ledgerDrawer.before") }}</h4>
              <pre>{{
                formatLedgerJson(selectedLedgerEntry.beforeSnapshot)
              }}</pre>
              <h4>{{ $t("shop.workspace.ledgerDrawer.after") }}</h4>
              <pre>{{
                formatLedgerJson(selectedLedgerEntry.afterSnapshot)
              }}</pre>
            </details>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
import { nextTick, onBeforeUnmount, onMounted, watch } from "vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceLedgerDrawer",
  components: { ItemIcon },
  setup() {
    const workspace = useShopWorkspaceContext();
    let previousFocus = null;
    const onKeydown = (event) => {
      if (event.key === "Escape" && workspace.selectedLedgerEntry.value) {
        workspace.closeLedgerEntry();
      }
    };
    watch(workspace.selectedLedgerEntry, async (selected) => {
      if (selected) {
        previousFocus = document.activeElement;
        await nextTick();
        document.querySelector(".ledger-drawer")?.focus();
        return;
      }
      await nextTick();
      previousFocus?.focus?.();
      previousFocus = null;
    });
    onMounted(() => window.addEventListener("keydown", onKeydown));
    onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));
    return workspace;
  },
};
</script>
