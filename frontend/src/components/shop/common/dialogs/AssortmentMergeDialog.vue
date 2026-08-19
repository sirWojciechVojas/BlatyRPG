<!-- Responsibility: AssortmentMergeDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showAssortmentMergeDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.assortmentMerge.ariaLabel')"
    @click.self="ctx.closeAssortmentMergeDialog"
  >
    <div class="assort-merge-dialog">
      <div class="img-dialog-header">
        <div class="img-dialog-title">
          {{ $t("modals.assortmentMerge.title") }}
        </div>
        <button
          type="button"
          class="btn btn-outline-light btn-sm"
          :aria-label="$t('common.actions.close')"
          @click="ctx.closeAssortmentMergeDialog"
        >
          {{ $t("common.actions.close") }}
        </button>
      </div>
      <div class="img-dialog-purpose">
        {{ $t("modals.assortmentMerge.purpose") }}
      </div>
      <div class="assort-merge-dialog__intro">
        {{ $t("modals.assortmentMerge.instructions") }}
      </div>
      <div class="assort-merge-dialog__columns">
        <div class="assort-merge-panel assort-merge-panel--left">
          <div class="assort-merge-panel__title">
            {{ $t("modals.assortmentMerge.leftTitle") }}
          </div>
          <div class="assort-merge-panel__rows">
            <div
              v-for="fieldKey in fieldKeys"
              :key="`merge-left-${fieldKey}`"
              class="assort-merge-row"
            >
              <div class="assort-merge-row__label">
                {{ fieldLabel(fieldKey) }}
              </div>
              <label class="assort-merge-row__choice">
                <input
                  type="checkbox"
                  :checked="choiceFor(fieldKey) === 'left'"
                  @change="setChoice(fieldKey, 'left')"
                />
                <span>{{
                  displayValue(ctx.assortmentMergeLeftItem, fieldKey)
                }}</span>
              </label>
            </div>
          </div>
        </div>
        <div class="assort-merge-panel assort-merge-panel--right">
          <div class="assort-merge-panel__title">
            {{ $t("modals.assortmentMerge.rightTitle") }}
          </div>
          <div class="assort-merge-panel__rows">
            <div
              v-for="fieldKey in fieldKeys"
              :key="`merge-right-${fieldKey}`"
              class="assort-merge-row"
            >
              <div class="assort-merge-row__label">
                {{ fieldLabel(fieldKey) }}
              </div>
              <label class="assort-merge-row__choice">
                <input
                  type="checkbox"
                  :checked="choiceFor(fieldKey) === 'right'"
                  @change="setChoice(fieldKey, 'right')"
                />
                <span>{{
                  displayValue(ctx.assortmentMergeRightItem, fieldKey)
                }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="img-dialog-footer">
        <button
          type="button"
          class="btn btn-outline-light"
          @click="ctx.closeAssortmentMergeDialog"
        >
          {{ $t("common.actions.cancel") }}
        </button>
        <button type="button" class="btn btn-success" @click="confirmMerge">
          {{ $t("common.actions.save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import i18n from "@/i18n";
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
const t = (...args) => i18n.global.t(...args);

const fieldLabelMap = {
  NAME: "modals.assortmentMerge.fields.name",
  ITEM_PLACE: "modals.assortmentMerge.fields.itemPlace",
  PERSONAL_PSEU: "modals.assortmentMerge.fields.personalPseu",
  PERSONAL_DESC: "modals.assortmentMerge.fields.personalDesc",
  PERSONAL_COST: "modals.assortmentMerge.fields.personalCost",
  DESCRIPTION: "modals.assortmentMerge.fields.description",
  IMG_CLASS: "modals.assortmentMerge.fields.imgClass",
  PRIZE: "modals.assortmentMerge.fields.prize",
  CHARGE: "modals.assortmentMerge.fields.charge",
};

const fieldKeys = computed(() =>
  Array.isArray(ctx.assortmentMergeFieldDefinitions)
    ? ctx.assortmentMergeFieldDefinitions
    : [],
);

const fieldLabel = (fieldKey) => t(fieldLabelMap[fieldKey] || fieldKey);

const displayValue = (item, fieldKey) => {
  const raw = item?.[fieldKey];
  if (raw === undefined || raw === null || raw === "") {
    return t("modals.fieldEdit.common.emptyValue");
  }
  if (typeof raw === "number" && Number.isFinite(raw)) {
    return raw;
  }
  return String(raw);
};

const choiceFor = (fieldKey) => {
  if (typeof ctx.assortmentMergeChoiceFor !== "function") {
    return "left";
  }
  return ctx.assortmentMergeChoiceFor(fieldKey);
};

const setChoice = (fieldKey, source) => {
  if (typeof ctx.setAssortmentMergeChoice !== "function") {
    return;
  }
  ctx.setAssortmentMergeChoice({ fieldKey, source });
};

const confirmMerge = () => {
  if (typeof ctx.confirmAssortmentMergeDialog !== "function") {
    return;
  }
  ctx.confirmAssortmentMergeDialog();
};
</script>
