<!-- Responsibility: TradeModalShell shop interface component. -->
<template>
  <div
    id="trading"
    class="modal fade modal-wide trading-modal trade-modal shop-trade-module"
    tabindex="-1"
    role="dialog"
    aria-labelledby="tradingModalLongTitle"
    aria-hidden="true"
  >
    <div class="modal-dialog tS modal-dialog-centered mw-100" role="document">
      <div
        class="modal-content"
        :class="{ 'trade-modal-content--with-purpose': isGM }"
        id="tradingStats"
        :style="styleVars"
      >
        <div class="modal-header mh-5">
          <div
            id="tradingModalLongTitle"
            class="modal-title col-md-12 titleBar"
          >
            <button
              type="button"
              class="btn btn-default btn-lg trade-view-settings-trigger"
              :class="{ active: isViewSettingsOpen }"
              :title="$t('shop.tradeModal.viewSettingsTitle')"
              @click="$emit('toggle-view-settings')"
            >
              <span class="bi bi-gear-fill" aria-hidden="true"></span>
            </button>
            {{ $t("modals.tradeMain.title") }}
            <button
              type="button"
              class="close"
              data-bs-dismiss="modal"
              :aria-label="$t('common.actions.close')"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
        <div class="trade-modal-signboard-bg" aria-hidden="true"></div>

        <slot />

        <div
          v-if="apiStatusLabel"
          class="modal-footer justify-content-center trade-footer"
        >
          <div class="row d-flex w-100 p-0 m-0 trade-footer-row">
            <div class="api-note">
              {{ $t("shop.tradeModal.apiPrefix") }}: {{ apiStatusLabel }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "TradeModalShell",
  props: {
    styleVars: {
      type: Object,
      default: () => ({}),
    },
    isGM: {
      type: Boolean,
      default: false,
    },
    isViewSettingsOpen: {
      type: Boolean,
      default: false,
    },
    apiStatusLabel: {
      type: String,
      default: "",
    },
    walletAlert: {
      type: String,
      default: "",
    },
    walletAlertKey: {
      type: Number,
      default: 0,
    },
  },
  emits: ["toggle-view-settings"],
};
</script>
<style src="@/styles/trade/index.css"></style>
