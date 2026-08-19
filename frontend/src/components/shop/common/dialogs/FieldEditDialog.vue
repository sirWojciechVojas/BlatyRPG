<!-- Responsibility: FieldEditDialog shop interface component. -->
<template>
  <div
    v-if="showGenericFieldEditDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="dialogTitle"
    @click.self="ctx.closeFieldEditDialog"
    @keydown.esc.stop.prevent="ctx.closeFieldEditDialog"
  >
    <div
      ref="dialogRoot"
      class="field-edit-dialog"
      tabindex="-1"
      @keydown.enter.stop="handleDialogEnter"
    >
      <div class="modal-header field-edit-dialog__header">
        <div class="modal-title col-md-12 titleBar field-edit-dialog__titlebar">
          <span class="field-edit-dialog__title-text">{{ dialogTitle }}</span>
          <button
            type="button"
            class="close"
            :aria-label="$t('common.actions.close')"
            @click="ctx.closeFieldEditDialog"
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>

      <div class="img-dialog-purpose field-edit-dialog__purpose">
        {{ $t("modals.fieldEdit.purpose") }}
      </div>

      <div class="field-edit-value">
        <div class="field-edit-value__header">
          <div class="field-edit-value__label">{{ fieldLabel }}</div>
          <input
            :value="ctx.classEditSearch"
            type="text"
            class="form-control-sm trade-input field-edit-value__search"
            :placeholder="$t('modals.fieldEdit.searchPlaceholder')"
            @input="ctx.updateClassEditSearch($event?.target?.value)"
          />
        </div>

        <ItemClassPicker
          v-if="isItemClassField"
          data-autofocus="true"
          :model-value="String(ctx.classEditDraftValue || '')"
          @update:model-value="ctx.updateClassEditDraftValue"
        />
        <ItemGenrePicker
          v-else-if="isItemGenreField"
          data-autofocus="true"
          :model-value="String(ctx.classEditDraftValue || '')"
          @update:model-value="ctx.updateClassEditDraftValue"
        />
        <PriceInput
          v-else-if="isPrizeField"
          data-autofocus="true"
          :model-value="String(ctx.classEditDraftValue || '')"
          @update:model-value="ctx.updateClassEditDraftValue"
        />
        <ChargeInput
          v-else-if="isChargeField"
          data-autofocus="true"
          :model-value="String(ctx.classEditDraftValue || '')"
          @update:model-value="ctx.updateClassEditDraftValue"
        />
        <div v-else class="field-edit-value__suggestions">
          <button
            v-for="suggestion in ctx.classEditSuggestions"
            :key="`suggestion-${suggestion}`"
            type="button"
            class="field-edit-suggestion"
            :class="{
              active: String(suggestion) === String(ctx.classEditDraftValue),
            }"
            @click="ctx.applyClassEditSuggestion(suggestion)"
          >
            {{ suggestion }}
          </button>
          <div v-if="!ctx.classEditSuggestions?.length" class="img-grid-empty">
            {{ $t("modals.fieldEdit.noSuggestions") }}
          </div>
        </div>
      </div>

      <div v-if="ctx.classEditValidationError" class="trade-field-error">
        {{ ctx.classEditValidationError }}
      </div>

      <div class="img-dialog-footer">
        <button
          type="button"
          class="btn btn-outline-light"
          @click="ctx.closeFieldEditDialog"
        >
          {{ $t("common.actions.cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-success"
          @click="ctx.confirmClassEdit"
        >
          {{ $t("common.actions.save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import ChargeInput from "@/components/shop/common/inputs/ChargeInput.vue";
import ItemClassPicker from "@/components/shop/common/inputs/ItemClassPicker.vue";
import ItemGenrePicker from "@/components/shop/common/inputs/ItemGenrePicker.vue";
import PriceInput from "@/components/shop/common/inputs/PriceInput.vue";
import i18n from "@/i18n";

const ctx = useTradeModalContext();
const t = (key, values = {}) => i18n.global.t(key, values);

const editField = computed(() =>
  String(ctx.classEditType || "")
    .trim()
    .toUpperCase(),
);
const showGenericFieldEditDialog = computed(
  () =>
    ctx.showFieldEditDialog &&
    !["ICON", "OWNER", "WEAPON"].includes(
      String(ctx.classEditMode || "")
        .trim()
        .toUpperCase(),
    ),
);
const fieldLabel = computed(
  () =>
    t(`modals.fieldEdit.fields.labels.${editField.value}`) ||
    editField.value ||
    t("modals.fieldEdit.defaultField"),
);
const contextLabel = computed(() =>
  ctx.classEditTarget
    ? t(`modals.fieldEdit.targets.${ctx.classEditTarget}`)
    : t("modals.fieldEdit.targets.default"),
);
const dialogTitle = computed(() =>
  t("modals.fieldEdit.title", {
    field: fieldLabel.value,
    context: contextLabel.value,
  }),
);
const isItemClassField = computed(() => editField.value === "ITEM_CLASS");
const isItemGenreField = computed(() => editField.value === "ITEM_GENRE");
const isPrizeField = computed(() => editField.value === "PRIZE");
const isChargeField = computed(() => editField.value === "CHARGE");

const dialogRoot = ref(null);

watch(showGenericFieldEditDialog, async (isOpen) => {
  if (!isOpen) {
    return;
  }
  await nextTick();
  dialogRoot.value?.querySelector?.("[data-autofocus='true']")?.focus?.();
});

const handleDialogEnter = (event) => {
  const tagName = String(event?.target?.tagName || "").toUpperCase();
  if (
    !showGenericFieldEditDialog.value ||
    event?.defaultPrevented ||
    event?.shiftKey ||
    event?.ctrlKey ||
    event?.altKey ||
    event?.metaKey ||
    tagName === "TEXTAREA"
  ) {
    return;
  }
  event.preventDefault();
  ctx.confirmClassEdit();
};
</script>
