<!-- Panel GM sklepu: ShopWorkspaceOffer. -->
<template>
  <div class="workspace-list-panel offer-workspace">
    <CompactToolbar :label="$t('shop.workspace.offer')">
      <input
        v-model.trim="offerQuery"
        type="search"
        :placeholder="$t('ui.search')"
      />
      <select
        v-model="offerType"
        class="form-select form-select-sm gm-combobox gm-combobox--type"
      >
        <option value="">{{ $t("shop.workspace.allTypes") }}</option>
        <option v-for="value in itemClasses" :key="value" :value="value">
          {{ domainLabel("classes", value) }}
        </option>
      </select>
      <select
        v-model="offerSort"
        class="form-select form-select-sm gm-combobox gm-combobox--sort"
      >
        <option value="name">{{ $t("shop.workspace.sort.name") }}</option>
        <option value="price">{{ $t("shop.workspace.sort.price") }}</option>
        <option value="availability">
          {{ $t("shop.workspace.sort.availability") }}
        </option>
      </select>
      <select
        v-model="density"
        class="form-select form-select-sm gm-combobox gm-combobox--density"
      >
        <option value="compact">
          {{ $t("shop.workspace.density.compact") }}
        </option>
        <option value="comfortable">
          {{ $t("shop.workspace.density.comfortable") }}
        </option>
      </select>
      <template #actions>
        <template>
          <select
            v-model.number="offerTargetId"
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
            :disabled="!offerTargetId"
            @click="quickTransferOffer"
          >
            {{ $t("shop.workspace.quickTransfer") }}
          </button>
        </template>
        <button
          type="button"
          :class="{ active: suggestionsOpen }"
          @click="toggleSuggestions"
        >
          {{ $t("shop.workspace.suggestions") }}
          <span v-if="shopSuggestions.length" class="badge rounded-pill">{{
            shopSuggestions.length
          }}</span>
        </button>
        <button type="button" @click="previewStock">
          {{ $t("shop.workspace.stockPreview") }}
        </button>
      </template>
    </CompactToolbar>
    <div v-if="stockPreview.length" class="diff-strip">
      <span
        >{{ $t("shop.workspace.diff.added") }}:
        {{ stockDiff.added.length }}</span
      >
      <span
        >{{ $t("shop.workspace.diff.changed") }}:
        {{ stockDiff.changed.length }}</span
      >
      <span
        >{{ $t("shop.workspace.diff.removed") }}:
        {{ stockDiff.removed.length }}</span
      >
      <details>
        <summary>
          {{
            $t("shop.workspace.previewItems", {
              count: stockPreview.length,
            })
          }}
        </summary>
        <div class="diff-strip__details">
          <span v-for="entry in stockDiff.added" :key="'add-' + entry.key"
            >+ {{ entry.name }}</span
          >
          <span v-for="entry in stockDiff.changed" :key="'change-' + entry.key"
            >± {{ entry.name }}: {{ entry.before }} → {{ entry.after }}</span
          >
          <span v-for="entry in stockDiff.removed" :key="'remove-' + entry.key"
            >− {{ entry.name }}</span
          >
        </div>
      </details>
      <button type="button" class="primary" @click="applyStockPreview">
        {{ $t("actions.apply") }}
      </button>
      <button type="button" @click="stockPreview = []">
        {{ $t("actions.cancel") }}
      </button>
    </div>
    <div
      class="offer-workspace__content"
      :class="{
        'offer-workspace__content--with-suggestions': suggestionsOpen,
      }"
    >
      <section class="offer-workspace__instances">
        <header class="offer-workspace__section-title">
          <div>
            <strong>{{ $t("shop.workspace.offerInstances") }}</strong>
            <span>{{
              $t("shop.workspace.instanceCount", {
                count: activeOffer.length,
                groups: groupedOffer.length,
              })
            }}</span>
          </div>
          <div class="offer-workspace__selection-actions">
            <label>
              <input
                type="checkbox"
                :checked="allFilteredOfferSelected"
                :indeterminate="
                  someFilteredOfferSelected && !allFilteredOfferSelected
                "
                @change="toggleAllOfferItems"
              />
              <span>{{
                allFilteredOfferSelected
                  ? $t("shop.workspace.deselectAll")
                  : $t("shop.workspace.selectAll")
              }}</span>
            </label>
            <button
              type="button"
              class="danger"
              :disabled="!offerSelection.length || offerSelectionBusy"
              @click="moveOfferSelectionToTrash"
            >
              {{
                offerSelectionBusy
                  ? $t("shop.workspace.movingToTrash")
                  : $t("shop.workspace.moveToTrash", {
                      count: selectedOfferQuantity,
                    })
              }}
            </button>
          </div>
        </header>
        <div class="offer-table-head" aria-hidden="true">
          <span></span>
          <span>{{ $t("shop.workspace.item.name") }}</span>
          <span>{{ $t("shop.workspace.classification") }}</span>
          <span>{{ $t("shop.workspace.quantity") }}</span>
          <span>{{ $t("shop.workspace.item.price") }}</span>
          <span>{{ $t("shop.workspace.item.charge") }}</span>
          <span>ID</span>
          <span></span>
        </div>
        <ItemList
          :items="filteredOffer"
          item-key="OFFER_KEY"
          :selected-keys="offerSelection"
          :density="density"
          :label="$t('shop.workspace.offerInstances')"
          :empty-label="$t('shop.workspace.emptyOfferInstances')"
          @select="toggleSelection(offerSelection, $event.OFFER_KEY)"
          @details="showDetails"
        >
          <template #default="{ item }"
            ><span class="item-icon-leading"
              ><ItemIcon
                :item="item"
                :size="density === 'comfortable' ? 34 : 30"
              /><span
                v-if="offerSelection.includes(item.OFFER_KEY)"
                class="item-icon-check"
                >✓</span
              ></span
            ><strong>{{ itemDisplayName(item) }}</strong
            ><span class="offer-classification"
              ><strong>{{ domainLabel("classes", item.ITEM_CLASS) }}</strong
              ><small>{{ domainLabel("genres", item.ITEM_GENRE) }}</small></span
            ><strong class="offer-quantity">×{{ item.QUANTITY }}</strong
            ><span class="offer-price-quantity"
              ><CurrencyDisplay
                :brass="item.ACTIVE_PRICE ?? item.PRIZE"
                :currency-code="displayCurrencyCode(item.CURRENCY)"
                variant="row" /></span
            ><span class="offer-charge">{{ item.CHARGE }}</span
            ><small class="offer-id">#{{ item.ID }}</small></template
          >
        </ItemList>
      </section>

      <aside v-if="suggestionsOpen" class="suggestion-panel">
        <header>
          <div>
            <strong>{{ $t("shop.workspace.suggestionPanel.title") }}</strong>
            <small>{{ $t("shop.workspace.suggestionPanel.hint") }}</small>
          </div>
          <div class="suggestion-panel__header-actions">
            <button
              type="button"
              class="primary"
              :disabled="
                addingAllSuggestions ||
                !shopSuggestions.length ||
                Object.keys(suggestionOperations).length > 0
              "
              @click="applyAllSuggestions"
            >
              {{
                addingAllSuggestions
                  ? $t("shop.workspace.suggestionPanel.addingAll")
                  : $t("shop.workspace.suggestionPanel.addAll")
              }}
            </button>
            <button type="button" @click="generateSuggestions">
              {{ $t("actions.refresh") }}
            </button>
          </div>
        </header>
        <div v-if="shopSuggestions.length" class="suggestion-panel__list">
          <article
            v-for="suggestion in shopSuggestions"
            :key="suggestion.suggestionId"
            class="suggestion-row"
          >
            <ItemIcon :item="suggestionIconItem(suggestion)" :size="30" />
            <div>
              <strong>{{ suggestionName(suggestion) }}</strong>
              <small
                >{{ domainLabel("classes", suggestion.classKey) }} ·
                {{ suggestionReason(suggestion) }}</small
              >
            </div>
            <span class="suggestion-row__score">{{
              Math.round(Number(suggestion.score || 0))
            }}</span>
            <div class="suggestion-row__actions">
              <button
                type="button"
                :disabled="
                  addingAllSuggestions ||
                  isSuggestionBusy(suggestion) ||
                  suggestionHasTemplate(suggestion)
                "
                @click="createSuggestionTemplate(suggestion)"
              >
                {{
                  suggestionOperation(suggestion) === "template"
                    ? $t("shop.workspace.suggestionPanel.creatingTemplate")
                    : suggestionHasTemplate(suggestion)
                      ? $t("shop.workspace.suggestionPanel.templateReady")
                      : $t("shop.workspace.suggestionPanel.saveTemplate")
                }}
              </button>
              <button
                type="button"
                class="primary"
                :disabled="addingAllSuggestions || isSuggestionBusy(suggestion)"
                @click="applySingleSuggestion(suggestion)"
              >
                {{
                  suggestionOperation(suggestion) === "item"
                    ? $t("shop.workspace.suggestionPanel.adding")
                    : $t("shop.workspace.suggestionPanel.addOne")
                }}
              </button>
            </div>
          </article>
        </div>
        <div v-else class="suggestion-panel__empty">
          <span>{{ $t("shop.workspace.suggestionPanel.empty") }}</span>
          <button type="button" class="primary" @click="generateSuggestions">
            {{ $t("shop.workspace.suggestionPanel.generate") }}
          </button>
        </div>
      </aside>
    </div>
  </div>
</template>
<script>
import CompactToolbar from "@/components/shop/common/CompactToolbar.vue";
import ItemList from "@/components/shop/common/ItemList.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceOffer",
  components: { CompactToolbar, ItemList, ItemIcon, CurrencyDisplay },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
