<template>
  <section class="admin-token-templates">
    <aside class="admin-token-templates__library">
      <header>
        <div>
          <p>{{ $t("admin.tokenTemplates.eyebrow") }}</p>
          <h2>{{ $t("admin.tokenTemplates.title") }}</h2>
        </div>
        <button type="button" class="admin-primary" @click="createNew">
          {{ $t("admin.tokenTemplates.add") }}
        </button>
      </header>
      <input
        v-model.trim="query"
        type="search"
        :placeholder="$t('admin.tokenTemplates.search')"
      />
      <p v-if="loading" class="admin-token-templates__state">
        {{ $t("admin.tokenTemplates.loading") }}
      </p>
      <p v-else-if="loadError" class="admin-alert error" role="alert">
        {{ $t("admin.tokenTemplates.loadError") }}
        <button type="button" @click="load">
          {{ $t("admin.actions.retry") }}
        </button>
      </p>
      <p v-else-if="!filtered.length" class="admin-token-templates__state">
        {{
          query
            ? $t("admin.tokenTemplates.noResults")
            : $t("admin.tokenTemplates.empty")
        }}
      </p>
      <div v-else class="admin-token-templates__cards">
        <button
          v-for="template in filtered"
          :key="template.id"
          type="button"
          :class="{ active: template.id === selectedId }"
          @click="edit(template)"
        >
          <AuthenticatedImage
            :src="template.imageUrl"
            alt=""
            draggable="false"
          />
          <span>
            <strong>{{ template.name }}</strong>
            <small
              >{{ template.widthCells }} × {{ template.heightCells }}</small
            >
          </span>
        </button>
      </div>
    </aside>

    <main v-if="draft" class="admin-token-template-editor">
      <header>
        <div>
          <small>{{
            selectedId
              ? $t("admin.tokenTemplates.edit")
              : $t("admin.tokenTemplates.create")
          }}</small>
          <h2>{{ draft.name || $t("admin.tokenTemplates.untitled") }}</h2>
        </div>
        <button
          v-if="selectedId"
          type="button"
          class="admin-danger"
          :disabled="saving"
          @click="remove"
        >
          {{ $t("admin.tokenTemplates.delete") }}
        </button>
      </header>

      <p v-if="saveError" class="admin-alert error" role="alert">
        {{ saveError }}
      </p>

      <div class="admin-token-template-editor__image-source">
        <label>
          <input v-model="imageMode" type="radio" value="url" />
          {{ $t("admin.tokenTemplates.imageUrl") }}
        </label>
        <label>
          <input v-model="imageMode" type="radio" value="upload" />
          {{ $t("admin.tokenTemplates.imageUpload") }}
        </label>
        <label
          v-if="imageMode === 'upload'"
          class="admin-token-template-editor__file"
        >
          <span>{{ $t("admin.tokenTemplates.chooseFile") }}</span>
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp,image/gif"
            @change="chooseFile"
          />
          <small>{{ file?.name || $t("admin.tokenTemplates.fileHint") }}</small>
        </label>
      </div>

      <nav role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab"
          type="button"
          :class="{ active: activeTab === tab }"
          @click="activeTab = tab"
        >
          {{ $t(`admin.tokenTemplates.tabs.${tab}`) }}
        </button>
      </nav>

      <div class="admin-token-template-editor__workspace">
        <TokenSettingsPreview
          :draft="draft"
          :token="previewToken"
          grid-type="square"
        />
        <div
          class="admin-token-template-editor__fields"
          :class="{
            'admin-token-template-editor--upload': imageMode === 'upload',
          }"
        >
          <TokenAppearanceSettings
            v-show="activeTab === 'appearance'"
            v-model="draft"
            can-manage
          />
          <section
            v-show="activeTab === 'movement'"
            class="admin-token-template-editor__movement"
          >
            <label>
              <span>{{ $t("vtt.token.movement.range") }}</span>
              <input
                :value="draft.movementRange"
                type="number"
                min="0"
                max="10000"
                step="0.5"
                @input="changeMovementRange($event.target.value)"
              />
            </label>
            <label>
              <span>{{ $t("vtt.token.movement.resetMode") }}</span>
              <select v-model="draft.movementResetMode">
                <option value="turn">
                  {{ $t("vtt.token.movement.turn") }}
                </option>
                <option value="round">
                  {{ $t("vtt.token.movement.round") }}
                </option>
                <option value="manual">
                  {{ $t("vtt.token.movement.manual") }}
                </option>
              </select>
            </label>
          </section>
          <TokenVisionSettings
            v-show="activeTab === 'vision'"
            v-model="draft.vision"
          />
          <TokenResourceSettings
            v-show="activeTab === 'resources'"
            v-model="draft.resources"
            :bar-position="draft.resourceBarPosition"
            can-manage
            @update:bar-position="draft.resourceBarPosition = $event"
          />
        </div>
      </div>

      <footer>
        <button type="button" :disabled="saving" @click="resetDraft">
          {{ $t("admin.tokenTemplates.reset") }}
        </button>
        <button
          type="button"
          class="admin-primary"
          :disabled="saving || !valid"
          @click="save"
        >
          {{
            saving
              ? $t("admin.tokenTemplates.saving")
              : $t("admin.tokenTemplates.save")
          }}
        </button>
      </footer>
    </main>

    <div v-else class="admin-token-templates__welcome">
      <h2>{{ $t("admin.tokenTemplates.welcomeTitle") }}</h2>
      <p>{{ $t("admin.tokenTemplates.welcomeBody") }}</p>
      <button type="button" class="admin-primary" @click="createNew">
        {{ $t("admin.tokenTemplates.add") }}
      </button>
    </div>
  </section>
</template>

<script>
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import TokenAppearanceSettings from "@/components/vtt/token/TokenAppearanceSettings.vue";
import TokenResourceSettings from "@/components/vtt/token/TokenResourceSettings.vue";
import TokenSettingsPreview from "@/components/vtt/token/TokenSettingsPreview.vue";
import TokenVisionSettings from "@/components/vtt/token/TokenVisionSettings.vue";
import { adminApiClient } from "@/lib/admin/adminApiClient";
import {
  createTokenSettingsDraft,
  tokenSettingsPayload,
} from "@/lib/vtt/tokenSettingsDraft";
import { tokenResourcesWithMovement } from "@/lib/vtt/tokenResources";

const pseudoToken = (template = {}) => ({
  ...template,
  width: Math.max(0.25, Number(template.widthCells) || 1) * 100,
  height: Math.max(0.25, Number(template.heightCells) || 1) * 100,
  movementSpent: 0,
  hidden: false,
  locked: false,
  capabilities: { canManage: true },
});

export default {
  name: "AdminTokenTemplatesTab",
  components: {
    AuthenticatedImage,
    TokenAppearanceSettings,
    TokenResourceSettings,
    TokenSettingsPreview,
    TokenVisionSettings,
  },
  emits: ["loaded"],
  data: () => ({
    items: [],
    query: "",
    selectedId: null,
    draft: null,
    original: null,
    imageMode: "url",
    file: null,
    filePreviewUrl: "",
    activeTab: "appearance",
    loading: true,
    saving: false,
    loadError: null,
    saveError: "",
    tabs: ["appearance", "movement", "vision", "resources"],
  }),
  computed: {
    filtered() {
      const needle = this.query.toLocaleLowerCase();
      return this.items.filter(
        (item) => !needle || item.name.toLocaleLowerCase().includes(needle),
      );
    },
    valid() {
      const hasImage =
        this.imageMode === "url"
          ? /^https:\/\//iu.test(String(this.draft?.imageUrl || ""))
          : Boolean(this.file || this.original?.imageAssetId);
      return (
        Boolean(this.draft?.name?.trim()) &&
        Number(this.draft?.widthCells) >= 0.25 &&
        Number(this.draft?.heightCells) >= 0.25 &&
        hasImage
      );
    },
    previewToken() {
      return pseudoToken(this.draft || {});
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    this.releaseFilePreview();
  },
  methods: {
    async load() {
      this.loading = true;
      this.loadError = null;
      try {
        this.items = await adminApiClient.tokenTemplates();
        this.$emit("loaded", this.items.length);
        if (this.selectedId) {
          const current = this.items.find(
            (item) => item.id === this.selectedId,
          );
          if (current) this.edit(current);
          else this.createNew();
        }
      } catch (error) {
        this.loadError = error;
      } finally {
        this.loading = false;
      }
    },
    createNew() {
      this.releaseFilePreview();
      this.selectedId = null;
      this.original = null;
      this.imageMode = "url";
      this.file = null;
      this.activeTab = "appearance";
      this.draft = createTokenSettingsDraft(
        pseudoToken({
          name: "",
          imageUrl: "",
          widthCells: 1,
          heightCells: 1,
          movementRange: 6,
          movementResetMode: "turn",
          resources: {},
          vision: {},
          rotationHandleEnabled: true,
          facingHandleEnabled: true,
        }),
        100,
      );
    },
    edit(template) {
      this.releaseFilePreview();
      this.selectedId = template.id;
      this.original = template;
      this.imageMode = template.imageAssetId ? "upload" : "url";
      this.file = null;
      this.activeTab = "appearance";
      this.draft = createTokenSettingsDraft(pseudoToken(template), 100);
    },
    resetDraft() {
      if (this.original) this.edit(this.original);
      else this.createNew();
      this.saveError = "";
    },
    chooseFile(event) {
      this.releaseFilePreview();
      this.file = event.target.files?.[0] || null;
      this.filePreviewUrl = this.file ? URL.createObjectURL(this.file) : "";
      this.draft.imageUrl =
        this.filePreviewUrl || this.original?.imageUrl || "";
    },
    releaseFilePreview() {
      if (this.filePreviewUrl) URL.revokeObjectURL(this.filePreviewUrl);
      this.filePreviewUrl = "";
    },
    changeMovementRange(value) {
      const range = Math.max(0, Math.min(10000, Number(value) || 0));
      this.draft.movementRange = range;
      this.draft.resources = tokenResourcesWithMovement(
        this.draft.resources,
        range,
        range,
      );
    },
    payload() {
      const normalized = tokenSettingsPayload(this.draft, 100, true);
      const payload = {
        name: normalized.name,
        widthCells: this.draft.widthCells,
        heightCells: this.draft.heightCells,
        rotation: normalized.rotation,
        facing: normalized.facing,
        rotationHandleEnabled: normalized.rotationHandleEnabled,
        facingHandleEnabled: normalized.facingHandleEnabled,
        rotationFollowsFacing: normalized.rotationFollowsFacing,
        showInfoUnselected: normalized.showInfoUnselected,
        resourceBarPosition: normalized.resourceBarPosition,
        elevation: normalized.elevation,
        disposition: normalized.disposition,
        movementRange: normalized.movementRange,
        movementResetMode: normalized.movementResetMode,
        resources: normalized.resources,
        vision: normalized.vision,
      };
      if (this.imageMode === "url") payload.imageUrl = normalized.imageUrl;
      if (this.selectedId) payload.revision = this.original.revision;
      return payload;
    },
    async save() {
      if (!this.valid || this.saving) return;
      this.saving = true;
      this.saveError = "";
      try {
        const template = this.selectedId
          ? await adminApiClient.updateTokenTemplate(
              this.selectedId,
              this.payload(),
              this.imageMode === "upload" ? this.file : null,
            )
          : await adminApiClient.createTokenTemplate(
              this.payload(),
              this.imageMode === "upload" ? this.file : null,
            );
        const index = this.items.findIndex((item) => item.id === template.id);
        if (index < 0) this.items.push(template);
        else this.items.splice(index, 1, template);
        this.items.sort((a, b) => a.name.localeCompare(b.name));
        this.$emit("loaded", this.items.length);
        this.edit(template);
      } catch (error) {
        this.saveError = this.errorMessage(error);
      } finally {
        this.saving = false;
      }
    },
    async remove() {
      if (!this.original || this.saving) return;
      if (
        !window.confirm(
          this.$t("admin.tokenTemplates.deleteConfirm", {
            name: this.original.name,
          }),
        )
      )
        return;
      this.saving = true;
      this.saveError = "";
      try {
        await adminApiClient.deleteTokenTemplate(
          this.original.id,
          this.original.revision,
        );
        this.items = this.items.filter((item) => item.id !== this.original.id);
        this.$emit("loaded", this.items.length);
        this.createNew();
      } catch (error) {
        this.saveError = this.errorMessage(error);
      } finally {
        this.saving = false;
      }
    },
    errorMessage(error) {
      if (error?.status === 409)
        return this.$t("admin.tokenTemplates.conflict");
      if (error?.status === 422)
        return this.$t("admin.tokenTemplates.validation");
      return this.$t("admin.tokenTemplates.saveError");
    },
  },
};
</script>
