<template>
  <section class="admin-professions">
    <aside class="admin-professions__catalog">
      <header>
        <div>
          <p>{{ $t("admin.professions.eyebrow") }}</p>
          <h2>{{ $t("admin.professions.title") }}</h2>
        </div>
        <button type="button" class="admin-primary" @click="createNew()">
          {{ $t("admin.professions.add") }}
        </button>
      </header>

      <div class="admin-professions__filters">
        <input
          v-model.trim="query"
          type="search"
          :placeholder="$t('admin.professions.search')"
        />
        <div>
          <select v-model="systemFilter">
            <option value="all">
              {{ $t("admin.professions.allSystems") }}
            </option>
            <option
              v-for="system in systems"
              :key="system.id"
              :value="system.id"
            >
              {{ system.name }}
            </option>
          </select>
          <select v-model="typeFilter">
            <option value="all">{{ $t("admin.professions.allTypes") }}</option>
            <option value="basic">{{ $t("admin.professions.basic") }}</option>
            <option value="advanced">
              {{ $t("admin.professions.advanced") }}
            </option>
          </select>
        </div>
        <small>{{
          $t("admin.professions.resultCount", {
            visible: filtered.length,
            total: items.length,
          })
        }}</small>
      </div>

      <p v-if="loading" class="admin-professions__state">
        {{ $t("admin.professions.loading") }}
      </p>
      <p v-else-if="loadError" class="admin-alert error" role="alert">
        {{ $t("admin.professions.loadError") }}
        <button type="button" @click="load">
          {{ $t("admin.actions.retry") }}
        </button>
      </p>
      <p v-else-if="!filtered.length" class="admin-professions__state">
        {{
          items.length
            ? $t("admin.professions.noResults")
            : $t("admin.professions.empty")
        }}
      </p>
      <ol v-else class="admin-professions__list">
        <li v-for="profession in filtered" :key="profession.id">
          <button
            type="button"
            :class="{ active: profession.id === selectedId }"
            @click="edit(profession)"
          >
            <span>
              <strong>{{ profession.name }}</strong>
              <small>#{{ profession.id }} · {{ profession.systemCode }}</small>
            </span>
            <em :class="{ advanced: profession.isAdvanced }">
              {{
                $t(
                  profession.isAdvanced
                    ? "admin.professions.advancedShort"
                    : "admin.professions.basicShort",
                )
              }}
            </em>
          </button>
        </li>
      </ol>
    </aside>

    <main v-if="draft" class="admin-profession-editor">
      <header>
        <div>
          <small>{{
            selectedId
              ? $t("admin.professions.edit", { id: selectedId })
              : $t("admin.professions.create")
          }}</small>
          <h2>{{ draft.name || $t("admin.professions.untitled") }}</h2>
        </div>
        <button
          v-if="selectedId"
          type="button"
          class="admin-danger"
          :disabled="saving || !original?.canDelete"
          :title="
            original?.canDelete
              ? undefined
              : $t('admin.professions.deleteBlocked')
          "
          @click="remove"
        >
          {{ $t("admin.professions.delete") }}
        </button>
      </header>

      <p v-if="saveError" class="admin-alert error" role="alert">
        {{ saveError }}
      </p>
      <p v-else-if="notice" class="admin-alert success" role="status">
        {{ notice }}
      </p>

      <form @submit.prevent="save">
        <div class="admin-profession-editor__workspace">
          <article class="admin-profession-editor__article">
            <header>
              <div>
                <span>{{ $t("admin.professions.entryContent") }}</span>
                <small>{{ $t("admin.professions.articleHint") }}</small>
              </div>
              <label class="admin-profession-editor__title">
                <span>{{ $t("admin.professions.name") }}</span>
                <input
                  v-model="draft.name"
                  type="text"
                  maxlength="255"
                  required
                  :placeholder="$t('admin.professions.untitled')"
                  :aria-invalid="fieldErrors.name ? 'true' : undefined"
                  @input="clearFieldError('name')"
                />
                <small v-if="fieldErrors.name" class="field-error">
                  {{ $t("admin.professions.nameError") }}
                </small>
              </label>
            </header>

            <div class="admin-profession-editor__texts">
              <label>
                <span>{{ $t("admin.professions.description") }}</span>
                <textarea
                  v-model="draft.description"
                  rows="18"
                  :placeholder="$t('admin.professions.descriptionPlaceholder')"
                  :aria-invalid="fieldErrors.description ? 'true' : undefined"
                  @input="clearFieldError('description')"
                ></textarea>
                <small>{{ $t("admin.professions.plainTextHint") }}</small>
              </label>
              <label>
                <span>{{ $t("admin.professions.details") }}</span>
                <textarea
                  v-model="draft.details"
                  rows="8"
                  :placeholder="$t('admin.professions.detailsPlaceholder')"
                  :aria-invalid="fieldErrors.details ? 'true' : undefined"
                  @input="clearFieldError('details')"
                ></textarea>
                <small>{{ $t("admin.professions.detailsHint") }}</small>
              </label>
            </div>
          </article>

          <aside class="admin-profession-editor__inspector">
            <section class="admin-profession-editor__metadata">
              <header>
                <strong>{{ $t("admin.professions.metadata") }}</strong>
              </header>
              <label>
                <span>{{ $t("admin.professions.system") }}</span>
                <select
                  v-model.number="draft.systemId"
                  required
                  :disabled="Boolean(selectedId)"
                  :aria-invalid="fieldErrors.systemId ? 'true' : undefined"
                  @change="systemChanged"
                >
                  <option :value="null" disabled>
                    {{ $t("admin.professions.chooseSystem") }}
                  </option>
                  <option
                    v-for="system in systems"
                    :key="system.id"
                    :value="system.id"
                  >
                    {{ system.name }}
                  </option>
                </select>
                <small>
                  {{
                    selectedId
                      ? $t("admin.professions.systemLocked")
                      : $t("admin.professions.systemHint")
                  }}
                </small>
              </label>

              <fieldset class="admin-profession-editor__flags">
                <legend>{{ $t("admin.professions.classification") }}</legend>
                <label>
                  <input v-model="draft.isAdvanced" type="checkbox" />
                  <span>
                    <strong>{{ $t("admin.professions.advancedFlag") }}</strong>
                    <small>{{ $t("admin.professions.advancedHint") }}</small>
                  </span>
                </label>
                <label>
                  <input v-model="draft.isMain" type="checkbox" />
                  <span>
                    <strong>{{ $t("admin.professions.mainFlag") }}</strong>
                    <small>{{ $t("admin.professions.mainHint") }}</small>
                  </span>
                </label>
              </fieldset>
            </section>

            <section class="admin-profession-editor__mechanics">
              <header>
                <div>
                  <strong>{{ $t("admin.professions.mechanics") }}</strong>
                  <small>{{ $t("admin.professions.mechanicsHint") }}</small>
                </div>
              </header>
              <AdminProfessionRequirementPicker
                v-model="draft.skillItems"
                :options="skillOptions"
                :label="$t('admin.professions.skills')"
                :placeholder="$t('admin.professions.skillsSearch')"
                :hint="$t('admin.professions.requirementHint')"
              />
              <AdminProfessionRequirementPicker
                v-model="draft.talentItems"
                :options="talentOptions"
                :label="$t('admin.professions.talents')"
                :placeholder="$t('admin.professions.talentsSearch')"
                :hint="$t('admin.professions.requirementHint')"
              />
            </section>

            <section class="admin-profession-editor__images">
              <header>
                <strong>{{ $t("admin.professions.figures") }}</strong>
                <small>{{ $t("admin.professions.figuresHint") }}</small>
              </header>
              <div>
                <article v-for="slot in imageSlots" :key="slot">
                  <div class="admin-profession-editor__image-preview">
                    <AuthenticatedImage
                      v-if="imageSrc(slot)"
                      :src="imageSrc(slot)"
                      :alt="$t(`admin.professions.${slot}Figure`)"
                    />
                    <span v-else aria-hidden="true">＋</span>
                  </div>
                  <div>
                    <strong>{{ $t(`admin.professions.${slot}Figure`) }}</strong>
                    <small>{{ imageMeta(slot) }}</small>
                    <label class="admin-profession-editor__file">
                      <span>{{ $t("admin.professions.chooseImage") }}</span>
                      <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        @change="chooseImage(slot, $event)"
                      />
                    </label>
                    <button
                      v-if="original?.images?.[slot] && !pendingImages[slot]"
                      type="button"
                      class="admin-profession-editor__remove-image"
                      :disabled="saving"
                      @click="removeImage(slot)"
                    >
                      {{ $t("admin.professions.removeImage") }}
                    </button>
                  </div>
                </article>
              </div>
            </section>

            <aside v-if="original" class="admin-profession-editor__usage">
              <span>
                {{
                  $t("admin.professions.characterUsage", {
                    count: original.usage.characters,
                  })
                }}
              </span>
              <span>
                {{
                  $t("admin.professions.pathUsage", {
                    count: original.usage.pathReferences,
                  })
                }}
              </span>
              <span>
                {{
                  $t("admin.professions.updated", {
                    date: formatDate(original.updatedAt),
                  })
                }}
              </span>
            </aside>
          </aside>
        </div>

        <footer>
          <span v-if="dirty">{{ $t("admin.professions.unsaved") }}</span>
          <div>
            <button
              type="button"
              :disabled="saving || !dirty"
              @click="resetDraft"
            >
              {{ $t("admin.professions.reset") }}
            </button>
            <button
              type="submit"
              class="admin-primary"
              :disabled="saving || !valid || !dirty"
            >
              {{
                saving
                  ? $t("admin.professions.saving")
                  : $t("admin.professions.save")
              }}
            </button>
          </div>
        </footer>
      </form>
    </main>

    <div v-else class="admin-professions__welcome">
      <h2>{{ $t("admin.professions.welcomeTitle") }}</h2>
      <p>{{ $t("admin.professions.welcomeBody") }}</p>
      <button type="button" class="admin-primary" @click="createNew()">
        {{ $t("admin.professions.add") }}
      </button>
    </div>
  </section>
</template>

<script>
import { adminApiClient } from "@/lib/admin/adminApiClient";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import AdminProfessionRequirementPicker from "./AdminProfessionRequirementPicker.vue";

const requirementItems = (items, raw = "", display = "") => {
  if (Array.isArray(items) && items.length) {
    return items.map((item) => ({ ...item }));
  }
  return raw
    ? [{ raw: String(raw), display: String(display || raw), decoded: false }]
    : [];
};

const serializeRequirements = (items) =>
  (items || [])
    .map((item) => String(item.raw || "").trim())
    .filter(Boolean)
    .join(",");

const emptyDraft = (systemId = null) => ({
  systemId,
  name: "",
  description: "",
  details: "",
  isAdvanced: false,
  isMain: true,
  skillItems: [],
  talentItems: [],
});

const professionDraft = (profession) => ({
  systemId: profession.systemId,
  name: profession.name,
  description: profession.description || "",
  details: profession.details || "",
  isAdvanced: profession.isAdvanced,
  isMain: profession.isMain,
  skillItems: requirementItems(
    profession.skillItems,
    profession.skills,
    profession.skillsDecoded,
  ),
  talentItems: requirementItems(
    profession.talentItems,
    profession.talents,
    profession.talentsDecoded,
  ),
});

const snapshot = (draft) =>
  JSON.stringify({
    systemId: Number(draft?.systemId) || null,
    name: String(draft?.name || ""),
    description: String(draft?.description || ""),
    details: String(draft?.details || ""),
    isAdvanced: Boolean(draft?.isAdvanced),
    isMain: Boolean(draft?.isMain),
    skills: serializeRequirements(draft?.skillItems),
    talents: serializeRequirements(draft?.talentItems),
  });

const searchText = (value) =>
  String(value || "")
    .toLocaleLowerCase("pl-PL")
    .replace(/ł/gu, "l")
    .normalize("NFD")
    .replace(/\p{Diacritic}/gu, "");

export default {
  name: "AdminProfessionsTab",
  components: { AdminProfessionRequirementPicker, AuthenticatedImage },
  emits: ["loaded"],
  data: () => ({
    items: [],
    systems: [],
    requirementOptions: { skills: [], talents: [] },
    query: "",
    systemFilter: "all",
    typeFilter: "all",
    selectedId: null,
    original: null,
    draft: null,
    baseline: "",
    loading: true,
    saving: false,
    loadError: null,
    saveError: "",
    notice: "",
    fieldErrors: {},
    pendingImages: { male: null, female: null },
    pendingImageUrls: { male: "", female: "" },
  }),
  computed: {
    imageSlots() {
      return ["male", "female"];
    },
    skillOptions() {
      return this.optionsFor("skills");
    },
    talentOptions() {
      return this.optionsFor("talents");
    },
    filtered() {
      const needle = searchText(this.query);
      return this.items
        .filter((item) => {
          if (
            this.systemFilter !== "all" &&
            item.systemId !== Number(this.systemFilter)
          ) {
            return false;
          }
          if (this.typeFilter === "advanced" && !item.isAdvanced) return false;
          if (this.typeFilter === "basic" && item.isAdvanced) return false;
          return (
            !needle ||
            searchText(
              `${item.name} ${item.description || ""} ${
                item.details || ""
              } ${item.skillsDecoded || ""} ${item.talentsDecoded || ""}`,
            ).includes(needle)
          );
        })
        .sort(
          (left, right) =>
            left.name.localeCompare(right.name, "pl", {
              sensitivity: "base",
            }) || left.id - right.id,
        );
    },
    dirty() {
      return (
        Boolean(this.draft) &&
        (snapshot(this.draft) !== this.baseline ||
          this.imageSlots.some((slot) => Boolean(this.pendingImages[slot])))
      );
    },
    valid() {
      return Boolean(this.draft?.name?.trim() && Number(this.draft?.systemId));
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    this.clearPendingImages();
  },
  methods: {
    async load() {
      this.loading = true;
      this.loadError = null;
      try {
        const result = await adminApiClient.professions();
        this.items = result.items;
        this.systems = result.systems;
        this.requirementOptions = result.requirementOptions;
        this.$emit("loaded", this.items.length);
        if (this.selectedId) {
          const current = this.items.find(
            (item) => item.id === this.selectedId,
          );
          if (current) this.setEditor(current);
          else this.clearEditor();
        }
      } catch (error) {
        this.loadError = error;
      } finally {
        this.loading = false;
      }
    },
    createNew(force = false) {
      if (!force && !this.allowDiscard()) return;
      this.selectedId = null;
      this.original = null;
      const firstSystem =
        this.systemFilter !== "all"
          ? Number(this.systemFilter)
          : this.systems[0]?.id || null;
      this.setDraft(emptyDraft(firstSystem));
    },
    edit(profession) {
      if (profession.id === this.selectedId || !this.allowDiscard()) return;
      this.setEditor(profession);
    },
    setEditor(profession) {
      this.selectedId = profession.id;
      this.original = profession;
      this.setDraft(professionDraft(profession));
    },
    setDraft(draft) {
      this.clearPendingImages();
      this.draft = {
        ...draft,
        skillItems: requirementItems(draft.skillItems),
        talentItems: requirementItems(draft.talentItems),
      };
      this.baseline = snapshot(this.draft);
      this.saveError = "";
      this.notice = "";
      this.fieldErrors = {};
    },
    clearEditor() {
      this.selectedId = null;
      this.original = null;
      this.draft = null;
      this.baseline = "";
      this.clearPendingImages();
    },
    allowDiscard() {
      return (
        !this.dirty ||
        window.confirm(this.$t("admin.professions.discardConfirm"))
      );
    },
    resetDraft() {
      if (this.original) this.setEditor(this.original);
      else
        this.setDraft(emptyDraft(this.draft?.systemId || this.systems[0]?.id));
    },
    payload() {
      const payload = {
        name: this.draft.name.trim(),
        description: this.draft.description.trim() || null,
        details: this.draft.details.trim() || null,
        isAdvanced: Boolean(this.draft.isAdvanced),
        isMain: Boolean(this.draft.isMain),
        skills: serializeRequirements(this.draft.skillItems),
        talents: serializeRequirements(this.draft.talentItems),
      };
      if (this.selectedId) payload.updatedAt = this.original.updatedAt;
      else payload.systemId = Number(this.draft.systemId);
      return payload;
    },
    async save() {
      if (!this.valid || !this.dirty || this.saving) return;
      let profession = this.original;
      this.saving = true;
      this.saveError = "";
      this.notice = "";
      this.fieldErrors = {};
      try {
        const coreDirty = snapshot(this.draft) !== this.baseline;
        if (!this.selectedId) {
          profession = await adminApiClient.createProfession(this.payload());
        } else if (coreDirty) {
          profession = await adminApiClient.updateProfession(
            this.selectedId,
            this.payload(),
          );
        }
        for (const slot of this.imageSlots) {
          if (!this.pendingImages[slot]) continue;
          profession = await adminApiClient.uploadProfessionImage(
            profession.id,
            slot,
            this.pendingImages[slot],
          );
          this.clearPendingImage(slot);
        }
        const index = this.items.findIndex((item) => item.id === profession.id);
        if (index < 0) this.items.push(profession);
        else this.items.splice(index, 1, profession);
        this.$emit("loaded", this.items.length);
        this.setEditor(profession);
        this.notice = this.$t("admin.professions.saved");
        this.invalidatePlayerCatalog();
      } catch (error) {
        if (profession && profession !== this.original) {
          const index = this.items.findIndex(
            (item) => item.id === profession.id,
          );
          if (index < 0) this.items.push(profession);
          else this.items.splice(index, 1, profession);
          this.selectedId = profession.id;
          this.original = profession;
          this.baseline = snapshot(professionDraft(profession));
        }
        this.fieldErrors = error?.payload?.errors || {};
        this.saveError = this.errorMessage(error);
      } finally {
        this.saving = false;
      }
    },
    async remove() {
      if (!this.original?.canDelete || this.saving) return;
      if (
        !window.confirm(
          this.$t("admin.professions.deleteConfirm", {
            name: this.original.name,
          }),
        )
      ) {
        return;
      }
      this.saving = true;
      this.saveError = "";
      try {
        await adminApiClient.deleteProfession(
          this.original.id,
          this.original.updatedAt,
        );
        this.items = this.items.filter((item) => item.id !== this.original.id);
        this.$emit("loaded", this.items.length);
        this.clearEditor();
        this.invalidatePlayerCatalog();
      } catch (error) {
        this.saveError = this.errorMessage(error);
      } finally {
        this.saving = false;
      }
    },
    clearFieldError(field) {
      if (!this.fieldErrors[field]) return;
      const errors = { ...this.fieldErrors };
      delete errors[field];
      this.fieldErrors = errors;
    },
    optionsFor(type) {
      const systemId = Number(this.draft?.systemId);
      return (this.requirementOptions[type] || []).filter(
        (option) => option.systemId === systemId,
      );
    },
    systemChanged() {
      this.clearFieldError("systemId");
      if (this.selectedId) return;
      this.draft.skillItems = [];
      this.draft.talentItems = [];
    },
    chooseImage(slot, event) {
      const file = event?.target?.files?.[0] || null;
      if (!file) return;
      if (
        !["image/png", "image/jpeg", "image/webp"].includes(file.type) ||
        file.size < 1 ||
        file.size > 25 * 1024 * 1024
      ) {
        this.saveError = this.$t("admin.professions.imageInvalid");
        event.target.value = "";
        return;
      }
      if (this.pendingImageUrls[slot]) {
        URL.revokeObjectURL(this.pendingImageUrls[slot]);
      }
      this.pendingImages = { ...this.pendingImages, [slot]: file };
      this.pendingImageUrls = {
        ...this.pendingImageUrls,
        [slot]: URL.createObjectURL(file),
      };
      this.saveError = "";
    },
    imageSrc(slot) {
      return this.pendingImageUrls[slot] || this.original?.images?.[slot]?.url;
    },
    imageMeta(slot) {
      const file = this.pendingImages[slot];
      if (file) return `${file.name} · ${this.formatBytes(file.size)}`;
      const image = this.original?.images?.[slot];
      if (!image) return this.$t("admin.professions.imageMissing");
      return `${image.name} · ${image.width}×${image.height}`;
    },
    async removeImage(slot) {
      if (!this.selectedId || !this.original?.images?.[slot] || this.saving) {
        return;
      }
      if (!window.confirm(this.$t("admin.professions.removeImageConfirm"))) {
        return;
      }
      this.saving = true;
      this.saveError = "";
      try {
        const profession = await adminApiClient.deleteProfessionImage(
          this.selectedId,
          slot,
        );
        const index = this.items.findIndex((item) => item.id === profession.id);
        if (index >= 0) this.items.splice(index, 1, profession);
        this.original = profession;
        this.baseline = snapshot(professionDraft(profession));
        this.invalidatePlayerCatalog();
      } catch (error) {
        this.saveError = this.errorMessage(error);
      } finally {
        this.saving = false;
      }
    },
    clearPendingImages() {
      Object.values(this.pendingImageUrls || {}).forEach((url) => {
        if (url) URL.revokeObjectURL(url);
      });
      this.pendingImages = { male: null, female: null };
      this.pendingImageUrls = { male: "", female: "" };
    },
    clearPendingImage(slot) {
      if (this.pendingImageUrls[slot]) {
        URL.revokeObjectURL(this.pendingImageUrls[slot]);
      }
      this.pendingImages = { ...this.pendingImages, [slot]: null };
      this.pendingImageUrls = { ...this.pendingImageUrls, [slot]: "" };
    },
    formatBytes(value) {
      const bytes = Number(value || 0);
      if (bytes < 1024 * 1024)
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
      return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    },
    errorMessage(error) {
      const code = String(error?.payload?.code || error?.code || "");
      if (code === "profession_in_use") {
        return this.$t("admin.professions.inUseError");
      }
      if (error?.status === 409) return this.$t("admin.professions.conflict");
      if (error?.status === 422) return this.$t("admin.professions.validation");
      return this.$t("admin.professions.saveError");
    },
    invalidatePlayerCatalog() {
      this.$store.commit("professions/SET_CONTEXT", null);
    },
    formatDate(value) {
      if (!value) return this.$t("admin.professions.unknownDate");
      const date = new Date(String(value).replace(" ", "T"));
      return Number.isNaN(date.getTime())
        ? String(value)
        : new Intl.DateTimeFormat(this.$i18n.locale, {
            dateStyle: "short",
            timeStyle: "short",
          }).format(date);
    },
  },
};
</script>
