<!-- Responsibility: SuggestionDetailDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showSuggestionDetailDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.suggestionDetail.ariaLabel')"
    @click.self="ctx.closeSuggestionDetailDialog"
  >
    <div class="suggestion-detail-dialog">
      <div class="img-dialog-header">
        <div class="img-dialog-title">
          {{ $t("modals.suggestionDetail.title") }}
        </div>
        <button
          type="button"
          class="btn btn-outline-light btn-sm"
          :aria-label="$t('common.actions.close')"
          @click="ctx.closeSuggestionDetailDialog"
        >
          {{ $t("common.actions.close") }}
        </button>
      </div>
      <div class="img-dialog-purpose">
        {{ $t("modals.suggestionDetail.purpose") }}
      </div>
      <div class="suggestion-detail-body">
        <div class="suggestion-detail-section">
          <div class="suggestion-detail-label">
            {{ $t("modals.suggestionDetail.suggestionLabel") }}
          </div>
          <div class="suggestion-detail-value">
            {{ ctx.suggestionDisplayName(ctx.suggestionDetailEntry) }}
          </div>
          <div class="suggestion-detail-muted">
            {{ ctx.suggestionDescription(ctx.suggestionDetailEntry) }}
          </div>
        </div>
        <div class="suggestion-detail-section">
          <div class="suggestion-detail-label">
            {{ $t("modals.suggestionDetail.whySuggestion") }}
          </div>
          <ul class="suggestion-detail-reasons">
            <li
              v-for="(line, index) in ctx.suggestionDetailReasonLines(
                ctx.suggestionDetailEntry,
              )"
              :key="`suggestion-reason-${index}`"
            >
              {{ line }}
            </li>
          </ul>
        </div>
        <div class="suggestion-detail-section">
          <div class="suggestion-detail-label">
            {{ $t("modals.suggestionDetail.templateVsPersonalizedTitle") }}
          </div>
          <div class="suggestion-detail-muted">
            {{ $t("modals.suggestionDetail.templateVsPersonalizedBody") }}
          </div>
        </div>
        <div class="suggestion-detail-section">
          <div class="suggestion-detail-label">
            {{ $t("modals.suggestionDetail.personalizedVariant") }}
          </div>
          <div class="suggestion-detail-variants">
            <label
              v-for="variant in ctx.suggestionDetailVariantOptions(
                ctx.suggestionDetailEntry,
              )"
              :key="variant.variantId"
              class="suggestion-variant-option"
            >
              <input
                v-model="ctx.suggestionDetailVariantModel"
                type="radio"
                :value="variant.variantId"
              />
              <span class="suggestion-variant-main">
                <strong>{{ variant.personalPseu }}</strong>
                <span>{{ variant.personalDesc }}</span>
              </span>
              <span class="suggestion-variant-price">{{
                $t("modals.suggestionDetail.variantPrice", {
                  value: Number(variant.personalCost || 0),
                })
              }}</span>
            </label>
          </div>
        </div>
      </div>
      <div class="img-dialog-footer suggestion-detail-footer">
        <button
          type="button"
          class="btn btn-outline-light"
          @click="ctx.closeSuggestionDetailDialog"
        >
          {{ $t("common.actions.cancel") }}
        </button>
        <button
          v-if="ctx.suggestionDetailEntry?.action === 'create_draft'"
          type="button"
          class="btn btn-warning"
          @click="ctx.confirmSuggestionDetailAction('template_only')"
        >
          {{ $t("modals.suggestionDetail.createTemplateOnly") }}
        </button>
        <button
          v-if="ctx.suggestionDetailEntry?.action === 'create_draft'"
          type="button"
          class="btn btn-success"
          @click="ctx.confirmSuggestionDetailAction('template_plus_item')"
        >
          {{ $t("modals.suggestionDetail.createTemplateAndItem") }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn-success"
          @click="ctx.confirmSuggestionDetailAction('item_only')"
        >
          {{ $t("modals.suggestionDetail.addItem") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
</script>
