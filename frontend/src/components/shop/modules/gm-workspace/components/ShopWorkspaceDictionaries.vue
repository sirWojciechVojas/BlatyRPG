<!-- Panel GM sklepu: ShopWorkspaceDictionaries. -->
<template>
  <section class="dictionary-workspace">
    <nav
      class="dictionary-group-tabs"
      :aria-label="$t('shop.workspace.dictionaries.navigation')"
    >
      <button
        v-for="group in dictionaryGroups"
        :key="group.key"
        type="button"
        :class="{ active: selectedDictionaryGroup === group.key }"
        @click="selectedDictionaryGroup = group.key"
      >
        {{ group.title }}
        <small>{{ group.entries.length }}</small>
      </button>
    </nav>
    <article v-if="activeDictionaryGroup" :key="activeDictionaryGroup.key">
      <header>
        <div>
          <h3>{{ activeDictionaryGroup.title }}</h3>
          <p>{{ activeDictionaryGroup.description }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span>{{ activeDictionaryGroup.entries.length }}</span>
          <button
            v-if="activeDictionaryGroup.canAdd"
            type="button"
            class="btn btn-sm btn-outline-warning"
            @click="startAddingEntry"
          >
            {{ $t("shop.workspace.dictionaries.add") }}
          </button>
        </div>
      </header>
      <form
        v-if="newEntryOpen"
        class="dictionary-new-entry"
        @submit.prevent="createDictionaryEntry"
      >
        <div class="dictionary-new-entry__heading">
          <strong>
            {{
              $t("shop.workspace.dictionaries.addTitle", {
                group: activeDictionaryGroup.title,
              })
            }}
          </strong>
          <span>{{ $t("shop.workspace.dictionaries.addDescription") }}</span>
        </div>
        <div class="dictionary-new-entry__fields">
          <label>
            <span>{{ $t("shop.workspace.dictionaries.code") }}</span>
            <input
              v-model.trim="newEntryDraft.code"
              class="form-control form-control-sm"
              required
              maxlength="64"
              placeholder="NEW_CODE"
              @input="normalizeNewCode"
            />
          </label>
          <label>
            <span>{{ $t("shop.workspace.dictionaries.labelPl") }}</span>
            <input
              v-model.trim="newEntryDraft.labelPl"
              class="form-control form-control-sm"
              required
            />
          </label>
          <label>
            <span>{{ $t("shop.workspace.dictionaries.labelEn") }}</span>
            <input
              v-model.trim="newEntryDraft.labelEn"
              class="form-control form-control-sm"
              required
            />
          </label>
          <label v-if="isSubcategoryGroup">
            <span>{{ $t("shop.workspace.dictionaries.parentCategory") }}</span>
            <select
              v-model="newEntryDraft.parentCategory"
              class="form-select form-select-sm"
              required
              @change="applyParentDefaults"
            >
              <option value="" disabled>
                {{ $t("shop.workspace.dictionaries.selectParentCategory") }}
              </option>
              <option
                v-for="entry in categoryEntries"
                :key="entry.code"
                :value="entry.code"
              >
                {{ entry.labelPl }} — {{ entry.labelEn }}
              </option>
            </select>
          </label>
          <label v-if="usesTaxonomyRelations">
            <span>{{ $t("shop.workspace.dictionaries.defaultClass") }}</span>
            <select
              v-model="newEntryDraft.itemClass"
              class="form-select form-select-sm"
              :required="isCategoryGroup"
            >
              <option value="">
                {{ $t("shop.workspace.dictionaries.inheritClass") }}
              </option>
              <option
                v-for="entry in classEntries"
                :key="entry.code"
                :value="entry.code"
              >
                {{ entry.labelPl }} — {{ entry.labelEn }}
              </option>
            </select>
          </label>
          <label v-if="usesTaxonomyRelations">
            <span>{{ $t("shop.workspace.dictionaries.defaultGenre") }}</span>
            <select
              v-model="newEntryDraft.itemGenre"
              class="form-select form-select-sm"
              :required="isCategoryGroup"
            >
              <option value="">
                {{ $t("shop.workspace.dictionaries.inheritGenre") }}
              </option>
              <option
                v-for="entry in genreEntries"
                :key="entry.code"
                :value="entry.code"
              >
                {{ entry.labelPl }} — {{ entry.labelEn }}
              </option>
            </select>
          </label>
          <div
            v-if="!usesTaxonomyRelations"
            class="dictionary-new-entry__field"
          >
            <span>{{ activeDictionaryGroup.appliesToLabel }}</span>
            <details class="dictionary-multi-select">
              <summary>
                {{ multiSelectionSummary(newEntryDraft) }}
              </summary>
              <div class="dictionary-multi-select__menu">
                <label
                  v-for="option in multiSelectOptions(newEntryDraft)"
                  :key="option.code"
                  :title="option.labelEn"
                >
                  <input
                    v-model="newEntryDraft.appliesToCodes"
                    type="checkbox"
                    :value="option.code"
                  />
                  <span>{{ option.labelPl }}</span>
                  <code>{{ option.code }}</code>
                </label>
              </div>
            </details>
          </div>
        </div>
        <p v-if="newEntryError" class="dictionary-entry-error" role="alert">
          {{ newEntryError }}
        </p>
        <footer>
          <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            @click="cancelAddingEntry"
          >
            {{ $t("actions.cancel") }}
          </button>
          <button type="submit" class="btn btn-sm btn-success">
            {{ $t("actions.create") }}
          </button>
        </footer>
      </form>
      <section v-if="activeMechanicsEntry" class="dictionary-mechanics-panel">
        <header>
          <div>
            <strong>
              {{
                $t("shop.workspace.mechanics.dictionaryTitle", {
                  entry:
                    activeMechanicsEntry.labelPl || activeMechanicsEntry.code,
                })
              }}
            </strong>
            <small>{{ $t("shop.workspace.mechanics.dictionaryHint") }}</small>
          </div>
          <div>
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              @click="closeMechanicsEditor"
            >
              {{ $t("actions.close") }}
            </button>
            <button
              type="button"
              class="btn btn-sm btn-success"
              @click="saveActiveMechanics"
            >
              {{ $t("actions.save") }}
            </button>
          </div>
        </header>
        <ItemMechanicsEditor
          :model-value="entryDraft(activeMechanicsEntry).mechanics"
          :own-source="mechanicsOwnSource"
          @update:model-value="updateActiveMechanics"
        />
      </section>
      <div class="dictionary-table-wrap">
        <table>
          <colgroup>
            <col />
            <col />
            <col />
            <col />
          </colgroup>
          <thead>
            <tr>
              <th>{{ $t("shop.workspace.dictionaries.code") }}</th>
              <th>PL</th>
              <th>EN</th>
              <th>{{ activeDictionaryGroup.appliesToLabel }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="entry in activeDictionaryGroup.entries"
              :key="entry.code"
            >
              <td>
                <div class="dictionary-code-cell">
                  <template v-if="entryDraft(entry).codeUnlocked">
                    <input
                      v-model.trim="entryDraft(entry).code"
                      class="form-control form-control-sm"
                      maxlength="64"
                      @input="normalizeEntryCode(entry)"
                    />
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-secondary"
                      :title="
                        $t('shop.workspace.dictionaries.cancelCodeChange')
                      "
                      @click="cancelCodeChange(entry)"
                    >
                      ×
                    </button>
                  </template>
                  <template v-else>
                    <code>{{ entry.code }}</code>
                    <button
                      type="button"
                      class="dictionary-code-unlock"
                      :title="$t('shop.workspace.dictionaries.unlockCode')"
                      :aria-label="$t('shop.workspace.dictionaries.unlockCode')"
                      @click="unlockCode(entry)"
                    >
                      ✎
                    </button>
                  </template>
                </div>
                <small v-if="entryDraft(entry).codeUnlocked">
                  {{ $t("shop.workspace.dictionaries.codeUnlocked") }}
                </small>
              </td>
              <td>
                <input
                  v-model.trim="entryDraft(entry).labelPl"
                  class="form-control form-control-sm"
                />
              </td>
              <td>
                <input
                  v-model.trim="entryDraft(entry).labelEn"
                  class="form-control form-control-sm"
                />
              </td>
              <td>
                <div class="dictionary-actions-cell">
                  <div
                    v-if="isCategoryGroup"
                    class="dictionary-relation-editor dictionary-relation-editor--category"
                  >
                    <label>
                      <span>{{
                        $t("shop.workspace.dictionaries.defaultClass")
                      }}</span>
                      <select
                        v-model="entryDraft(entry).itemClass"
                        class="form-select form-select-sm"
                      >
                        <option value="">
                          {{ $t("shop.workspace.dictionaries.canonicalValue") }}
                        </option>
                        <option
                          v-for="option in classEntries"
                          :key="option.code"
                          :value="option.code"
                        >
                          {{ option.labelPl }}
                        </option>
                      </select>
                    </label>
                    <label>
                      <span>{{
                        $t("shop.workspace.dictionaries.defaultGenre")
                      }}</span>
                      <select
                        v-model="entryDraft(entry).itemGenre"
                        class="form-select form-select-sm"
                      >
                        <option value="">
                          {{ $t("shop.workspace.dictionaries.canonicalValue") }}
                        </option>
                        <option
                          v-for="option in genreEntries"
                          :key="option.code"
                          :value="option.code"
                        >
                          {{ option.labelPl }}
                        </option>
                      </select>
                    </label>
                  </div>
                  <div
                    v-else-if="isSubcategoryGroup"
                    class="dictionary-relation-editor dictionary-relation-editor--subcategory"
                  >
                    <label>
                      <span>{{
                        $t("shop.workspace.dictionaries.parentCategory")
                      }}</span>
                      <select
                        v-model="entryDraft(entry).parentCategory"
                        class="form-select form-select-sm"
                      >
                        <option value="" disabled>—</option>
                        <option
                          v-for="option in categoryEntries"
                          :key="option.code"
                          :value="option.code"
                        >
                          {{ option.labelPl }}
                        </option>
                      </select>
                    </label>
                    <label>
                      <span>{{
                        $t("shop.workspace.dictionaries.defaultClass")
                      }}</span>
                      <select
                        v-model="entryDraft(entry).itemClass"
                        class="form-select form-select-sm"
                      >
                        <option value="">
                          {{ $t("shop.workspace.dictionaries.inheritClass") }}
                        </option>
                        <option
                          v-for="option in classEntries"
                          :key="option.code"
                          :value="option.code"
                        >
                          {{ option.labelPl }}
                        </option>
                      </select>
                    </label>
                    <label>
                      <span>{{
                        $t("shop.workspace.dictionaries.defaultGenre")
                      }}</span>
                      <select
                        v-model="entryDraft(entry).itemGenre"
                        class="form-select form-select-sm"
                      >
                        <option value="">
                          {{ $t("shop.workspace.dictionaries.inheritGenre") }}
                        </option>
                        <option
                          v-for="option in genreEntries"
                          :key="option.code"
                          :value="option.code"
                        >
                          {{ option.labelPl }}
                        </option>
                      </select>
                    </label>
                  </div>
                  <details v-else class="dictionary-multi-select">
                    <summary>
                      {{ multiSelectionSummary(entryDraft(entry)) }}
                    </summary>
                    <div class="dictionary-multi-select__menu">
                      <label
                        v-for="option in multiSelectOptions(entryDraft(entry))"
                        :key="option.code"
                        :title="option.labelEn"
                      >
                        <input
                          v-model="entryDraft(entry).appliesToCodes"
                          type="checkbox"
                          :value="option.code"
                        />
                        <span>{{ option.labelPl }}</span>
                        <code>{{ option.code }}</code>
                      </label>
                    </div>
                  </details>
                  <p
                    v-if="rowErrors[entry.id || entry.code]"
                    class="dictionary-entry-error"
                    role="alert"
                  >
                    {{ rowErrors[entry.id || entry.code] }}
                  </p>
                  <div class="dictionary-row-actions">
                    <button
                      v-if="mechanicsSupported"
                      type="button"
                      class="btn btn-sm btn-outline-info"
                      @click="openMechanicsEditor(entry)"
                    >
                      {{
                        $t("shop.workspace.mechanics.shortLabel", {
                          count: (entryDraft(entry).mechanics || []).length,
                        })
                      }}
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-success"
                      @click="saveExistingEntry(entry)"
                    >
                      {{ $t("actions.save") }}
                    </button>
                    <button
                      v-if="activeDictionaryGroup.canArchive"
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      @click="archiveDictionaryEntry(entry)"
                    >
                      ×
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>
<script>
import { computed, reactive, ref, watch } from "vue";
import ItemMechanicsEditor from "@/components/shop/common/ItemMechanicsEditor.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

const emptyEntryDraft = () => ({
  code: "",
  labelPl: "",
  labelEn: "",
  parentCategory: "",
  itemClass: "",
  itemGenre: "",
  appliesToText: "",
  appliesToCodes: [],
});

export default {
  name: "ShopWorkspaceDictionaries",
  components: { ItemMechanicsEditor },
  setup() {
    const context = useShopWorkspaceContext();
    const selectedDictionaryGroup = ref("icon_categories");
    const newEntryOpen = ref(false);
    const newEntryDraft = reactive(emptyEntryDraft());
    const newEntryError = ref("");
    const rowErrors = reactive({});
    const activeMechanicsEntryCode = ref("");
    const activeDictionaryGroup = computed(
      () =>
        context.dictionaryGroups.value?.find(
          (group) => group.key === selectedDictionaryGroup.value,
        ) ||
        context.dictionaryGroups.value?.[0] ||
        null,
    );
    const entriesFor = (group) =>
      context.dictionaryGroups.value?.find((entry) => entry.key === group)
        ?.entries || [];
    const categoryEntries = computed(() => entriesFor("icon_categories"));
    const classEntries = computed(() => entriesFor("classes"));
    const genreEntries = computed(() => entriesFor("genres"));
    const relationOptions = computed(() =>
      entriesFor(activeDictionaryGroup.value?.relationOptionsGroup),
    );
    const isCategoryGroup = computed(
      () => activeDictionaryGroup.value?.relationKind === "category",
    );
    const isSubcategoryGroup = computed(
      () => activeDictionaryGroup.value?.relationKind === "subcategory",
    );
    const usesTaxonomyRelations = computed(
      () => isCategoryGroup.value || isSubcategoryGroup.value,
    );
    const mechanicsSupported = computed(() =>
      ["classes", "genres"].includes(activeDictionaryGroup.value?.key),
    );
    const mechanicsOwnSource = computed(() =>
      activeDictionaryGroup.value?.key === "classes" ? "CLASS" : "GENRE",
    );
    const activeMechanicsEntry = computed(() =>
      activeDictionaryGroup.value?.entries.find(
        (entry) => entry.code === activeMechanicsEntryCode.value,
      ),
    );
    const relationValues = (draft) =>
      String(draft.appliesToText || "")
        .split(",")
        .map((value) => value.trim().toUpperCase())
        .filter(Boolean);
    const relationWithPrefix = (values, prefix) =>
      values
        .find((value) => value.startsWith(`${prefix}:`))
        ?.slice(prefix.length + 1) || "";
    const entryDraft = (entry) => {
      const draft = context.dictionaryDraft(
        activeDictionaryGroup.value.key,
        entry,
      );
      if (!Array.isArray(draft.appliesToCodes)) {
        draft.appliesToCodes = relationValues(draft);
      }
      if (!Object.prototype.hasOwnProperty.call(draft, "parentCategory")) {
        const values = relationValues(draft);
        const categoryCodes = new Set(
          categoryEntries.value.map((option) => option.code),
        );
        draft.parentCategory =
          relationWithPrefix(values, "CATEGORY") ||
          values.find((value) => categoryCodes.has(value)) ||
          "";
        draft.itemClass = relationWithPrefix(values, "CLASS");
        draft.itemGenre = relationWithPrefix(values, "GENRE");
      }
      return draft;
    };
    const resetNewEntry = () => {
      Object.assign(newEntryDraft, emptyEntryDraft());
      newEntryError.value = "";
      if (isCategoryGroup.value) {
        newEntryDraft.itemGenre = "UTILITY";
      }
    };
    const startAddingEntry = () => {
      resetNewEntry();
      newEntryOpen.value = true;
    };
    const cancelAddingEntry = () => {
      newEntryOpen.value = false;
      resetNewEntry();
    };
    const normalizeCode = (value) =>
      String(value || "")
        .toUpperCase()
        .replace(/[^A-Z0-9_]/gu, "_")
        .replace(/^[^A-Z]+/u, "");
    const normalizeNewCode = () => {
      newEntryDraft.code = normalizeCode(newEntryDraft.code);
    };
    const normalizeEntryCode = (entry) => {
      const draft = entryDraft(entry);
      draft.code = normalizeCode(draft.code);
    };
    const unlockCode = (entry) => {
      entryDraft(entry).codeUnlocked = true;
    };
    const cancelCodeChange = (entry) => {
      const draft = entryDraft(entry);
      draft.code = entry.code;
      draft.codeUnlocked = false;
    };
    const applyParentDefaults = () => {
      if (
        !newEntryDraft.itemClass &&
        classEntries.value.some(
          (entry) => entry.code === newEntryDraft.parentCategory,
        )
      ) {
        newEntryDraft.itemClass = newEntryDraft.parentCategory;
      }
    };
    const composeRelations = (draft) => {
      const renamedFrom = relationValues(draft).filter((value) =>
        value.startsWith("RENAMED_FROM:"),
      );
      if (isCategoryGroup.value) {
        return [
          draft.itemClass && `CLASS:${draft.itemClass}`,
          draft.itemGenre && `GENRE:${draft.itemGenre}`,
          ...renamedFrom,
        ].filter(Boolean);
      }
      if (isSubcategoryGroup.value) {
        return [
          draft.parentCategory && `CATEGORY:${draft.parentCategory}`,
          draft.itemClass && `CLASS:${draft.itemClass}`,
          draft.itemGenre && `GENRE:${draft.itemGenre}`,
          ...renamedFrom,
        ].filter(Boolean);
      }
      return [...(draft.appliesToCodes || [])];
    };
    const multiSelectOptions = (draft) => {
      const known = new Set(relationOptions.value.map((entry) => entry.code));
      return [
        ...relationOptions.value,
        ...(draft.appliesToCodes || [])
          .filter((code) => !known.has(code))
          .map((code) => ({
            code,
            labelPl: code,
            labelEn: code,
          })),
      ];
    };
    const multiSelectionSummary = (draft) => {
      const selected = draft.appliesToCodes || [];
      if (!selected.length) {
        return context.t("shop.workspace.dictionaries.noRestrictions");
      }
      const labels = multiSelectOptions(draft)
        .filter((entry) => selected.includes(entry.code))
        .map((entry) => entry.labelPl || entry.code);
      if (labels.length <= 2) return labels.join(", ");
      return `${labels.slice(0, 2).join(", ")} +${labels.length - 2}`;
    };
    const validateDraft = (draft, requireParent = false) => {
      if (!/^[A-Z][A-Z0-9_]{1,63}$/u.test(draft.code || "")) {
        return context.t("shop.workspace.dictionaries.invalidCode");
      }
      if (!draft.labelPl || !draft.labelEn) {
        return context.t("shop.workspace.dictionaries.requiredLabels");
      }
      if (requireParent && !draft.parentCategory) {
        return context.t("shop.workspace.dictionaries.parentRequired");
      }
      return "";
    };
    const createDictionaryEntry = async () => {
      const error = validateDraft(newEntryDraft, isSubcategoryGroup.value);
      if (error) {
        newEntryError.value = error;
        return;
      }
      const saved = await context.addDictionaryEntry(
        activeDictionaryGroup.value.key,
        {
          ...newEntryDraft,
          appliesTo: composeRelations(newEntryDraft),
        },
      );
      if (!saved) {
        newEntryError.value = context.t(
          "shop.workspace.dictionaries.saveError",
        );
        return;
      }
      cancelAddingEntry();
    };
    const saveExistingEntry = async (entry) => {
      const draft = entryDraft(entry);
      const key = entry.id || entry.code;
      const error = validateDraft(draft, isSubcategoryGroup.value);
      rowErrors[key] = error;
      if (error) return null;
      const saved = await context.saveDictionaryEntry(
        activeDictionaryGroup.value.key,
        entry,
        {
          ...draft,
          appliesTo: composeRelations(draft),
        },
      );
      rowErrors[key] = saved
        ? ""
        : context.t("shop.workspace.dictionaries.saveError");
      return saved;
    };
    const openMechanicsEditor = (entry) => {
      activeMechanicsEntryCode.value = entry.code;
    };
    const closeMechanicsEditor = () => {
      activeMechanicsEntryCode.value = "";
    };
    const updateActiveMechanics = (mechanics) => {
      if (!activeMechanicsEntry.value) return;
      entryDraft(activeMechanicsEntry.value).mechanics = mechanics;
    };
    const saveActiveMechanics = async () => {
      if (!activeMechanicsEntry.value) return;
      const saved = await saveExistingEntry(activeMechanicsEntry.value);
      if (saved) closeMechanicsEditor();
    };
    watch(selectedDictionaryGroup, () => {
      cancelAddingEntry();
      closeMechanicsEditor();
    });
    return {
      ...context,
      selectedDictionaryGroup,
      activeDictionaryGroup,
      categoryEntries,
      classEntries,
      genreEntries,
      relationOptions,
      isCategoryGroup,
      isSubcategoryGroup,
      usesTaxonomyRelations,
      mechanicsSupported,
      mechanicsOwnSource,
      activeMechanicsEntry,
      newEntryOpen,
      newEntryDraft,
      newEntryError,
      rowErrors,
      entryDraft,
      startAddingEntry,
      cancelAddingEntry,
      normalizeNewCode,
      normalizeEntryCode,
      unlockCode,
      cancelCodeChange,
      applyParentDefaults,
      multiSelectOptions,
      multiSelectionSummary,
      createDictionaryEntry,
      saveExistingEntry,
      openMechanicsEditor,
      closeMechanicsEditor,
      updateActiveMechanics,
      saveActiveMechanics,
    };
  },
};
</script>
