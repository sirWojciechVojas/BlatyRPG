<template>
  <div v-if="previewLoading" class="shop-editor-pricing__empty">
    {{ $t("shop.shopEditor.pricing.simulator.calculating") }}
  </div>
  <div v-else-if="previewError" class="shop-editor-pricing__currency-warning">
    {{ $t("shop.shopEditor.pricing.simulator.error") }}: {{ previewError }}
  </div>
  <div
    v-else-if="!pricePreview?.items?.length"
    class="shop-editor-pricing__empty"
  >
    {{ $t("shop.shopEditor.pricing.emptyPreview") }}
  </div>
  <template v-else>
    <nav
      class="shop-editor-pricing__result-tabs"
      role="tablist"
      :aria-label="$t('shop.shopEditor.pricing.simulator.resultTabs')"
    >
      <button
        v-for="view in ['summary', 'breakdown']"
        :key="view"
        type="button"
        role="tab"
        :class="{ active: previewResultsView === view }"
        :aria-selected="previewResultsView === view"
        @click="previewResultsView = view"
      >
        {{ $t(`shop.shopEditor.pricing.simulator.views.${view}`) }}
      </button>
    </nav>
    <div
      v-if="previewResultsView === 'summary'"
      class="shop-editor-pricing__simulator-results"
    >
      <article
        v-for="item in pricePreview.items"
        :key="item.templateId"
        class="shop-editor-pricing__result"
      >
        <header>
          <strong>{{ item.templateName }}</strong>
          <span :class="deltaClass(item.difference.amount)">
            {{ formatPrice(item.after.finalPrice) }}
            <small v-if="pricePreview.hasUnsavedChanges">
              ({{ formatDelta(item.difference.amount) }},
              {{ signedPercent(item.difference.percent) }})
            </small>
          </span>
        </header>
        <div class="shop-editor-pricing__result-metrics">
          <span v-for="metric in resultMetrics(item)" :key="metric.key">
            {{ metric.label }} <b>{{ metric.value }}</b>
          </span>
        </div>
      </article>
    </div>
    <div v-else class="shop-editor-pricing__breakdown-view">
      <header>
        <select
          v-if="pricePreview.items.length > 1"
          v-model="selectedPreviewItemId"
          :aria-label="$t('shop.shopEditor.pricing.simulator.breakdownItem')"
        >
          <option
            v-for="item in pricePreview.items"
            :key="`breakdown-${item.templateId}`"
            :value="String(item.templateId)"
          >
            {{ item.templateName }}
          </option>
        </select>
        <strong v-else>{{ selectedPreviewItem?.templateName }}</strong>
        <nav
          v-if="breakdownPages.length > 1"
          :aria-label="$t('shop.shopEditor.pricing.simulator.breakdownStages')"
        >
          <button
            v-for="(page, index) in breakdownPages"
            :key="`breakdown-page-${index}`"
            type="button"
            :class="{ active: breakdownPage === index }"
            @click="breakdownPage = index"
          >
            {{ page[0].order }}–{{ page[page.length - 1].order }}
          </button>
        </nav>
      </header>
      <ol class="shop-editor-pricing__breakdown">
        <li
          v-for="row in activeBreakdownRows"
          :key="`${selectedPreviewItem.templateId}-${row.order}-${row.key}`"
          :class="{ skipped: !row.applied }"
        >
          <span class="shop-editor-pricing__breakdown-order">{{
            row.order
          }}</span>
          <span class="shop-editor-pricing__breakdown-copy">
            <b>{{ row.name }}</b>
            <small>{{ reasonLabel(row) }} · {{ breakdownMeta(row) }}</small>
          </span>
          <span :class="deltaClass(row.amountChange)">
            {{ row.applied ? signedPercent(row.percentChange) : "—" }}
            <small>
              {{
                row.applied
                  ? formatDelta(row.amountChange)
                  : $t("shop.shopEditor.pricing.simulator.skipped")
              }}
            </small>
          </span>
        </li>
      </ol>
    </div>
  </template>
</template>

<script>
import { computed, ref, watch } from "vue";
import { useShopPricingContext } from "../../shopPricingContext";

export default {
  name: "ShopPricingPreviewResults",
  setup() {
    const context = useShopPricingContext();
    const previewResultsView = ref("summary");
    const selectedPreviewItemId = ref("");
    const breakdownPage = ref(0);
    const previewItems = computed(
      () => context.pricePreview.value?.items || [],
    );
    const selectedPreviewItem = computed(
      () =>
        previewItems.value.find(
          (item) => String(item.templateId) === selectedPreviewItemId.value,
        ) ||
        previewItems.value[0] ||
        null,
    );
    const breakdownPages = computed(() => {
      const rows = selectedPreviewItem.value?.after?.breakdown || [];
      return Array.from({ length: Math.ceil(rows.length / 5) }, (_, index) =>
        rows.slice(index * 5, index * 5 + 5),
      );
    });
    const activeBreakdownRows = computed(
      () => breakdownPages.value[breakdownPage.value] || [],
    );
    watch(
      previewItems,
      (items) => {
        if (
          !items.some(
            (item) => String(item.templateId) === selectedPreviewItemId.value,
          )
        ) {
          selectedPreviewItemId.value = String(items[0]?.templateId || "");
        }
        breakdownPage.value = 0;
      },
      { immediate: true },
    );
    watch(selectedPreviewItemId, () => {
      breakdownPage.value = 0;
    });
    const signedPercent = (value) => {
      const number = Number(value || 0);
      return `${number > 0 ? "+" : ""}${number.toFixed(1)}%`;
    };
    const resultMetrics = (item) => {
      const t = context.t;
      return [
        [
          "catalog",
          t("shop.shopEditor.pricing.simulator.catalog"),
          context.formatPrice(item.after.catalogPrice),
        ],
        [
          "converted",
          t("shop.shopEditor.pricing.simulator.converted"),
          context.formatPrice(item.after.basePrice),
        ],
        [
          "base",
          t("shop.shopEditor.pricing.simulator.preModifiers"),
          context.formatPrice(item.after.priceBeforeModifiers),
        ],
        ...(context.pricePreview.value.hasUnsavedChanges
          ? [
              [
                "saved",
                t("shop.shopEditor.pricing.simulator.savedPrice"),
                context.formatPrice(item.before.finalPrice),
              ],
            ]
          : []),
        [
          "unit",
          t("shop.shopEditor.pricing.simulator.draftUnitPrice"),
          context.formatPrice(item.after.finalPrice),
        ],
        [
          "total",
          t("shop.shopEditor.pricing.simulator.total"),
          context.formatPrice(item.after.totalPrice),
        ],
        [
          "stock",
          t("shop.shopEditor.pricing.stockQuantity"),
          item.stockQuantity === null ? "∞" : item.stockQuantity,
        ],
        [
          "availability",
          t("shop.shopEditor.pricing.simulator.availability"),
          `${item.after.availabilityChance}%`,
        ],
      ].map(([key, label, value]) => ({ key, label, value }));
    };
    return {
      ...context,
      previewResultsView,
      selectedPreviewItemId,
      selectedPreviewItem,
      breakdownPages,
      breakdownPage,
      activeBreakdownRows,
      signedPercent,
      resultMetrics,
    };
  },
};
</script>
