<!-- Komponent modułu Sklep - ustal asortyment. Ten plik renderuje listę kart przedmiotów wykorzystywaną przez widoki źródła i celu asortymentu. -->
<template>
  <div class="assort-panel-list shop-mode-template-list">
    <div
      v-for="item in items"
      :key="`${side}-${item.key}`"
      class="assort-card assort-card--selectable"
      :class="{ selected: ctx.isContainerSelected(item.key, side) }"
      @click="ctx.toggleContainerSelection(item.key, side)"
    >
      <div class="assort-card-main">
        <span
          class="assort-card-icon trade-icon inventory-item legacy-inventory-icon"
          :class="ctx.legacyIconClassForItem(item)"
          role="img"
          :aria-label="item.name"
        ></span>
        <div class="assort-card-info">
          <div class="assort-card-title">
            <span class="assort-card-name">{{ item.name }}</span>
            <span v-if="item.badge" class="assort-card-badge">
              {{ item.badge }}
            </span>
          </div>
          <div v-if="item.description" class="assort-card-desc">
            {{ item.description }}
          </div>
          <div
            v-if="item.stackCandidateCount > 1"
            class="assort-card-stack-hint"
          >
            {{
              $t("shop.assortment.stackCandidateLabel", {
                count: item.stackCandidateCount,
              })
            }}
          </div>
        </div>
        <div v-if="item.quantityLabel" class="assort-card-qty">
          {{ item.quantityLabel }}
        </div>
      </div>
    </div>

    <div v-if="!items.length" class="assort-empty">
      {{ $t("shop.common.emptyItems") }}
    </div>
  </div>
</template>

<script setup>
import { useTradeModalContext } from "@/components/shop/shopContext";

defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  side: {
    type: String,
    required: true,
  },
});

const ctx = useTradeModalContext();
</script>
