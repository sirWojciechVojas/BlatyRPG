<!-- Responsibility: IconPickerDialog shop interface component. -->
<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="icon-picker-backdrop"
      role="dialog"
      aria-modal="true"
      :aria-label="
        t(
          panelMode === 'edit'
            ? 'shop.workspace.iconPicker.editTitle'
            : 'shop.workspace.iconPicker.title',
        )
      "
      @click.self="$emit('close')"
    >
      <section class="icon-picker-dialog">
        <header>
          <div>
            <h2>
              {{
                t(
                  panelMode === "edit"
                    ? "shop.workspace.iconPicker.editTitle"
                    : "shop.workspace.iconPicker.title",
                )
              }}
            </h2>
            <p>
              {{
                t(
                  panelMode === "edit"
                    ? "shop.workspace.iconPicker.editDescription"
                    : "shop.workspace.iconPicker.description",
                )
              }}
            </p>
          </div>
          <button type="button" @click="$emit('close')">
            {{ t("actions.close") }}
          </button>
        </header>

        <div class="icon-picker-toolbar">
          <input
            v-model.trim="query"
            class="form-control form-control-sm"
            type="search"
            :placeholder="t('shop.workspace.iconPicker.search')"
          />
          <select
            v-model="typeFilter"
            class="form-select form-select-sm icon-filter icon-filter-type"
            :aria-label="t('shop.workspace.iconPicker.category')"
            :title="filterEnglishTitle('typeKey', typeFilter)"
          >
            <option value="">
              {{ t("shop.workspace.iconPicker.allCategories") }} ({{
                categoryResultCount
              }})
            </option>
            <option
              v-for="entry in categoryFilterOptions"
              :key="entry.code"
              :value="entry.code"
              :title="entry.labelEn"
            >
              {{ entry.labelPl }} ({{ entry.count }})
            </option>
          </select>
          <select
            v-model="subtypeFilter"
            class="form-select form-select-sm icon-filter icon-filter-subtype"
            :aria-label="t('shop.workspace.iconPicker.subtype')"
            :title="filterEnglishTitle('subtypeKey', subtypeFilter)"
            :disabled="!typeFilter"
          >
            <option value="">
              {{ t("shop.workspace.iconPicker.allSubtypes") }} ({{
                subcategoryResultCount
              }})
            </option>
            <option
              v-for="entry in subcategoryFilterOptions"
              :key="entry.code"
              :value="entry.code"
              :title="entry.labelEn"
            >
              {{ entry.labelPl }} ({{ entry.count }})
            </option>
          </select>
          <select
            v-model="sourceFilter"
            class="form-select form-select-sm icon-filter icon-filter-source"
            :aria-label="t('shop.workspace.iconPicker.source')"
          >
            <option value="">
              {{ t("shop.workspace.iconPicker.allSources") }}
            </option>
            <option
              v-for="source in sourceOptions"
              :key="source"
              :value="source"
            >
              {{ source }}
            </option>
          </select>
          <button
            v-if="canEdit"
            class="icon-upload-button"
            type="button"
            :disabled="iconUploading"
            @click="openUploadPanel('create')"
          >
            {{
              iconUploading
                ? t("shop.workspace.iconPicker.uploading")
                : t("shop.workspace.iconPicker.addIcon")
            }}
          </button>
          <span>
            {{
              t("shop.workspace.iconPicker.results", {
                count: filteredIcons.length,
              })
            }}
          </span>
        </div>

        <nav
          class="icon-picker-purpose-tabs"
          :aria-label="t('shop.workspace.iconPicker.modeLabel')"
        >
          <button
            type="button"
            :class="{ active: panelMode === 'select' }"
            @click="setPanelMode('select')"
          >
            <strong>{{ t("shop.workspace.iconPicker.selectMode") }}</strong>
            <small>{{ t("shop.workspace.iconPicker.selectModeHint") }}</small>
          </button>
          <button
            v-if="canEdit"
            type="button"
            :class="{ active: panelMode === 'edit' }"
            @click="setPanelMode('edit')"
          >
            <strong>{{ t("shop.workspace.iconPicker.editMode") }}</strong>
            <small>{{ t("shop.workspace.iconPicker.editModeHint") }}</small>
          </button>
        </nav>

        <div
          class="icon-picker-body"
          :class="{ 'is-edit-mode': panelMode === 'edit' }"
        >
          <form class="icon-metadata-editor" @submit.prevent="saveMetadata">
            <section
              v-if="panelMode === 'select'"
              class="icon-change-preview"
              aria-live="polite"
            >
              <div class="icon-change-preview__item">
                <small>{{ t("shop.workspace.iconPicker.currentIcon") }}</small>
                <ItemIcon :item="{ IMG_CLASS: currentCode }" :size="64" />
                <strong>{{ currentCode }}</strong>
              </div>
              <span class="icon-change-preview__arrow" aria-hidden="true"
                >→</span
              >
              <div class="icon-change-preview__item candidate">
                <small>{{ t("shop.workspace.iconPicker.newIcon") }}</small>
                <ItemIcon :item="{ IMG_CLASS: editingCode }" :size="64" />
                <strong>{{ editingCode }}</strong>
              </div>
              <button
                class="icon-metadata-choose"
                type="button"
                :disabled="!editingCode || editingCode === currentCode"
                @click="choose(editingCode)"
              >
                {{
                  editingCode === currentCode
                    ? t("shop.workspace.iconPicker.iconSelected")
                    : t("shop.workspace.iconPicker.useIcon")
                }}
              </button>
            </section>

            <section
              v-if="panelMode === 'select'"
              class="icon-selection-summary"
            >
              <strong>{{ metadataDraft.name || editingCode }}</strong>
              <span>{{ metadataDraft.sourceName || "—" }}</span>
              <small>{{
                t("shop.workspace.iconPicker.selectionInstruction")
              }}</small>
            </section>

            <section v-else class="icon-edit-heading">
              <ItemIcon :item="{ IMG_CLASS: editingCode }" :size="72" />
              <div>
                <small>{{ t("shop.workspace.iconPicker.editingIcon") }}</small>
                <strong>{{ editingCode }}</strong>
                <span>{{ metadataDraft.name }}</span>
              </div>
              <button type="button" @click="openUploadPanel('replace')">
                {{ t("shop.workspace.iconPicker.replaceFiles") }}
              </button>
            </section>

            <section v-if="uploadMode" class="icon-files-panel">
              <div class="icon-files-panel__heading">
                <strong>{{
                  uploadMode === "create"
                    ? t("shop.workspace.iconPicker.addIconTitle")
                    : t("shop.workspace.iconPicker.replaceFilesTitle", {
                        code: editingCode,
                      })
                }}</strong>
                <button type="button" @click="closeUploadPanel">×</button>
              </div>
              <div class="icon-file-comparison" aria-live="polite">
                <div>
                  <small>{{
                    t("shop.workspace.iconPicker.currentArtwork")
                  }}</small>
                  <ItemIcon :item="{ IMG_CLASS: editingCode }" :size="72" />
                </div>
                <span aria-hidden="true">→</span>
                <div>
                  <small>{{ t("shop.workspace.iconPicker.newArtwork") }}</small>
                  <span class="icon-file-comparison__preview">
                    <img
                      v-if="largeIconPreviewUrl"
                      :src="largeIconPreviewUrl"
                      alt=""
                    />
                    <b v-else>144</b>
                  </span>
                </div>
              </div>
              <div class="icon-files-panel__inputs">
                <label class="icon-file-drop">
                  <input
                    ref="smallIconInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    @change="selectUploadFile($event, 'small')"
                  />
                  <span>42 × 42</span>
                  <small>{{
                    smallIconFile?.name ||
                    t("shop.workspace.iconPicker.chooseFile")
                  }}</small>
                </label>
                <label class="icon-file-drop">
                  <input
                    ref="largeIconInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    @change="selectUploadFile($event, 'large')"
                  />
                  <span>144 × 144</span>
                  <small>{{
                    largeIconFile?.name ||
                    t("shop.workspace.iconPicker.chooseFile")
                  }}</small>
                </label>
              </div>
              <small>{{ t("shop.workspace.iconPicker.twoFilesHint") }}</small>
              <button
                type="button"
                class="icon-files-panel__submit"
                :disabled="!canSubmitIconFiles || iconUploading"
                @click="submitIconFiles"
              >
                {{
                  iconUploading
                    ? t("shop.workspace.iconPicker.uploading")
                    : t("shop.workspace.iconPicker.saveFiles")
                }}
              </button>
            </section>

            <div v-if="panelMode === 'edit'" class="icon-metadata-sections">
              <section class="icon-metadata-section icon-metadata-identity">
                <div class="icon-metadata-section__heading">
                  <div>
                    <strong>{{
                      t("shop.workspace.iconPicker.identitySection")
                    }}</strong>
                    <small>{{
                      t("shop.workspace.iconPicker.identitySectionHint")
                    }}</small>
                  </div>
                  <ShopHelpTooltip
                    :label="t('shop.workspace.iconPicker.identitySection')"
                    :text="t('shop.workspace.iconPicker.identitySectionHelp')"
                    align="right"
                  />
                </div>
                <label>
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.name") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.nameHelp')"
                    />
                  </span>
                  <input
                    v-model.trim="metadataDraft.name"
                    :readonly="!canEdit"
                    required
                    maxlength="160"
                  />
                </label>
                <label>
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.sourceFile") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.sourceFileHelp')"
                    />
                  </span>
                  <input
                    :value="metadataDraft.sourceName"
                    readonly
                    :title="metadataDraft.sourceName"
                  />
                </label>
                <label class="span-2 compact-textarea">
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.metadataDescription") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.descriptionHelp')"
                    />
                  </span>
                  <textarea
                    v-model.trim="metadataDraft.description"
                    :readonly="!canEdit"
                    rows="1"
                    maxlength="2000"
                  ></textarea>
                </label>
              </section>

              <section class="icon-metadata-section">
                <div class="icon-metadata-section__heading">
                  <div>
                    <strong>{{
                      t("shop.workspace.iconPicker.classificationSection")
                    }}</strong>
                    <small>{{
                      t("shop.workspace.iconPicker.classificationSectionHint")
                    }}</small>
                  </div>
                  <ShopHelpTooltip
                    :label="
                      t('shop.workspace.iconPicker.classificationSection')
                    "
                    :text="
                      t('shop.workspace.iconPicker.classificationSectionHelp')
                    "
                    align="right"
                  />
                </div>
                <label>
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.categories") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.categoryHelp')"
                    />
                  </span>
                  <select
                    :value="metadataDraft.typeKey"
                    :disabled="!canEdit"
                    :title="selectedEnglishTitle('typeKey')"
                    :aria-label="metadataAriaLabel('typeKey')"
                    @change="
                      changeMetadataField('typeKey', $event.target.value)
                    "
                  >
                    <option value="">—</option>
                    <option
                      v-for="entry in metadataCategoryOptions"
                      :key="entry.code"
                      :value="entry.code"
                      :title="entry.labelEn"
                    >
                      {{ entry.labelPl }}
                    </option>
                  </select>
                </label>
                <label>
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.subcategories") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.subcategoryHelp')"
                    />
                  </span>
                  <select
                    :value="metadataDraft.subtypeKey"
                    :disabled="!canEdit || !metadataDraft.typeKey"
                    :title="selectedEnglishTitle('subtypeKey')"
                    :aria-label="metadataAriaLabel('subtypeKey')"
                    @change="
                      changeMetadataField('subtypeKey', $event.target.value)
                    "
                  >
                    <option value="">—</option>
                    <option
                      v-for="entry in metadataSubcategoryOptions"
                      :key="entry.code"
                      :value="entry.code"
                      :title="entry.labelEn"
                    >
                      {{ entry.labelPl }}
                    </option>
                  </select>
                </label>
              </section>

              <section class="icon-metadata-section">
                <div class="icon-metadata-section__heading">
                  <div>
                    <strong>{{
                      t("shop.workspace.iconPicker.matchingSection")
                    }}</strong>
                    <small>{{
                      t("shop.workspace.iconPicker.matchingSectionHint")
                    }}</small>
                  </div>
                  <ShopHelpTooltip
                    :label="t('shop.workspace.iconPicker.matchingSection')"
                    :text="t('shop.workspace.iconPicker.matchingSectionHelp')"
                    align="right"
                  />
                </div>
                <label>
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.itemClasses") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.itemClassHelp')"
                    />
                  </span>
                  <select
                    :value="metadataDraft.itemClass"
                    :disabled="!canEdit || !metadataDraft.typeKey"
                    :title="selectedEnglishTitle('itemClass')"
                    :aria-label="metadataAriaLabel('itemClass')"
                    @change="
                      changeMetadataField('itemClass', $event.target.value)
                    "
                  >
                    <option value="">—</option>
                    <option
                      v-for="entry in metadataClassOptions"
                      :key="entry.code"
                      :value="entry.code"
                      :title="entry.labelEn"
                    >
                      {{ entry.labelPl }}
                    </option>
                  </select>
                </label>
                <label>
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.itemGenres") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.itemGenreHelp')"
                    />
                  </span>
                  <select
                    :value="metadataDraft.itemGenre"
                    :disabled="!canEdit || !metadataDraft.typeKey"
                    :title="selectedEnglishTitle('itemGenre')"
                    :aria-label="metadataAriaLabel('itemGenre')"
                    @change="
                      changeMetadataField('itemGenre', $event.target.value)
                    "
                  >
                    <option value="">—</option>
                    <option
                      v-for="entry in metadataGenreOptions"
                      :key="entry.code"
                      :value="entry.code"
                      :title="entry.labelEn"
                    >
                      {{ entry.labelPl }}
                    </option>
                  </select>
                </label>
                <label class="span-2 compact-textarea">
                  <span class="icon-metadata-field-label">
                    {{ t("shop.workspace.iconPicker.keywords") }}
                    <ShopHelpTooltip
                      :text="t('shop.workspace.iconPicker.keywordsHelp')"
                    />
                  </span>
                  <textarea
                    v-model.trim="metadataDraft.specialMarks"
                    :readonly="!canEdit"
                    rows="1"
                    maxlength="1000"
                    :placeholder="t('shop.workspace.iconPicker.listHint')"
                  ></textarea>
                </label>
              </section>
            </div>
            <p
              v-if="metadataDependencyNotice"
              class="icon-metadata-notice"
              role="status"
            >
              {{ metadataDependencyNotice }}
            </p>
            <p v-if="metadataError" class="icon-metadata-error" role="alert">
              {{ metadataError }}
            </p>
            <div
              v-if="canEdit && panelMode === 'edit'"
              class="icon-metadata-actions"
            >
              <button
                type="button"
                :disabled="metadataSaving"
                @click="resetMetadata"
              >
                {{
                  isCatalogAddition
                    ? t("shop.workspace.iconPicker.deleteIcon")
                    : t("shop.workspace.iconPicker.restoreDefaults")
                }}
              </button>
              <button
                type="submit"
                :disabled="metadataSaving || !metadataDraft.name"
              >
                {{
                  metadataSaving
                    ? t("shop.workspace.iconPicker.saving")
                    : t("actions.save")
                }}
              </button>
            </div>
          </form>
          <div
            ref="gridElement"
            class="icon-picker-grid"
            :style="{ '--icon-tile-height': `${pageTileHeight}px` }"
          >
            <article
              v-for="code in pagedIcons"
              :key="code"
              :class="{ active: code === editingCode }"
            >
              <button
                type="button"
                :title="tileTitle(code)"
                @click="editMetadata(code)"
                @dblclick="panelMode === 'select' && choose(code)"
              >
                <ItemIcon :item="{ IMG_CLASS: code }" :size="42" />
                <strong>{{ code }}</strong>
                <small>{{ iconName(code) }}</small>
              </button>
            </article>
          </div>
        </div>

        <footer>
          <span class="icon-pagination-range">
            {{
              t("shop.workspace.iconPicker.visibleRange", {
                from: visibleRange.from,
                to: visibleRange.to,
                total: filteredIcons.length,
              })
            }}
          </span>
          <div
            class="icon-pagination"
            :aria-label="t('shop.workspace.iconPicker.pagination')"
          >
            <button
              type="button"
              :disabled="page <= 1"
              :aria-label="t('shop.workspace.iconPicker.firstPage')"
              @click="goToPage(1)"
            >
              «
            </button>
            <button
              type="button"
              :disabled="page <= 1"
              @click="goToPage(page - 1)"
            >
              {{ t("shop.workspace.iconPicker.previous") }}
            </button>
            <template v-for="entry in visiblePageEntries" :key="entry.key">
              <span v-if="entry.ellipsis" class="icon-pagination-ellipsis"
                >…</span
              >
              <button
                v-else
                type="button"
                :class="{ active: page === entry.page }"
                :aria-current="page === entry.page ? 'page' : undefined"
                @click="goToPage(entry.page)"
              >
                {{ entry.page }}
              </button>
            </template>
            <button
              type="button"
              :disabled="page >= pageCount"
              @click="goToPage(page + 1)"
            >
              {{ t("shop.workspace.iconPicker.next") }}
            </button>
            <button
              type="button"
              :disabled="page >= pageCount"
              :aria-label="t('shop.workspace.iconPicker.lastPage')"
              @click="goToPage(pageCount)"
            >
              »
            </button>
          </div>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import i18n from "@/i18n";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { inventoryIconMetadataMap } from "@/data/trade/inventoryIconMetadata";
import {
  countIconFacet,
  createIconMetadataPayload,
  filterIconClasses,
  iconCategories,
  iconItemClasses,
  iconItemGenres,
  iconSubcategories,
  reconcileMetadataSelection,
} from "@/data/trade/iconTaxonomy";
import shopApiClient, { createShopApiConfig } from "@/lib/trade/shopApiClient";
import {
  getIconMetadata,
  getAvailableIconClasses,
  removeIconMetadataOverride,
  setIconMetadataOverride,
  setIconMetadataOverrides,
} from "@/lib/trade/iconMetadataRegistry";

const props = defineProps({
  open: { type: Boolean, default: false },
  modelValue: { type: String, default: "v0001" },
  campaignId: { type: Number, default: 1 },
  canEdit: { type: Boolean, default: false },
  itemDictionaries: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["update:modelValue", "close"]);
const t = (key, values = {}) => i18n.global.t(key, values);
const query = ref("");
const typeFilter = ref("");
const subtypeFilter = ref("");
const sourceFilter = ref("");
const page = ref(1);
const gridElement = ref(null);
const smallIconInput = ref(null);
const largeIconInput = ref(null);
const iconUploading = ref(false);
const panelMode = ref("select");
const uploadMode = ref("");
const smallIconFile = ref(null);
const largeIconFile = ref(null);
const largeIconPreviewUrl = ref("");
const editingCode = ref("");
const metadataSaving = ref(false);
const metadataError = ref("");
const metadataDependencyNotice = ref("");
const metadataDraft = ref({
  name: "",
  description: "",
  sourceName: "",
  specialMarks: "",
  typeKey: "",
  subtypeKey: "",
  itemClass: "",
  itemGenre: "",
});
const metadataRevision = ref(0);
const pageSize = ref(1);
const pageTileHeight = ref(86);
let gridResizeObserver = null;
const locale = computed(() =>
  typeof i18n.global.locale === "string"
    ? i18n.global.locale
    : i18n.global.locale.value,
);
const isPolish = computed(() => String(locale.value).startsWith("pl"));
const currentCode = computed(() => {
  const code = String(props.modelValue || "v0001").toLowerCase();
  return /^v\d{4}$/u.test(code) ? code : "v0001";
});
const canSubmitIconFiles = computed(() =>
  Boolean(smallIconFile.value && largeIconFile.value),
);
const availableIconClasses = computed(() => {
  void metadataRevision.value;
  return getAvailableIconClasses();
});
const metadataValues = (field) =>
  availableIconClasses.value.flatMap((code) => {
    void metadataRevision.value;
    const value = getIconMetadata(code)?.[field];
    return Array.isArray(value) ? value : value ? [value] : [];
  });
const uniqueSorted = (values) =>
  [...new Set(values.filter(Boolean))].sort((left, right) =>
    String(left).localeCompare(String(right), locale.value),
  );
const suppliedDictionaryEntries = (group) =>
  Array.isArray(props.itemDictionaries?.[group])
    ? props.itemDictionaries[group]
    : [];
const taxonomyCode = (value) =>
  String(value || "")
    .trim()
    .toUpperCase();
const relationCodes = (entry, prefix) =>
  (entry?.appliesTo || [])
    .map(taxonomyCode)
    .filter((value) => value.startsWith(`${prefix}:`))
    .map((value) => value.slice(prefix.length + 1))
    .filter(Boolean);
const directRelationCodes = (entry) =>
  (entry?.appliesTo || [])
    .map(taxonomyCode)
    .filter((value) => value && !value.includes(":"));
const runtimeIconTaxonomy = computed(() => {
  const suppliedCategories = suppliedDictionaryEntries("icon_categories");
  const suppliedSubcategories = suppliedDictionaryEntries("icon_subcategories");
  const classCodes = new Set(
    suppliedDictionaryEntries("classes").map((entry) =>
      taxonomyCode(entry.code),
    ),
  );
  const canonicalCategoryByCode = new Map(
    iconCategories.map((entry) => [entry.code, entry]),
  );
  const canonicalSubcategoryByCode = new Map(
    iconSubcategories.map((entry) => [entry.code, entry]),
  );
  const suppliedCategoryByCode = new Map(
    suppliedCategories.map((entry) => [taxonomyCode(entry.code), entry]),
  );
  const suppliedSubcategoryByCode = new Map(
    suppliedSubcategories.map((entry) => [taxonomyCode(entry.code), entry]),
  );
  const retiredCategoryCodes = new Set(
    suppliedCategories.flatMap((entry) => relationCodes(entry, "RENAMED_FROM")),
  );
  const retiredSubcategoryCodes = new Set(
    suppliedSubcategories.flatMap((entry) =>
      relationCodes(entry, "RENAMED_FROM"),
    ),
  );
  const categoryCodes = new Set([
    ...canonicalCategoryByCode.keys(),
    ...suppliedCategoryByCode.keys(),
  ]);
  const categorySeeds = [
    ...iconCategories.filter((entry) => !retiredCategoryCodes.has(entry.code)),
    ...suppliedCategories
      .filter(
        (entry) =>
          !canonicalCategoryByCode.has(taxonomyCode(entry.code)) &&
          taxonomyCode(entry.code),
      )
      .map((entry) => ({
        code: taxonomyCode(entry.code),
        categoryCode: null,
        labelPl: entry.labelPl || entry.code,
        labelEn: entry.labelEn || entry.code,
        itemClasses: [],
        itemGenres: [],
        subcategoryCodes: [],
      })),
  ];
  const categories = categorySeeds.map((seed) => {
    const supplied = suppliedCategoryByCode.get(seed.code);
    const configuredClasses = relationCodes(supplied, "CLASS");
    const configuredGenres = relationCodes(supplied, "GENRE");
    const fallbackClasses = classCodes.has(seed.code) ? [seed.code] : ["MISC"];
    return {
      ...seed,
      ...(supplied || {}),
      code: seed.code,
      categoryCode: null,
      labelPl: supplied?.labelPl || seed.labelPl,
      labelEn: supplied?.labelEn || seed.labelEn,
      itemClasses:
        configuredClasses.length > 0
          ? configuredClasses
          : seed.itemClasses?.length
            ? [...seed.itemClasses]
            : fallbackClasses,
      itemGenres:
        configuredGenres.length > 0
          ? configuredGenres
          : seed.itemGenres?.length
            ? [...seed.itemGenres]
            : ["UTILITY"],
      subcategoryCodes: [],
    };
  });
  const categoryByCode = new Map(
    categories.map((entry) => [entry.code, entry]),
  );
  const subcategorySeeds = [
    ...iconSubcategories.filter(
      (entry) => !retiredSubcategoryCodes.has(entry.code),
    ),
    ...suppliedSubcategories
      .filter(
        (entry) =>
          !canonicalSubcategoryByCode.has(taxonomyCode(entry.code)) &&
          taxonomyCode(entry.code),
      )
      .map((entry) => ({
        code: taxonomyCode(entry.code),
        categoryCode: "",
        labelPl: entry.labelPl || entry.code,
        labelEn: entry.labelEn || entry.code,
        itemClasses: [],
        itemGenres: [],
      })),
  ];
  const subcategories = subcategorySeeds.map((seed) => {
    const supplied = suppliedSubcategoryByCode.get(seed.code);
    const configuredParent =
      relationCodes(supplied, "CATEGORY")[0] ||
      directRelationCodes(supplied).find((code) => categoryCodes.has(code)) ||
      seed.categoryCode;
    const parent = categoryByCode.get(configuredParent);
    const configuredClasses = relationCodes(supplied, "CLASS");
    const configuredGenres = relationCodes(supplied, "GENRE");
    return {
      ...seed,
      ...(supplied || {}),
      code: seed.code,
      categoryCode: configuredParent || "",
      labelPl: supplied?.labelPl || seed.labelPl,
      labelEn: supplied?.labelEn || seed.labelEn,
      itemClasses:
        configuredClasses.length > 0
          ? configuredClasses
          : seed.itemClasses?.length
            ? [...seed.itemClasses]
            : [...(parent?.itemClasses || ["MISC"])],
      itemGenres:
        configuredGenres.length > 0
          ? configuredGenres
          : seed.itemGenres?.length
            ? [...seed.itemGenres]
            : ["UTILITY"],
    };
  });
  subcategories.forEach((entry) => {
    const parent = categoryByCode.get(entry.categoryCode);
    if (parent && !parent.subcategoryCodes.includes(entry.code)) {
      parent.subcategoryCodes.push(entry.code);
    }
  });
  return { categories, subcategories };
});
const metadataCategoryEntries = computed(
  () => runtimeIconTaxonomy.value.categories,
);
const metadataSubcategoryEntries = computed(
  () => runtimeIconTaxonomy.value.subcategories,
);
const facetFilters = (includeCategory = true) => ({
  query: query.value,
  sourceName: sourceFilter.value,
  typeKey: includeCategory ? typeFilter.value : "",
  subtypeKey: "",
  locale: locale.value,
  searchAliases: metadataSearchAliases.value,
});
const categoryFilterOptions = computed(() => {
  void metadataRevision.value;
  return metadataCategoryEntries.value.map((entry) => ({
    ...entry,
    count: countIconFacet(
      availableIconClasses.value,
      getIconMetadata,
      facetFilters(false),
      "typeKey",
      entry.code,
    ),
  }));
});
const categoryResultCount = computed(
  () =>
    filterIconClasses(
      availableIconClasses.value,
      getIconMetadata,
      facetFilters(false),
    ).length,
);
const subcategoryFilterOptions = computed(() => {
  void metadataRevision.value;
  if (!typeFilter.value) return [];
  return metadataSubcategoryEntries.value
    .filter((entry) => entry.categoryCode === typeFilter.value)
    .map((entry) => ({
      ...entry,
      count: countIconFacet(
        availableIconClasses.value,
        getIconMetadata,
        facetFilters(),
        "subtypeKey",
        entry.code,
      ),
    }));
});
const subcategoryResultCount = computed(
  () =>
    filterIconClasses(
      availableIconClasses.value,
      getIconMetadata,
      facetFilters(),
    ).length,
);
const sourceOptions = computed(() =>
  uniqueSorted(metadataValues("sourceName")),
);
const dictionaryOptions = (group, metadataField) => {
  const entries = suppliedDictionaryEntries(group);
  const known = new Set(entries.map((entry) => String(entry.code || "")));
  return [
    ...entries,
    ...uniqueSorted(metadataValues(metadataField))
      .filter((code) => !known.has(code))
      .map((code) => ({ code, labelPl: code, labelEn: code })),
  ];
};
const mergeCanonicalDictionary = (canonical, group, metadataField) => {
  const entries = dictionaryOptions(group, metadataField);
  const retiredCodes = new Set(
    entries.flatMap((entry) => relationCodes(entry, "RENAMED_FROM")),
  );
  const suppliedByCode = new Map(
    entries.map((entry) => [String(entry.code || ""), entry]),
  );
  const activeCanonical = canonical.filter(
    (entry) => !retiredCodes.has(entry.code),
  );
  const canonicalCodes = new Set(activeCanonical.map((entry) => entry.code));
  return [
    ...activeCanonical.map((entry) => ({
      ...entry,
      ...(suppliedByCode.get(entry.code) || {}),
      labelPl: suppliedByCode.get(entry.code)?.labelPl || entry.labelPl,
      labelEn: suppliedByCode.get(entry.code)?.labelEn || entry.labelEn,
    })),
    ...entries.filter((entry) => !canonicalCodes.has(String(entry.code || ""))),
  ];
};
const classDictionaryOptions = computed(() =>
  mergeCanonicalDictionary(iconItemClasses, "classes", "itemClasses"),
);
const genreDictionaryOptions = computed(() =>
  mergeCanonicalDictionary(iconItemGenres, "genres", "itemGenres"),
);
const metadataSearchAliases = computed(() =>
  Object.fromEntries(
    [
      ...metadataCategoryEntries.value,
      ...metadataSubcategoryEntries.value,
      ...classDictionaryOptions.value,
      ...genreDictionaryOptions.value,
    ].map((entry) => [entry.code, [entry.labelPl, entry.labelEn]]),
  ),
);
const selectedCategoryTaxonomy = computed(() =>
  metadataCategoryEntries.value.find(
    (entry) => entry.code === metadataDraft.value.typeKey,
  ),
);
const selectedSubcategoryTaxonomy = computed(() =>
  metadataSubcategoryEntries.value.find(
    (entry) => entry.code === metadataDraft.value.subtypeKey,
  ),
);
const metadataCategoryOptions = computed(() => metadataCategoryEntries.value);
const metadataSubcategoryOptions = computed(() =>
  metadataSubcategoryEntries.value.filter(
    (entry) => entry.categoryCode === metadataDraft.value.typeKey,
  ),
);
const metadataClassOptions = computed(() => {
  const allowed =
    selectedSubcategoryTaxonomy.value?.itemClasses ||
    selectedCategoryTaxonomy.value?.itemClasses ||
    [];
  return classDictionaryOptions.value.filter((entry) =>
    allowed.includes(entry.code),
  );
});
const metadataGenreOptions = computed(() => {
  const allowed =
    selectedSubcategoryTaxonomy.value?.itemGenres ||
    selectedCategoryTaxonomy.value?.itemGenres ||
    [];
  return genreDictionaryOptions.value.filter((entry) =>
    allowed.includes(entry.code),
  );
});
const dictionaryEntry = (field, code) => {
  if (!code) return null;
  if (field === "typeKey")
    return (
      metadataCategoryEntries.value.find((entry) => entry.code === code) || null
    );
  if (field === "subtypeKey")
    return (
      metadataSubcategoryEntries.value.find((entry) => entry.code === code) ||
      null
    );
  const options =
    field === "itemClass"
      ? classDictionaryOptions.value
      : genreDictionaryOptions.value;
  return options.find((entry) => entry.code === code) || null;
};
const filterEnglishTitle = (field, code) =>
  code ? `English: ${dictionaryEntry(field, code)?.labelEn || code}` : "";
const selectedEnglishTitle = (field) => {
  const entry = dictionaryEntry(field, metadataDraft.value[field]);
  return entry ? `English: ${entry.labelEn || entry.code}` : "";
};
const metadataFieldLabel = (field) =>
  t(`shop.workspace.iconPicker.metadataFields.${field}`);
const metadataValueLabel = (field, code) => {
  const entry = dictionaryEntry(field, code);
  return entry?.labelPl || entry?.label || code;
};
const metadataAriaLabel = (field) => {
  const entry = dictionaryEntry(field, metadataDraft.value[field]);
  if (!entry) return metadataFieldLabel(field);
  return `${metadataFieldLabel(field)}: ${entry.labelPl || entry.code}. English: ${
    entry.labelEn || entry.code
  }`;
};
const changeMetadataField = (field, value) => {
  const reconciled = reconcileMetadataSelection(
    { ...metadataDraft.value, [field]: value },
    field,
    {
      categories: metadataCategoryEntries.value,
      subcategories: metadataSubcategoryEntries.value,
    },
  );
  Object.assign(metadataDraft.value, reconciled.selection);
  const changes = [
    ...reconciled.removed.map(
      (change) =>
        `${metadataFieldLabel(change.field)}: ${metadataValueLabel(
          change.field,
          change.value,
        )} → —`,
    ),
    ...reconciled.added.map(
      (change) =>
        `${metadataFieldLabel(change.field)}: — → ${metadataValueLabel(
          change.field,
          change.value,
        )}`,
    ),
  ];
  metadataDependencyNotice.value = changes.length
    ? t("shop.workspace.iconPicker.relationsAdjusted", {
        changes: changes.join("; "),
      })
    : "";
};
const filteredIcons = computed(() => {
  void metadataRevision.value;
  return filterIconClasses(availableIconClasses.value, getIconMetadata, {
    ...facetFilters(),
    subtypeKey: subtypeFilter.value,
  });
});
const pageCount = computed(() =>
  Math.max(1, Math.ceil(filteredIcons.value.length / pageSize.value)),
);
const pagedIcons = computed(() => {
  const start = (page.value - 1) * pageSize.value;
  return filteredIcons.value.slice(start, start + pageSize.value);
});
const visibleRange = computed(() => ({
  from: filteredIcons.value.length ? (page.value - 1) * pageSize.value + 1 : 0,
  to: Math.min(page.value * pageSize.value, filteredIcons.value.length),
}));
const visiblePageEntries = computed(() => {
  const total = pageCount.value;
  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => ({
      key: `page-${index + 1}`,
      page: index + 1,
    }));
  }
  const pages = new Set([1, total]);
  for (
    let candidate = Math.max(2, page.value - 2);
    candidate <= Math.min(total - 1, page.value + 2);
    candidate += 1
  ) {
    pages.add(candidate);
  }
  const sorted = [...pages].sort((left, right) => left - right);
  const entries = [];
  sorted.forEach((value, index) => {
    if (index && value - sorted[index - 1] > 1) {
      entries.push({ key: `ellipsis-${value}`, ellipsis: true });
    }
    entries.push({ key: `page-${value}`, page: value });
  });
  return entries;
});
const goToPage = (target) => {
  page.value = Math.min(pageCount.value, Math.max(1, Number(target) || 1));
};
const updatePageSize = () => {
  const element = gridElement.value;
  if (!element) return;
  const styles = window.getComputedStyle(element);
  const horizontalPadding =
    parseFloat(styles.paddingLeft || 0) + parseFloat(styles.paddingRight || 0);
  const verticalPadding =
    parseFloat(styles.paddingTop || 0) + parseFloat(styles.paddingBottom || 0);
  const columnGap = parseFloat(styles.columnGap || styles.gap || 0);
  const rowGap = parseFloat(styles.rowGap || styles.gap || 0);
  const usableWidth = Math.max(68, element.clientWidth - horizontalPadding);
  const usableHeight = Math.max(86, element.clientHeight - verticalPadding);
  const columns = Math.max(
    1,
    Math.floor((usableWidth + columnGap) / (68 + columnGap)),
  );
  const rows = Math.max(1, Math.round((usableHeight + rowGap) / (86 + rowGap)));
  pageTileHeight.value = Math.max(
    78,
    Math.floor((usableHeight - Math.max(0, rows - 1) * rowGap) / rows),
  );
  pageSize.value = columns * rows;
};
const observeGridSize = async () => {
  await nextTick();
  gridResizeObserver?.disconnect();
  updatePageSize();
  if (typeof ResizeObserver !== "undefined" && gridElement.value) {
    gridResizeObserver = new ResizeObserver(updatePageSize);
    gridResizeObserver.observe(gridElement.value);
  }
};
const iconName = (code) => getIconMetadata(code)?.name || code;
const isCatalogAddition = computed(
  () => Number(String(editingCode.value).slice(1)) > 1375,
);
const defaultMetadata = (code) => inventoryIconMetadataMap[code] || {};
const tileTitle = (code) => {
  const meta = getIconMetadata(code) || {};
  return [code, isPolish.value ? meta.name : "", meta.sourceName]
    .filter(Boolean)
    .join(" — ");
};
const choose = (code) => {
  emit("update:modelValue", code);
  emit("close");
};
const cleanDescription = (value) => {
  const description = String(value || "").trim();
  return /^Ikona przedmiotu:.*Kategoria:.*Źródłowa nazwa z analizy:/u.test(
    description,
  )
    ? ""
    : description;
};
const editMetadata = (code) => {
  const metadata = getIconMetadata(code) || defaultMetadata(code);
  editingCode.value = code;
  metadataError.value = "";
  const hasMultipleValues = [
    metadata.typeKeys,
    metadata.subtypeKeys,
    metadata.itemClasses,
    metadata.itemGenres,
  ].some((values) => Array.isArray(values) && values.length > 1);
  metadataDependencyNotice.value = hasMultipleValues
    ? t("shop.workspace.iconPicker.legacyMultipleValues")
    : "";
  metadataDraft.value = {
    name: metadata.name || code,
    description: cleanDescription(metadata.description),
    sourceName: metadata.sourceName || "",
    specialMarks: metadata.specialMarks || "",
    typeKey: metadata.typeKeys?.[0] || "",
    subtypeKey: metadata.subtypeKeys?.[0] || "",
    itemClass: metadata.itemClasses?.[0] || "",
    itemGenre: metadata.itemGenres?.[0] || "",
  };
};
const metadataPayload = () => createIconMetadataPayload(metadataDraft.value);
const saveMetadata = async () => {
  metadataSaving.value = true;
  try {
    const response = await shopApiClient.saveIconMetadata(
      createShopApiConfig({ campaignId: props.campaignId }),
      editingCode.value,
      metadataPayload(),
    );
    setIconMetadataOverride(response.metadata);
    metadataRevision.value += 1;
    editMetadata(editingCode.value);
  } catch (error) {
    metadataError.value = t("shop.workspace.iconPicker.saveError");
  } finally {
    metadataSaving.value = false;
  }
};
const clearUploadFiles = () => {
  if (largeIconPreviewUrl.value) {
    URL.revokeObjectURL(largeIconPreviewUrl.value);
    largeIconPreviewUrl.value = "";
  }
  smallIconFile.value = null;
  largeIconFile.value = null;
  if (smallIconInput.value) smallIconInput.value.value = "";
  if (largeIconInput.value) largeIconInput.value.value = "";
};
const closeUploadPanel = () => {
  uploadMode.value = "";
  clearUploadFiles();
};
const setPanelMode = (mode) => {
  panelMode.value = mode === "edit" && props.canEdit ? "edit" : "select";
  closeUploadPanel();
};
const openUploadPanel = (mode) => {
  panelMode.value = "edit";
  uploadMode.value = mode;
  clearUploadFiles();
  metadataError.value = "";
};
const imageDimensions = (file) =>
  new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => {
      URL.revokeObjectURL(url);
      resolve({ width: image.naturalWidth, height: image.naturalHeight });
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("invalid image"));
    };
    image.src = url;
  });
const selectUploadFile = async (event, variant) => {
  const file = event.target?.files?.[0] || null;
  if (!file) return;
  const expectedSize = variant === "small" ? 42 : 144;
  try {
    const dimensions = await imageDimensions(file);
    if (
      !["image/png", "image/jpeg", "image/webp"].includes(file.type) ||
      file.size > 4 * 1024 * 1024 ||
      dimensions.width !== expectedSize ||
      dimensions.height !== expectedSize
    ) {
      throw new Error("invalid icon file");
    }
    if (variant === "small") {
      smallIconFile.value = file;
    } else {
      if (largeIconPreviewUrl.value) {
        URL.revokeObjectURL(largeIconPreviewUrl.value);
      }
      largeIconFile.value = file;
      largeIconPreviewUrl.value = URL.createObjectURL(file);
    }
    metadataError.value = "";
  } catch (error) {
    if (variant === "small") smallIconFile.value = null;
    else {
      largeIconFile.value = null;
      if (largeIconPreviewUrl.value) {
        URL.revokeObjectURL(largeIconPreviewUrl.value);
        largeIconPreviewUrl.value = "";
      }
    }
    event.target.value = "";
    metadataError.value = t("shop.workspace.iconPicker.invalidDimensions", {
      size: expectedSize,
    });
  }
};
const submitIconFiles = async () => {
  if (!canSubmitIconFiles.value) return;
  iconUploading.value = true;
  metadataError.value = "";
  try {
    const config = createShopApiConfig({ campaignId: props.campaignId });
    const response =
      uploadMode.value === "create"
        ? await shopApiClient.uploadIconPair(
            config,
            smallIconFile.value,
            largeIconFile.value,
          )
        : await shopApiClient.replaceIconImages(
            config,
            editingCode.value,
            smallIconFile.value,
            largeIconFile.value,
            metadataPayload(),
          );
    setIconMetadataOverride(response.metadata);
    metadataRevision.value += 1;
    if (uploadMode.value === "create") {
      query.value = "";
      typeFilter.value = "";
      subtypeFilter.value = "";
      sourceFilter.value = "";
    }
    editMetadata(response.metadata.iconClass);
    if (uploadMode.value === "create") page.value = pageCount.value;
    closeUploadPanel();
  } catch (error) {
    metadataError.value = t("shop.workspace.iconPicker.uploadError");
  } finally {
    iconUploading.value = false;
  }
};
const resetMetadata = async () => {
  metadataSaving.value = true;
  metadataError.value = "";
  try {
    const deletingCustomIcon = isCatalogAddition.value;
    const deletedCode = editingCode.value;
    await shopApiClient.deleteIconMetadata(
      createShopApiConfig({ campaignId: props.campaignId }),
      editingCode.value,
    );
    removeIconMetadataOverride(editingCode.value);
    metadataRevision.value += 1;
    if (deletingCustomIcon && currentCode.value === deletedCode) {
      emit("update:modelValue", "v0001");
    }
    editMetadata(deletingCustomIcon ? "v0001" : editingCode.value);
  } catch (error) {
    metadataError.value = t("shop.workspace.iconPicker.saveError");
  } finally {
    metadataSaving.value = false;
  }
};
const loadMetadata = async () => {
  if (!props.canEdit) return;
  try {
    const response = await shopApiClient.getIconMetadata(
      createShopApiConfig({ campaignId: props.campaignId }),
    );
    setIconMetadataOverrides(response?.metadata || []);
    metadataRevision.value += 1;
    if (editingCode.value) editMetadata(editingCode.value);
  } catch (error) {
    metadataError.value = t("shop.workspace.iconPicker.loadError");
  }
};
watch([query, typeFilter, subtypeFilter, sourceFilter], () => {
  page.value = 1;
});
watch(typeFilter, () => {
  if (
    subtypeFilter.value &&
    !subcategoryFilterOptions.value.some(
      (entry) => entry.code === subtypeFilter.value,
    )
  ) {
    subtypeFilter.value = "";
  }
});
watch(pageCount, (count) => {
  if (page.value > count) page.value = count;
});
watch(
  () => props.open,
  (open) => {
    if (open) {
      panelMode.value = "select";
      page.value = 1;
      editMetadata(String(props.modelValue || "v0001").toLowerCase());
      loadMetadata();
      observeGridSize();
    } else {
      gridResizeObserver?.disconnect();
      editingCode.value = "";
      closeUploadPanel();
    }
  },
);
onBeforeUnmount(() => {
  gridResizeObserver?.disconnect();
  clearUploadFiles();
});
</script>

<style scoped src="./IconPickerDialog.css"></style>
