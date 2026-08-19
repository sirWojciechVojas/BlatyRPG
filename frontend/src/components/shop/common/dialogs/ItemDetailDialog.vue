<!-- Responsibility: ItemDetailDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showItemDetailDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.itemDetail.ariaLabel')"
    @click.self="ctx.closeItemDetailDialog"
  >
    <div class="item-detail-dialog">
      <div class="item-detail-titlebar">
        <div class="item-detail-title-text">
          {{
            ctx.itemDetailDisplayName ||
            $t("modals.itemDetail.fallbackItemName")
          }}
        </div>
        <button
          type="button"
          class="btn btn-outline-light btn-sm item-detail-close"
          :aria-label="$t('common.actions.close')"
          @click="ctx.closeItemDetailDialog"
        >
          <span aria-hidden="true">×</span>
        </button>
      </div>
      <div class="item-detail-body">
        <div class="item-detail-preview">
          <div class="img-preview-frame item-detail-preview-frame">
            <ItemIcon
              class="item-detail-preview-img"
              :item="ctx.itemDetailItem"
              :size="112"
            />
            <div v-if="ctx.itemDetailChargeLabel" class="item-detail-charge">
              {{ ctx.itemDetailChargeLabel }}
            </div>
          </div>
        </div>
        <div class="item-detail-info">
          <div class="item-detail-meta">
            <template
              v-for="section in ctx.itemDetailMetaSections"
              :key="`section-${section.key}`"
            >
              <details
                v-if="section.advanced"
                class="item-detail-section item-detail-section--advanced"
              >
                <summary class="item-detail-subtitle">
                  {{ section.title }}
                </summary>
                <div class="item-detail-section-lines">
                  <div
                    v-for="line in section.lines"
                    :key="`detail-${section.key}-${line.key}`"
                    class="item-detail-line"
                    :class="`item-detail-line--${line.key}`"
                  >
                    <span class="item-detail-label">{{ line.label }}:</span>
                    <CurrencyDisplay
                      v-if="
                        line.type === 'currency' &&
                        line.brass !== null &&
                        (!line.concealed || itemValueRevealed)
                      "
                      class="item-detail-line-currency"
                      :brass="line.brass"
                      :currency-code="line.currencyCode"
                      variant="row"
                    />
                    <div
                      v-else-if="line.concealed && !itemValueRevealed"
                      class="item-detail-appraisal"
                    >
                      <span>{{ $t("modals.itemDetail.valueHidden") }}</span>
                      <button
                        type="button"
                        class="btn btn-outline-light btn-sm"
                        @click="itemValueRevealed = true"
                      >
                        {{ $t("modals.itemDetail.appraise") }}
                      </button>
                    </div>
                    <span v-else class="item-detail-text">{{
                      line.value
                    }}</span>
                  </div>
                </div>
              </details>
              <section
                v-else
                class="item-detail-section"
                :class="`item-detail-section--${section.key}`"
              >
                <div class="item-detail-subtitle">{{ section.title }}</div>
                <div class="item-detail-section-lines">
                  <div
                    v-for="line in section.lines"
                    :key="`detail-${section.key}-${line.key}`"
                    class="item-detail-line"
                    :class="`item-detail-line--${line.key}`"
                  >
                    <span class="item-detail-label">{{ line.label }}:</span>
                    <CurrencyDisplay
                      v-if="
                        line.type === 'currency' &&
                        line.brass !== null &&
                        (!line.concealed || itemValueRevealed)
                      "
                      class="item-detail-line-currency"
                      :brass="line.brass"
                      :currency-code="line.currencyCode"
                      variant="row"
                    />
                    <div
                      v-else-if="line.concealed && !itemValueRevealed"
                      class="item-detail-appraisal"
                    >
                      <span>{{ $t("modals.itemDetail.valueHidden") }}</span>
                      <button
                        type="button"
                        class="btn btn-outline-light btn-sm"
                        @click="itemValueRevealed = true"
                      >
                        {{ $t("modals.itemDetail.appraise") }}
                      </button>
                    </div>
                    <span v-else class="item-detail-text">{{
                      line.value
                    }}</span>
                  </div>
                </div>
              </section>
            </template>
            <div
              v-if="!ctx.itemDetailMetaSections.length"
              class="item-detail-empty"
            >
              {{ $t("modals.itemDetail.noData") }}
            </div>
          </div>
        </div>
        <div v-if="ctx.itemDetailCanEditNickname" class="item-detail-nickname">
          <div class="item-detail-nickname-label">
            {{ $t("modals.itemDetail.nicknameLabel") }}
          </div>
          <div class="item-detail-nickname-row">
            <input
              v-model="ctx.itemDetailNicknameModel"
              type="text"
              class="form-control-sm trade-input item-detail-nickname-input"
              :placeholder="$t('modals.itemDetail.nicknamePlaceholder')"
              @keyup.enter="ctx.applyItemDetailNickname"
            />
            <button
              type="button"
              class="btn btn-primary btn-sm"
              @click="ctx.applyItemDetailNickname"
            >
              {{ $t("common.actions.save") }}
            </button>
            <button
              type="button"
              class="btn btn-outline-light btn-sm item-detail-nickname-mode"
              @click="ctx.toggleItemDetailNicknameMode"
            >
              {{ ctx.itemDetailNicknameModeLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
const itemValueRevealed = ref(false);

watch(
  () => [ctx.showItemDetailDialog, ctx.itemDetailItem?.ID],
  () => {
    itemValueRevealed.value = false;
  },
);
</script>
