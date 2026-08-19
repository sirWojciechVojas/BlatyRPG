<!-- Responsibility: IconClassDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showClassEditDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.iconClass.ariaLabel')"
    @click.self="ctx.closeClassEditDialog"
  >
    <div class="img-dialog">
      <div class="img-dialog-header">
        <div class="img-dialog-title">{{ $t("modals.iconClass.title") }}</div>
        <button
          type="button"
          class="btn btn-outline-light btn-sm"
          :aria-label="$t('common.actions.close')"
          @click="ctx.closeClassEditDialog"
        >
          {{ $t("common.actions.close") }}
        </button>
      </div>
      <div class="img-dialog-purpose">
        {{ $t("modals.iconClass.purpose") }}
      </div>
      <div class="img-dialog-body">
        <div class="img-dialog-preview">
          <div class="img-preview-frame">
            <div
              class="img-preview-icon img-preview-icon--fixed trade-icon"
              :style="ctx.pickerPreviewIconStyle(ctx.selectedIconPreviewClass)"
            ></div>
            <div class="img-preview-glow"></div>
          </div>
          <div class="img-meta-editor">
            <div class="img-meta-title">
              {{ $t("modals.iconClass.metadataTitle") }}
            </div>
            <div class="img-meta-row img-meta-row--actions">
              <input
                v-model.trim="ctx.selectedIconClassModel"
                type="text"
                class="form-control-sm trade-input"
                :placeholder="$t('modals.iconClass.iconClassPlaceholder')"
              />
              <button
                type="button"
                class="btn btn-outline-danger btn-sm img-meta-action-btn"
                :disabled="!ctx.canDeleteSelectedIcon"
                @click="ctx.deleteSelectedIconFromCatalog"
              >
                <span class="bi bi-trash" aria-hidden="true"></span>
                {{ $t("modals.iconClass.deleteIcon") }}
              </button>
            </div>
            <div class="img-meta-row img-meta-row--actions">
              <input
                v-model.trim="ctx.newIconClassDraft"
                type="text"
                class="form-control-sm trade-input"
                :placeholder="$t('modals.iconClass.addIconPlaceholder')"
              />
              <button
                type="button"
                class="btn btn-outline-light btn-sm img-meta-action-btn"
                @click="ctx.addIconToCatalog"
              >
                <span class="bi bi-plus-lg" aria-hidden="true"></span>
                {{ $t("modals.iconClass.addIcon") }}
              </button>
            </div>
            <div
              v-if="ctx.iconCatalogValidationError"
              class="trade-field-error"
            >
              {{ ctx.iconCatalogValidationError }}
            </div>
            <input
              v-model.trim="ctx.selectedIconNameModel"
              type="text"
              class="form-control-sm trade-input"
              :placeholder="$t('modals.iconClass.namePlaceholder')"
            />
            <textarea
              v-model.trim="ctx.selectedIconDescriptionModel"
              class="form-control trade-textarea img-meta-textarea"
              rows="2"
              :placeholder="$t('modals.iconClass.descriptionPlaceholder')"
            ></textarea>
            <input
              v-model.trim="ctx.selectedIconSpecialMarksModel"
              type="text"
              class="form-control-sm trade-input"
              :placeholder="$t('modals.iconClass.specialMarksPlaceholder')"
            />
            <input
              :value="ctx.selectedIconTagsModel"
              type="text"
              class="form-control-sm trade-input"
              :placeholder="$t('modals.iconClass.autoTagsPlaceholder')"
              readonly
            />
            <div class="img-meta-choice">
              <div class="img-meta-choice-label">
                {{ $t("modals.iconClass.typesMulti") }}
              </div>
              <div class="img-meta-chip-list img-meta-chip-list--types">
                <button
                  v-for="option in ctx.selectedIconTypeOptions"
                  :key="`meta-type-${option.value}`"
                  type="button"
                  class="img-meta-chip"
                  :class="{ active: option.active }"
                  @click="ctx.toggleSelectedIconType(option.value)"
                >
                  {{ option.label }}
                </button>
              </div>
            </div>
            <div class="img-meta-choice">
              <div class="img-meta-choice-label">
                {{ $t("modals.iconClass.subtypesMulti") }}
              </div>
              <div class="img-meta-chip-list img-meta-chip-list--subtypes">
                <button
                  v-for="option in ctx.selectedIconSubtypeOptionsForChips"
                  :key="`meta-subtype-${option.value}`"
                  type="button"
                  class="img-meta-chip img-meta-chip--subtype"
                  :class="{ active: option.active }"
                  @click="ctx.toggleSelectedIconSubtype(option.value)"
                >
                  {{ option.label }}
                </button>
              </div>
            </div>
          </div>
          <button
            type="button"
            class="btn btn-primary w-100"
            :disabled="
              !ctx.hasSelectedIconMetadataChanges || !ctx.selectedImgClass
            "
            @click="ctx.applySelectedIconMetadata"
          >
            {{ $t("modals.iconClass.applyMetadata") }}
          </button>
        </div>
        <div class="img-dialog-grid">
          <div class="img-grid-title">
            {{ $t("modals.iconClass.collectionTitle") }}
          </div>
          <div class="img-grid-controls">
            <input
              v-model.trim="ctx.imgClassSearch"
              type="text"
              class="form-control-sm trade-input img-grid-search"
              :placeholder="$t('modals.iconClass.searchPlaceholder')"
            />
            <select
              v-model="ctx.imgClassTypeFilter"
              class="form-select form-select-sm trade-input img-grid-select"
            >
              <option value="all">{{ $t("modals.iconClass.allTypes") }}</option>
              <option
                v-for="option in ctx.imgClassTypeOptions"
                :key="`img-type-${option.value}`"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <select
              v-model="ctx.imgClassSubtypeFilter"
              class="form-select form-select-sm trade-input img-grid-select"
            >
              <option value="all">
                {{ $t("modals.iconClass.allSubtypes") }}
              </option>
              <option
                v-for="option in ctx.imgClassSubtypeFilterOptions"
                :key="`img-subtype-${option.value}`"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <select
              v-model="ctx.imgClassSortMode"
              class="form-select form-select-sm trade-input img-grid-select"
            >
              <option value="asc">{{ $t("modals.iconClass.sortAsc") }}</option>
              <option value="desc">
                {{ $t("modals.iconClass.sortDesc") }}
              </option>
            </select>
          </div>
          <div class="img-grid">
            <button
              v-for="imgClass in ctx.filteredImgClassOptions"
              :key="`img-${imgClass}`"
              type="button"
              class="img-tile"
              :class="{
                active: imgClass === ctx.selectedImgClass,
                'is-dragging': imgClass === ctx.draggedIconClass,
                'is-drop-target': imgClass === ctx.dragOverIconClass,
              }"
              :title="ctx.iconTileTitle(imgClass)"
              draggable="true"
              @click="ctx.selectImgClass(imgClass)"
              @dragstart="ctx.startIconClassDrag(imgClass, $event)"
              @dragenter.prevent="ctx.enterIconClassDropTarget(imgClass)"
              @dragover.prevent="ctx.enterIconClassDropTarget(imgClass)"
              @drop.prevent="ctx.dropIconClassOn(imgClass)"
              @dragend="ctx.clearIconClassDragState"
            >
              <span
                class="img-tile-icon img-tile-icon--fixed trade-icon"
                :style="ctx.pickerIconStyle(imgClass, 42)"
              ></span>
              <span class="img-tile-label">{{ imgClass }}</span>
            </button>
            <div
              v-if="!ctx.filteredImgClassOptions.length"
              class="img-grid-empty"
            >
              {{ $t("modals.iconClass.emptyFiltered") }}
            </div>
          </div>
        </div>
      </div>
      <div class="img-dialog-footer">
        <button
          type="button"
          class="btn btn-outline-light"
          @click="ctx.closeClassEditDialog"
        >
          {{ $t("common.actions.cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-success"
          @click="ctx.confirmImgClass"
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
