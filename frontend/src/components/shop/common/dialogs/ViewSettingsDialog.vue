<!-- Responsibility: ViewSettingsDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showViewSettingsDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.viewSettings.ariaLabel')"
    @click.self="ctx.closeViewSettingsDialog"
  >
    <div class="view-settings-dialog">
      <div class="img-dialog-header">
        <div class="img-dialog-title">
          {{ $t("modals.viewSettings.title") }}
        </div>
        <button
          type="button"
          class="btn btn-outline-light btn-sm"
          :aria-label="$t('common.actions.close')"
          @click="ctx.closeViewSettingsDialog"
        >
          {{ $t("common.actions.close") }}
        </button>
      </div>

      <div class="img-dialog-purpose">
        {{ $t("modals.viewSettings.purpose") }}
      </div>

      <div class="view-settings-dialog__body">
        <div class="view-settings-dialog__section-title">
          {{ $t("shop.workspace.density.compact") }} /
          {{ $t("shop.workspace.density.comfortable") }}
        </div>
        <div class="view-settings-density" role="radiogroup">
          <button
            type="button"
            class="btn btn-outline-light"
            :class="{ active: Number(ctx.viewSettingsIconSizeModel) <= 30 }"
            role="radio"
            :aria-checked="String(Number(ctx.viewSettingsIconSizeModel) <= 30)"
            @click="ctx.setViewSettingsIconSize(28)"
          >
            {{ $t("shop.workspace.density.compact") }}
          </button>
          <button
            type="button"
            class="btn btn-outline-light"
            :class="{ active: Number(ctx.viewSettingsIconSizeModel) > 30 }"
            role="radio"
            :aria-checked="String(Number(ctx.viewSettingsIconSizeModel) > 30)"
            @click="ctx.setViewSettingsIconSize(40)"
          >
            {{ $t("shop.workspace.density.comfortable") }}
          </button>
        </div>
      </div>

      <div class="img-dialog-footer">
        <button
          type="button"
          class="btn btn-outline-light"
          @click="ctx.closeViewSettingsDialog"
        >
          {{ $t("common.actions.cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-success"
          @click="ctx.saveViewSettingsDialog"
        >
          {{ $t("common.actions.save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
</script>

<style scoped>
.view-settings-density {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
.view-settings-density .active {
  border-color: #c6a164;
  background: #5f4827;
  color: #fff1d2;
}
</style>
