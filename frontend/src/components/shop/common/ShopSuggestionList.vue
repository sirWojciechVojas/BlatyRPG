<!-- Responsibility: ShopSuggestionList shop interface component. -->
<template>
  <div class="shop-editor-suggestions">
    <div
      v-for="entry in entries"
      :key="entry.suggestionId"
      class="shop-suggestion-row"
      :class="rowClasses(entry)"
      @click="handleClick(entry)"
      @contextmenu.prevent.stop="handleContext(entry)"
    >
      <div class="shop-suggestion-main">
        <span
          class="shop-suggestion-icon trade-icon inventory-item legacy-inventory-icon"
          :class="ctx.suggestionIconClass(entry)"
          role="img"
          :aria-label="ctx.suggestionDisplayName(entry)"
        ></span>
        <div class="shop-suggestion-texts">
          <div class="shop-suggestion-title">
            {{ ctx.suggestionDisplayName(entry) }}
          </div>
          <div class="shop-suggestion-desc">
            {{ ctx.suggestionDescription(entry) }}
          </div>
        </div>
      </div>
      <div class="shop-suggestion-meta">
        <span class="badge" :class="ctx.recommendationBadgeClass(entry)">
          {{ ctx.recommendationLabel(entry) }}
        </span>
        <span class="badge bg-secondary">
          {{ $t("shop.common.scorePrefix") }}:
          {{ Number(entry.score || 0).toFixed(2) }}
        </span>
        <span v-if="entry.classKey" class="badge bg-dark">
          {{ $t("shop.common.classPrefix") }} {{ entry.classKey }}
        </span>
        <span v-if="entry.genreKey" class="badge bg-dark">
          {{ $t("shop.common.genrePrefix") }} {{ entry.genreKey }}
        </span>
        <span
          v-if="showDraftBadge && entry.action === 'create_draft'"
          class="badge bg-warning text-dark"
        >
          {{ draftBadgeLabel }}
        </span>
        <span class="badge bg-dark">
          {{ $t("shop.common.qtyPrefix") }}: {{ Number(entry.quantity || 1) }}
        </span>
      </div>
      <div class="shop-suggestion-reason">
        {{ reasonText(entry) }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

const props = defineProps({
  entries: {
    type: Array,
    default: () => [],
  },
  selectedIds: {
    type: Array,
    default: () => [],
  },
  selectable: {
    type: Boolean,
    default: false,
  },
  recommendationOnly: {
    type: Boolean,
    default: false,
  },
  showDraftBadge: {
    type: Boolean,
    default: true,
  },
  draftBadgeLabel: {
    type: String,
    default: "",
  },
  openDetailOnContext: {
    type: Boolean,
    default: false,
  },
  useEntryReason: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["toggle", "open-detail"]);

const ctx = useTradeModalContext();
const t = ctx.$t ? ctx.$t.bind(ctx) : (key) => key;

function rowClasses(entry) {
  const suggestionId = String(entry?.suggestionId || "");
  return {
    "shop-suggestion-row--selectable": props.selectable,
    "shop-suggestion-row--recommendation": props.recommendationOnly,
    selected: props.selectable && props.selectedIds.includes(suggestionId),
  };
}

function handleClick(entry) {
  if (!props.selectable) {
    return;
  }
  emit("toggle", String(entry?.suggestionId || ""));
}

function handleContext(entry) {
  if (!props.openDetailOnContext) {
    return;
  }
  emit("open-detail", entry);
}

function reasonText(entry) {
  if (props.useEntryReason && entry?.recommendationReasonPl) {
    return entry.recommendationReasonPl;
  }
  return ctx.resolveShopSuggestionReason(entry);
}

const draftBadgeLabel = computed(
  () => props.draftBadgeLabel || t("shop.common.draftBadge"),
);
</script>
