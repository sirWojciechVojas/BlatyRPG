<template>
  <section class="handout-window" :aria-busy="loading">
    <header v-if="entity" class="handout-window__toolbar">
      <button v-if="canEdit && !editing" type="button" @click="beginEdit">
        {{ $t("vtt.table.handouts.edit") }}
      </button>
      <template v-if="editing">
        <button type="button" :disabled="saving" @click="save">
          {{ $t("vtt.table.handouts.save") }}
        </button>
        <button type="button" :disabled="saving" @click="cancelEdit">
          {{ $t("vtt.table.handouts.cancel") }}
        </button>
      </template>
      <button
        v-if="scope === 'library' && entity.id && !entity.deletedAt && !editing"
        type="button"
        :disabled="saving"
        @click="publish"
      >
        {{ $t("vtt.table.handouts.publish") }}
      </button>
      <button
        v-if="scope === 'campaign' && canPresent && !entity.deletedAt"
        type="button"
        :disabled="saving"
        :class="{ active: shareOpen }"
        @click="shareOpen = !shareOpen"
      >
        {{ $t("vtt.table.handouts.share") }}
      </button>
      <button
        v-if="canEdit && entity.id && !entity.deletedAt && editing"
        type="button"
        :disabled="saving"
        @click="trash"
      >
        {{ $t("vtt.table.handouts.trash") }}
      </button>
      <button
        v-if="canRestore"
        type="button"
        :disabled="saving"
        @click="restore"
      >
        {{ $t("vtt.table.handouts.restore") }}
      </button>
      <span class="handout-window__state">
        {{
          editing
            ? $t("vtt.table.handouts.editing")
            : $t("vtt.table.handouts.reading")
        }}
      </span>
    </header>

    <p v-if="error" class="handout-window__error" role="alert">
      {{ error }}
    </p>
    <p v-if="loading" class="handout-window__empty" role="status">
      {{ $t("vtt.table.handouts.loading") }}
    </p>

    <template v-else-if="entity">
      <section
        v-if="shareOpen && scope === 'campaign' && canPresent"
        class="handout-window__share"
      >
        <label>
          {{ $t("vtt.table.handouts.audience") }}
          <select v-model="shareMode">
            <option value="private">
              {{ $t("vtt.table.handouts.audiencePrivate") }}
            </option>
            <option value="all_active_members">
              {{ $t("vtt.table.handouts.audienceAll") }}
            </option>
            <option value="selected_active_members">
              {{ $t("vtt.table.handouts.audienceSelected") }}
            </option>
          </select>
        </label>
        <div
          v-if="shareMode === 'selected_active_members'"
          class="handout-window__recipients"
        >
          <label v-for="member in activeMembers" :key="memberId(member)">
            <input
              v-model="shareRecipients"
              type="checkbox"
              :value="memberId(member)"
            />
            {{ member.username || member.email || member.name }}
          </label>
        </div>
        <button type="button" :disabled="saving" @click="saveAudience">
          {{ $t("vtt.table.handouts.saveAudience") }}
        </button>
      </section>

      <template v-if="editing">
        <section class="handout-window__fields">
          <input
            v-model.trim="draft.title"
            :aria-label="$t('vtt.table.handouts.title')"
            :placeholder="$t('vtt.table.handouts.title')"
          />
          <select v-if="scope === 'library'" v-model="draft.folderId">
            <option :value="null">
              {{ $t("vtt.table.handouts.noFolder") }}
            </option>
            <option
              v-for="folder in libraryMeta.folders"
              :key="folder.id"
              :value="folder.id"
            >
              {{ folder.name }}
            </option>
          </select>
        </section>

        <div v-if="scope === 'library'" class="handout-window__tags">
          <label v-for="tag in libraryMeta.tags" :key="tag.id">
            <input
              type="checkbox"
              :checked="draft.tagIds.includes(tag.id)"
              @change="toggleLibraryTag(tag.id)"
            />
            {{ tag.name }}
          </label>
        </div>
        <div v-else class="handout-window__tags">
          <button
            v-for="tag in draft.tags"
            :key="tag"
            type="button"
            :title="$t('vtt.table.handouts.removeTag')"
            @click="removeCampaignTag(tag)"
          >
            {{ tag }} ×
          </button>
          <input
            v-model.trim="newTag"
            :placeholder="$t('vtt.table.handouts.newTag')"
            @keyup.enter="addCampaignTag"
          />
        </div>

        <HandoutEditor
          v-model="draft.content"
          :allow-mentions="scope === 'campaign'"
          :mention-targets="mentionTargets"
          @upload-image="uploadAsset"
          @error="showError"
        />

        <section v-if="scope === 'campaign'" class="handout-window__relations">
          <h3>{{ $t("vtt.table.handouts.relations") }}</h3>
          <ul>
            <li
              v-for="link in draftRelatedLinks"
              :key="`${link.targetType}:${link.targetId}`"
            >
              {{ link.label }}
              <button
                type="button"
                :aria-label="$t('vtt.table.handouts.removeRelation')"
                @click="removeRelatedLink(link)"
              >
                ×
              </button>
            </li>
          </ul>
          <select v-model="relatedTarget">
            <option value="">
              {{ $t("vtt.table.handouts.addRelation") }}
            </option>
            <option
              v-for="target in mentionTargets"
              :key="`${target.type}:${target.id}`"
              :value="`${target.type}:${target.id}`"
            >
              {{ target.label }}
            </option>
          </select>
          <button
            type="button"
            :disabled="!relatedTarget"
            @click="addRelatedLink"
          >
            {{ $t("vtt.table.handouts.add") }}
          </button>
        </section>
      </template>

      <template v-else>
        <header class="handout-window__heading">
          <div>
            <h2>{{ entity.title }}</h2>
            <p v-if="locationLabel">{{ locationLabel }}</p>
          </div>
          <div v-if="entity.tags?.length" class="handout-window__tag-list">
            <span v-for="tag in entity.tags" :key="tag">{{ tag }}</span>
          </div>
        </header>
        <HandoutDocument
          :content="entity.content"
          :assets="entity.assets || []"
        />
        <section
          v-if="scope === 'campaign' && entity.links?.length"
          class="handout-window__relations"
        >
          <h3>{{ $t("vtt.table.handouts.relations") }}</h3>
          <ul>
            <li
              v-for="link in entity.links"
              :key="`${link.placement}:${link.targetType}:${link.targetId}`"
            >
              {{ link.placement === "mention" ? "@" : "↗" }}
              {{ link.label }}
            </li>
          </ul>
        </section>
      </template>
    </template>
  </section>
</template>

<script>
import HandoutDocument from "./HandoutDocument.vue";
import HandoutEditor from "./HandoutEditor.vue";
import { handoutApiClient } from "@/lib/handouts/handoutApiClient";

const emptyDocument = () => ({
  type: "doc",
  content: [{ type: "paragraph" }],
});
const copy = (value) => JSON.parse(JSON.stringify(value));

export default {
  name: "HandoutWindow",
  components: { HandoutDocument, HandoutEditor },
  props: {
    campaignId: { type: [Number, String], required: true },
    campaignData: { type: Object, default: () => ({}) },
    scope: {
      type: String,
      required: true,
      validator: (value) => ["library", "campaign"].includes(value),
    },
    handoutId: { type: [Number, String], default: null },
    startEditing: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    scenes: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
  },
  emits: ["changed", "close", "open-handout", "window-update"],
  data: () => ({
    localHandoutId: null,
    entity: null,
    draft: null,
    loading: false,
    saving: false,
    editing: false,
    error: "",
    errorTimer: null,
    libraryMeta: { folders: [], tags: [] },
    newTag: "",
    relatedTarget: "",
    shareOpen: false,
    shareMode: "private",
    shareRecipients: [],
  }),
  computed: {
    canEdit() {
      if (!this.entity || this.entity.deletedAt) return false;
      return this.scope === "library"
        ? this.canManage
        : this.entity.canEdit === true;
    },
    canPresent() {
      return (
        this.scope === "campaign" &&
        (this.entity?.canPresent === true || this.canManage)
      );
    },
    canRestore() {
      if (!this.entity?.id || !this.entity.deletedAt) return false;
      return this.scope === "library"
        ? this.canManage
        : this.entity.canEdit === true;
    },
    activeMembers() {
      return this.members.filter((member) => member.isActive !== false);
    },
    mentionTargets() {
      return [
        ...this.scenes.map((scene) => ({
          type: "scene",
          id: scene.id,
          label: scene.name || `#${scene.id}`,
        })),
        ...this.characters.map((character) => ({
          type: "character",
          id: character.id,
          label: character.name || `#${character.id}`,
        })),
        ...(Array.isArray(this.campaignData.items)
          ? this.campaignData.items
          : []
        ).map((item) => ({
          type: "item",
          id: item.id,
          label: item.name || `#${item.id}`,
        })),
      ];
    },
    draftRelatedLinks() {
      return (this.draft?.links || []).filter(
        (link) => link.placement === "related",
      );
    },
    locationLabel() {
      if (this.scope === "campaign") return this.entity?.folderPath || "";
      const folder = this.libraryMeta.folders.find(
        (item) => Number(item.id) === Number(this.entity?.folderId),
      );
      return folder?.name || "";
    },
  },
  watch: {
    handoutId(value) {
      if (Number(value || 0) !== Number(this.localHandoutId || 0)) this.load();
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    window.clearTimeout(this.errorTimer);
  },
  methods: {
    memberId(member) {
      return Number(member.userId || member.id);
    },
    async load() {
      this.loading = true;
      this.error = "";
      this.localHandoutId = this.handoutId ? Number(this.handoutId) : null;
      try {
        if (this.scope === "library") {
          const metaPromise = handoutApiClient.listLibrary();
          if (!this.localHandoutId) {
            const meta = await metaPromise;
            this.setLibraryMeta(meta);
            this.entity = {
              id: null,
              title: "",
              folderId: null,
              tags: [],
              tagIds: [],
              content: emptyDocument(),
              assets: [],
              revision: null,
              deletedAt: null,
            };
            this.beginEdit();
            return;
          }
          const [{ entry }, meta] = await Promise.all([
            handoutApiClient.getLibraryEntry(this.localHandoutId),
            metaPromise,
          ]);
          this.setLibraryMeta(meta);
          this.entity = this.normalizeLibraryEntry(entry);
        } else {
          const { handout } = await handoutApiClient.getCampaignHandout(
            this.campaignId,
            this.localHandoutId,
          );
          this.entity = this.normalizeCampaignHandout(handout);
          this.shareMode = handout.audienceMode || "private";
          this.shareRecipients = handout.recipients || [];
          await this.markRead();
        }
        this.editing = this.startEditing && this.canEdit;
        if (this.editing) this.beginEdit();
        this.emitWindowUpdate();
      } catch (error) {
        this.showError(error);
      } finally {
        this.loading = false;
      }
    },
    setLibraryMeta(result) {
      this.libraryMeta = {
        folders: result.folders || [],
        tags: result.tags || [],
      };
    },
    normalizeLibraryEntry(entry) {
      const tags = entry.tags || [];
      return {
        ...entry,
        tags,
        tagIds: this.libraryMeta.tags
          .filter((tag) => tags.includes(tag.name))
          .map((tag) => tag.id),
        content: entry.content || emptyDocument(),
        assets: entry.assets || [],
      };
    },
    normalizeCampaignHandout(handout) {
      return {
        ...handout,
        tags: handout.tags || [],
        content: handout.content || emptyDocument(),
        assets: handout.assets || [],
        links: handout.links || [],
      };
    },
    beginEdit() {
      if (!this.entity || (!this.canEdit && this.entity.id)) return;
      this.draft = copy(this.entity);
      this.editing = true;
      this.shareOpen = false;
    },
    cancelEdit() {
      if (!this.entity?.id) {
        this.$emit("close");
        return;
      }
      this.draft = null;
      this.editing = false;
      this.newTag = "";
      this.relatedTarget = "";
    },
    toggleLibraryTag(id) {
      const ids = new Set(this.draft.tagIds || []);
      ids.has(id) ? ids.delete(id) : ids.add(id);
      this.draft.tagIds = [...ids];
    },
    addCampaignTag() {
      const tag = this.newTag.trim();
      if (tag && !this.draft.tags.includes(tag)) {
        this.draft.tags = [...this.draft.tags, tag];
      }
      this.newTag = "";
    },
    removeCampaignTag(tag) {
      this.draft.tags = this.draft.tags.filter((item) => item !== tag);
    },
    addRelatedLink() {
      const [targetType, targetId] = this.relatedTarget.split(":");
      const target = this.mentionTargets.find(
        (item) => item.type === targetType && String(item.id) === targetId,
      );
      if (!target) return;
      if (
        !this.draftRelatedLinks.some(
          (link) =>
            link.targetType === target.type &&
            Number(link.targetId) === Number(target.id),
        )
      ) {
        this.draft.links = [
          ...(this.draft.links || []),
          {
            targetType: target.type,
            targetId: target.id,
            label: target.label,
            placement: "related",
          },
        ];
      }
      this.relatedTarget = "";
    },
    removeRelatedLink(link) {
      this.draft.links = (this.draft.links || []).filter(
        (item) =>
          !(
            item.placement === "related" &&
            item.targetType === link.targetType &&
            Number(item.targetId) === Number(link.targetId)
          ),
      );
    },
    async save() {
      if (!this.draft?.title) {
        this.showError(this.$t("vtt.table.handouts.titleRequired"));
        return;
      }
      this.saving = true;
      try {
        if (this.scope === "library") {
          const payload = {
            title: this.draft.title,
            content: this.draft.content,
            folderId: this.draft.folderId,
            tagIds: this.draft.tagIds || [],
          };
          const result = this.localHandoutId
            ? await handoutApiClient.updateLibraryEntry(this.localHandoutId, {
                ...payload,
                revision: this.entity.revision,
              })
            : await handoutApiClient.createLibraryEntry(payload);
          this.localHandoutId = Number(result.entry.id);
          this.entity = this.normalizeLibraryEntry(result.entry);
        } else {
          const { handout } = await handoutApiClient.updateCampaignHandout(
            this.campaignId,
            this.localHandoutId,
            {
              title: this.draft.title,
              content: this.draft.content,
              tags: this.draft.tags || [],
              relatedLinks: this.draftRelatedLinks.map(
                ({ targetType, targetId, label }) => ({
                  targetType,
                  targetId,
                  label,
                }),
              ),
              revision: this.entity.revision,
            },
          );
          this.entity = this.normalizeCampaignHandout(handout);
        }
        this.editing = false;
        this.draft = null;
        this.emitWindowUpdate();
        this.emitChanged();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async publish() {
      this.saving = true;
      try {
        const { handout } = await handoutApiClient.publish(
          this.campaignId,
          this.localHandoutId,
        );
        this.$emit("open-handout", {
          scope: "campaign",
          handoutId: handout.id,
          title: handout.title,
        });
        this.emitChanged();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async saveAudience() {
      this.saving = true;
      try {
        const result = await handoutApiClient.shareCampaignHandout(
          this.campaignId,
          this.localHandoutId,
          {
            revision: this.entity.revision,
            audienceMode: this.shareMode,
            recipientIds:
              this.shareMode === "selected_active_members"
                ? this.shareRecipients
                : [],
          },
        );
        this.entity = this.normalizeCampaignHandout({
          ...this.entity,
          ...result.handout,
          content: result.handout.content || this.entity.content,
        });
        this.shareOpen = false;
        if (result.delivery?.batchId) this.sendAvailability(result.delivery);
        this.emitWindowUpdate();
        this.emitChanged();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    sendAvailability(delivery) {
      const requestId =
        typeof window.crypto?.randomUUID === "function"
          ? window.crypto.randomUUID()
          : `handout-${Date.now()}-${Math.random().toString(36).slice(2)}`;
      this.$store
        .dispatch("realtime/sendHandoutAvailability", {
          requestId,
          batchId: delivery.batchId,
        })
        .catch(() => {});
    },
    async trash() {
      if (!window.confirm(this.$t("vtt.table.handouts.confirmTrash"))) return;
      this.saving = true;
      try {
        if (this.scope === "library") {
          await handoutApiClient.deleteLibraryEntry(
            this.localHandoutId,
            this.entity.revision,
          );
        } else {
          await handoutApiClient.deleteCampaignHandout(
            this.campaignId,
            this.localHandoutId,
            this.entity.revision,
          );
        }
        this.emitChanged();
        this.$emit("close");
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async restore() {
      this.saving = true;
      try {
        if (this.scope === "library") {
          const { entry } = await handoutApiClient.restoreLibraryEntry(
            this.localHandoutId,
          );
          this.entity = this.normalizeLibraryEntry(entry);
        } else {
          const { handout } = await handoutApiClient.restoreCampaignHandout(
            this.campaignId,
            this.localHandoutId,
          );
          this.entity = this.normalizeCampaignHandout(handout);
        }
        this.emitWindowUpdate();
        this.emitChanged();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async uploadAsset({ file, resolve, reject }) {
      try {
        const result = await handoutApiClient.uploadAsset(file);
        resolve(result.asset);
      } catch (error) {
        reject(error);
      }
    },
    async markRead() {
      try {
        const result = await handoutApiClient.listNotifications(
          this.campaignId,
        );
        const notification = (result.items || []).find(
          (item) =>
            Number(item.handoutId) === Number(this.localHandoutId) &&
            !item.readAt,
        );
        if (notification) {
          await handoutApiClient.markNotificationRead(
            this.campaignId,
            notification.id,
          );
          this.emitChanged();
        }
      } catch (_error) {
        // Reading the document must remain possible if inbox refresh fails.
      }
    },
    emitWindowUpdate() {
      this.$emit("window-update", {
        handoutId: this.localHandoutId,
        title: this.entity?.title || this.$t("vtt.table.handouts.newHandout"),
      });
    },
    emitChanged() {
      const detail = { campaignId: Number(this.campaignId), scope: this.scope };
      window.dispatchEvent(
        new CustomEvent("blatyrpg:handout-refresh", { detail }),
      );
      this.$emit("changed", detail);
    },
    showError(error) {
      const code = error?.code || error?.payload?.code;
      this.error =
        code === "revision_conflict"
          ? this.$t("vtt.table.handouts.revisionConflict")
          : typeof error === "string"
            ? error
            : error?.payload?.message ||
              error?.message ||
              this.$t("vtt.table.handouts.failed");
      window.clearTimeout(this.errorTimer);
      this.errorTimer = window.setTimeout(() => {
        this.error = "";
      }, 5000);
    },
  },
};
</script>

<style src="./handout-workspace.css"></style>
<style src="./handout-window.css"></style>
