<!-- Komponent modułu Sklep - dodaj/edytuj. Ten plik renderuje główny pasek akcji dostępnych w edytorze sklepu. -->
<template>
  <div class="shop-mode-template-actions">
    <div
      class="shop-mode-template-actions__row shop-mode-template-actions__row--end"
    >
      <button
        type="button"
        class="btn btn-outline-light btn-sm shop-editor-icon-btn"
        :title="$t('shop.shopEditor.tooltips.newShopButton')"
        :aria-label="$t('shop.shopEditor.tooltips.newShopButton')"
        @click="emit('create-shop')"
      >
        <span class="visually-hidden">{{ $t("shop.shopEditor.newShop") }}</span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path
            d="M4 9.5 12 4l8 5.5V19H4V9.5ZM12 10v7M8.5 13.5h7"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.2"
          />
        </svg>
      </button>

      <button
        type="button"
        class="btn btn-outline-light btn-sm shop-editor-icon-btn"
        :title="$t('shop.shopEditor.tooltips.drawSignboardButton')"
        :aria-label="$t('shop.shopEditor.tooltips.drawSignboardButton')"
        @click="emit('draw-signboard')"
      >
        <span class="visually-hidden">{{
          $t("shop.shopEditor.drawSignboardName")
        }}</span>
        <svg
          class="shop-editor-signboard-random-icon"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          aria-hidden="true"
        >
          <path
            d="M5 6.5h14M7 6.5v9.5c0 1.1.9 2 2 2h6c1.1 0 2-.9 2-2V6.5M10 9.5h4M12 3.5v3"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.1"
          />
          <path
            d="m16.5 11.5 1 1.8 2 .4-1.4 1.4.3 2-1.9-.9-1.8.9.3-2-1.4-1.4 2-.4 1-1.8Z"
            fill="none"
            stroke="currentColor"
            stroke-linejoin="round"
            stroke-width="1.8"
          />
        </svg>
      </button>

      <button
        type="button"
        class="btn btn-outline-warning btn-sm shop-editor-icon-btn shop-editor-activation-btn"
        :title="$t('shop.shopEditor.tooltips.manageActivationButton')"
        :aria-label="$t('shop.shopEditor.tooltips.manageActivationButton')"
        @click="emit('manage-activation')"
      >
        <span class="visually-hidden">{{
          $t("shop.shopEditor.manageActivation")
        }}</span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path
            d="M12 3v8"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="2.4"
          />
          <path
            d="M7.8 6.8a7 7 0 1 0 8.4 0"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.2"
          />
        </svg>
      </button>

      <button
        type="button"
        class="btn btn-outline-danger btn-sm shop-editor-icon-btn"
        :disabled="!canDeleteActiveShop"
        :title="$t('shop.shopEditor.tooltips.deleteShopButton')"
        :aria-label="$t('shop.shopEditor.tooltips.deleteShopButton')"
        @click="emit('delete-shop')"
      >
        <span class="visually-hidden">{{
          $t("shop.shopEditor.deleteShop")
        }}</span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path
            d="M5 7h14M9 7V5h6v2m-8 0 1 12h8l1-12"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.8"
          />
        </svg>
      </button>

      <button
        type="button"
        class="btn btn-outline-light btn-sm shop-editor-icon-btn"
        :title="$t('shop.shopEditor.tooltips.saveProfileButton')"
        :aria-label="$t('shop.shopEditor.tooltips.saveProfileButton')"
        @click="emit('save-profile')"
      >
        <span class="visually-hidden">{{
          $t("shop.shopEditor.saveProfile")
        }}</span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path
            d="M5 4.5h11l3 3V19.5H5zM8 4.5v4h7v-4M8.5 14.5h7"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.1"
          />
        </svg>
      </button>

      <button
        type="button"
        class="btn btn-primary btn-sm shop-editor-icon-btn"
        :title="$t('shop.shopEditor.tooltips.generateStarterSuggestionsButton')"
        :aria-label="
          $t('shop.shopEditor.tooltips.generateStarterSuggestionsButton')
        "
        @click="emit('generate-suggestions')"
      >
        <span class="visually-hidden">
          {{ $t("shop.shopEditor.generateStarterSuggestions") }}
        </span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path
            d="M6 18 18 6M13 5l.8 2.2L16 8l-2.2.8L13 11l-.8-2.2L10 8l2.2-.8L13 5ZM8 13l.6 1.6L10 15l-1.4.5L8 17l-.6-1.5L6 15l1.4-.4L8 13ZM16.5 12.5l.5 1.3 1.3.5-1.3.5-.5 1.2-.5-1.2-1.2-.5 1.2-.5.5-1.3Z"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
          />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  canDeleteActiveShop: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "create-shop",
  "delete-shop",
  "draw-signboard",
  "generate-suggestions",
  "manage-activation",
  "save-profile",
]);
</script>
