<!-- Responsibility: FlankActionButtons shop interface component. -->
<template>
  <div
    v-if="usesShopSignboards"
    class="shop-signboard-selector"
    :class="selectorClass"
  >
    <div class="shop-signboard-selector__list" role="list">
      <button
        v-for="(button, index) in pagedShopButtons"
        :key="button.shopId || `${button.label}-${index}`"
        type="button"
        class="shop-signboard-row"
        :class="[
          shopTypeIconClass(button.tileTypeLabel || button.shopTypeLabel),
          {
            active: button.active,
            'shop-signboard-row--active': button.active,
          },
        ]"
        :disabled="button.disabled"
        :title="button.title || button.label"
        role="listitem"
        @click="$emit('action', button)"
      >
        <span class="shop-signboard-row__shine"></span>
        <span class="shop-signboard-row__icon" aria-hidden="true"></span>
        <span class="shop-signboard-row__text">
          <span class="shop-signboard-row__name">{{ button.label }}</span>
          <span class="shop-signboard-row__type">
            {{ button.tileTypeLabel || button.shopTypeLabel }}
          </span>
        </span>
      </button>
    </div>

    <div v-if="pageCount > 1" class="shop-signboard-selector__pager">
      <button
        type="button"
        class="shop-signboard-selector__page-btn"
        :disabled="currentPage === 0"
        title="Poprzednia strona szyldów"
        aria-label="Poprzednia strona szyldów"
        @click="goToPage(currentPage - 1)"
      >
        &lt;
      </button>
      <div class="shop-signboard-selector__dots" aria-label="Strony szyldów">
        <button
          v-for="page in pageCount"
          :key="page"
          type="button"
          class="shop-signboard-selector__dot"
          :class="{
            'shop-signboard-selector__dot--active': page - 1 === currentPage,
          }"
          :aria-label="`Strona szyldów ${page}`"
          :aria-current="page - 1 === currentPage ? 'page' : undefined"
          @click="goToPage(page - 1)"
        ></button>
      </div>
      <button
        type="button"
        class="shop-signboard-selector__page-btn"
        :disabled="currentPage >= pageCount - 1"
        title="Następna strona szyldów"
        aria-label="Następna strona szyldów"
        @click="goToPage(currentPage + 1)"
      >
        &gt;
      </button>
    </div>

    <aside
      v-if="selectedShopButton"
      class="shop-signboard-details"
      :title="selectedShopButton.title || selectedShopButton.label"
    >
      <div class="shop-signboard-details__head">
        <div class="shop-signboard-details__identity">
          <strong>{{ selectedShopButton.label }}</strong>
          <span>{{ selectedShopButton.shopTypeLabel }}</span>
        </div>
      </div>
      <dl
        v-if="selectedShopButton.detailRows?.length"
        class="shop-signboard-details__rows"
      >
        <template
          v-for="detail in selectedShopButton.detailRows"
          :key="detail.label"
        >
          <dt>{{ detail.label }}</dt>
          <dd>{{ detail.value }}</dd>
        </template>
      </dl>
      <p v-else class="shop-signboard-details__note">
        {{ selectedShopButton.subtitle }}
      </p>
    </aside>
  </div>

  <template v-else>
    <button
      v-for="(button, index) in buttons"
      :key="
        button.shopId ||
        button.mode ||
        button.action ||
        `${button.label}-${index}`
      "
      type="button"
      class="btn square justify-content-center"
      :class="[
        button.variantClass,
        button.extraClass,
        {
          active: button.active,
          'shop-flank-card--active':
            button.type === 'shop-card' && button.active,
        },
      ]"
      :disabled="button.disabled"
      :aria-label="button.label"
      :title="button.title || button.label"
      @click="$emit('action', button)"
    >
      <template v-if="button.type === 'shop-card'">
        <span class="shop-flank-card__shine"></span>
        <span class="shop-flank-card__badge">{{ button.badge || "S" }}</span>
        <span class="shop-flank-card__label">{{ button.label }}</span>
        <span class="shop-flank-card__type">
          <span class="shop-flank-card__type-prefix">Typ:</span>
          {{ button.shopTypeLabel }}
        </span>
        <span class="shop-flank-card__subtitle">{{ button.subtitle }}</span>
      </template>
      <template v-else>
        {{ button.label }}
      </template>
    </button>
  </template>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  buttons: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["action"]);

const MAX_SHOPS_PER_PAGE = 5;
const currentPage = ref(0);
const isMounted = ref(false);
const autoSelectedShopId = ref(null);

const shopButtons = computed(() =>
  props.buttons.filter((button) => button?.type === "shop-card"),
);

const usesShopSignboards = computed(
  () =>
    shopButtons.value.length > 0 &&
    shopButtons.value.length === props.buttons.length,
);

const pageCount = computed(() =>
  Math.max(1, Math.ceil(shopButtons.value.length / MAX_SHOPS_PER_PAGE)),
);

const pagedShopButtons = computed(() => {
  const start = currentPage.value * MAX_SHOPS_PER_PAGE;
  return shopButtons.value.slice(start, start + MAX_SHOPS_PER_PAGE);
});

const selectedShopButton = computed(
  () =>
    shopButtons.value.find((button) => button.active) || shopButtons.value[0],
);

const selectorClass = computed(() => {
  const visibleCount = pagedShopButtons.value.length;
  return {
    "shop-signboard-selector--single": shopButtons.value.length === 1,
    "shop-signboard-selector--pair": shopButtons.value.length === 2,
    "shop-signboard-selector--grid":
      shopButtons.value.length >= 3 && shopButtons.value.length <= 5,
    "shop-signboard-selector--paged": shopButtons.value.length > 5,
    "shop-signboard-selector--page-single":
      shopButtons.value.length > 1 && visibleCount === 1,
  };
});

const clampPage = (page) =>
  Math.min(Math.max(Number(page) || 0, 0), pageCount.value - 1);

const syncPageToActiveShop = () => {
  const activeIndex = shopButtons.value.findIndex((button) => button.active);
  if (activeIndex >= 0) {
    currentPage.value = clampPage(Math.floor(activeIndex / MAX_SHOPS_PER_PAGE));
    return;
  }
  currentPage.value = clampPage(currentPage.value);
};

const ensureDefaultShopSelection = () => {
  if (!isMounted.value || shopButtons.value.some((button) => button.active)) {
    autoSelectedShopId.value = null;
    return;
  }
  const firstAvailable = shopButtons.value.find((button) => !button.disabled);
  if (
    !firstAvailable ||
    String(autoSelectedShopId.value) === String(firstAvailable.shopId)
  ) {
    return;
  }
  autoSelectedShopId.value = firstAvailable.shopId;
  emit("action", firstAvailable);
};

const goToPage = (page) => {
  currentPage.value = clampPage(page);
};

const shopTypeIconClass = (typeLabel = "") => {
  const normalized = String(typeLabel || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
  if (/kuz|kowal|zbroj/.test(normalized)) {
    return "shop-signboard-row--forge";
  }
  if (/alchem|aptek/.test(normalized)) {
    return "shop-signboard-row--alchemy";
  }
  if (/karcz|tawern/.test(normalized)) {
    return "shop-signboard-row--tavern";
  }
  if (/ziel/.test(normalized)) {
    return "shop-signboard-row--herbalist";
  }
  if (/mag|mist/.test(normalized)) {
    return "shop-signboard-row--arcane";
  }
  return "shop-signboard-row--general";
};

watch(
  () => [
    shopButtons.value.length,
    shopButtons.value.map((button) => button.active).join("|"),
  ],
  syncPageToActiveShop,
  { immediate: true },
);

watch(
  () => [
    shopButtons.value.map((button) => button.shopId).join("|"),
    shopButtons.value.map((button) => button.active).join("|"),
  ],
  ensureDefaultShopSelection,
);

onMounted(() => {
  isMounted.value = true;
  ensureDefaultShopSelection();
});
</script>
