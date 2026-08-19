<!-- Responsibility: OwnerOptionDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showOwnerOptDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.ownerOption.ariaLabel')"
    @click.self="ctx.closeOwnerOptDialog"
  >
    <div class="owner-dialog">
      <div class="img-dialog-header">
        <div class="img-dialog-title">{{ $t("modals.ownerOption.title") }}</div>
        <button
          type="button"
          class="btn btn-outline-light btn-sm"
          :aria-label="$t('common.actions.close')"
          @click="ctx.closeOwnerOptDialog"
        >
          {{ $t("common.actions.close") }}
        </button>
      </div>
      <div class="img-dialog-purpose">
        {{ $t("modals.ownerOption.purpose") }}
      </div>
      <div class="owner-dialog-body">
        <div class="owner-dialog-left">
          <div class="owner-avatar-display">
            <div class="owner-avatar owner-avatar--selected">
              <span class="owner-avatar-badge">{{ ctx.displayOwnerOpt }}</span>
              <span class="owner-avatar-title">{{ ctx.displayOwnerOpt }}</span>
            </div>
            <div class="owner-avatar-caption">
              {{ $t("modals.ownerOption.currentSelection") }}
            </div>
          </div>
        </div>
        <div class="owner-dialog-right">
          <div class="img-grid-title">
            {{ $t("modals.ownerOption.selectionDescription") }}
          </div>
          <div class="owner-opt-list">
            <button
              v-for="opt in ctx.ownerOptOptions"
              :key="`owner-opt-${opt}`"
              type="button"
              class="owner-opt"
              :class="{ active: opt === ctx.selectedOwnerOpt }"
              @click="ctx.selectOwnerOpt(opt)"
            >
              <div class="owner-opt-header">
                <span class="owner-opt-title">{{ opt }}</span>
                <span class="owner-opt-chip">{{
                  $t("modals.ownerOption.ownerChip")
                }}</span>
              </div>
              <div class="owner-opt-desc">
                {{ ctx.ownerOptDescription(opt) }}
              </div>
            </button>
          </div>
        </div>
      </div>
      <div class="img-dialog-footer">
        <button
          type="button"
          class="btn btn-outline-light"
          @click="ctx.closeOwnerOptDialog"
        >
          {{ $t("common.actions.cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-success"
          @click="ctx.confirmOwnerOpt"
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
