<template>
  <section
    class="handout-workspace"
    :class="{
      'handout-workspace--compact': compact,
      'handout-workspace--navigator': openInWindows,
    }"
    :aria-busy="loading"
  >
    <header class="handout-workspace__tabs">
      <button
        v-if="canManage"
        type="button"
        :class="{ active: tab === 'library' }"
        @click="activateTab('library')"
      >
        {{ $t("vtt.table.handouts.myLibrary") }}
      </button>
      <button
        type="button"
        :class="{ active: tab === 'campaign' }"
        @click="activateTab('campaign')"
      >
        {{ $t("vtt.table.handouts.campaignMaterials") }}
        <b v-if="unreadCount">{{ unreadCount > 99 ? "99+" : unreadCount }}</b>
      </button>
      <button
        v-if="canManage"
        type="button"
        class="handout-workspace__trash-tab"
        :class="{ active: tab === 'trash' }"
        :aria-label="$t('vtt.table.handouts.trashTab')"
        :title="$t('vtt.table.handouts.trashTab')"
        @click="activateTab('trash')"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M5 7h14m-9-3h4l1 3H9l1-3Zm-3 3 1 13h8l1-13M10 11v5m4-5v5" />
        </svg>
      </button>
      <span v-if="error" class="handout-workspace__error">{{ error }}</span>
    </header>

    <div
      v-if="tab === 'library' && canManage"
      class="handout-workspace__body handout-workspace__body--library"
    >
      <aside
        v-show="openInWindows || !compact || !libraryEntry"
        class="handout-workspace__sidebar"
      >
        <div class="handout-workspace__actions">
          <button
            type="button"
            class="handout-workspace__primary-action"
            @click="newLibraryEntry"
          >
            <span aria-hidden="true">+</span>
            {{ $t("vtt.table.handouts.new") }}
          </button>
          <button
            type="button"
            class="handout-workspace__primary-action"
            @click="createFolder"
          >
            <span aria-hidden="true">+</span>
            {{ $t("vtt.table.handouts.newFolder") }}
          </button>
          <button
            type="button"
            class="handout-workspace__primary-action handout-workspace__primary-action--tags"
            @click="createTag"
          >
            <span aria-hidden="true">+</span>
            {{ $t("vtt.table.handouts.tags") }}
          </button>
        </div>
        <input
          v-model.trim="libraryQuery"
          type="search"
          :placeholder="$t('vtt.table.handouts.search')"
          @input="debouncedLibraryLoad"
        />
        <h3>{{ $t("vtt.table.handouts.folders") }}</h3>
        <button
          type="button"
          class="handout-workspace__filter"
          :class="{ active: !libraryFolderId }"
          @click="filterFolder(null)"
        >
          {{ $t("vtt.table.handouts.all") }}
        </button>
        <button
          v-for="folder in library.folders"
          :key="folder.id"
          type="button"
          class="handout-workspace__filter"
          :class="{ active: libraryFolderId === folder.id }"
          @click="filterFolder(folder.id)"
        >
          <span :style="{ paddingLeft: `${folderDepth(folder) * 12}px` }">{{
            folder.name
          }}</span>
        </button>
        <div v-if="library.tags.length" class="handout-workspace__tag-list">
          <button
            v-for="tag in library.tags"
            :key="tag.id"
            type="button"
            class="handout-workspace__tag-filter"
            :class="{ active: libraryTagId === tag.id }"
            @click="filterTag(tag.id)"
          >
            {{ tag.name }}
          </button>
          <button
            v-if="libraryTagId"
            type="button"
            class="handout-workspace__tag-filter handout-workspace__tag-filter--clear"
            @click="filterTag(null)"
          >
            × {{ $t("vtt.table.handouts.clear") }}
          </button>
        </div>
        <div class="handout-workspace__list">
          <button
            v-for="item in library.items"
            :key="item.id"
            type="button"
            class="handout-workspace__item"
            :class="{ active: libraryEntry?.id === item.id }"
            @click="selectLibraryEntry(item.id)"
          >
            <span>{{ item.title }}</span
            ><small>{{ item.tags.join(", ") }}</small>
          </button>
          <p v-if="!library.items.length" class="handout-workspace__empty">
            {{ $t("vtt.table.handouts.emptyLibrary") }}
          </p>
        </div>
      </aside>

      <main
        v-if="!openInWindows"
        v-show="!compact || Boolean(libraryEntry)"
        class="handout-workspace__detail"
      >
        <template v-if="libraryEntry">
          <div class="handout-workspace__detail-head">
            <button
              v-if="compact"
              type="button"
              class="handout-workspace__back"
              @click="closeLibraryDetail"
            >
              ← {{ $t("vtt.table.handouts.backToList") }}
            </button>
            <input
              v-model.trim="libraryEntry.title"
              :aria-label="$t('vtt.table.handouts.title')"
              :disabled="libraryTrash"
            />
            <select v-model="libraryEntry.folderId" :disabled="libraryTrash">
              <option :value="null">
                {{ $t("vtt.table.handouts.noFolder") }}
              </option>
              <option
                v-for="folder in library.folders"
                :key="folder.id"
                :value="folder.id"
              >
                {{ folder.name }}
              </option>
            </select>
            <button
              v-if="!libraryTrash"
              type="button"
              :disabled="saving"
              @click="saveLibraryEntry"
            >
              {{ $t("vtt.table.handouts.save") }}
            </button>
            <button
              v-if="!libraryTrash"
              type="button"
              :disabled="saving || !libraryEntry.id"
              @click="publishLibraryEntry"
            >
              {{ $t("vtt.table.handouts.publish") }}
            </button>
            <button
              v-if="libraryEntry.id && !libraryTrash"
              type="button"
              :disabled="saving"
              @click="deleteLibraryEntry"
            >
              {{ $t("vtt.table.handouts.trash") }}
            </button>
            <button
              v-if="libraryEntry.id && libraryTrash"
              type="button"
              :disabled="saving"
              @click="restoreLibraryEntry"
            >
              {{ $t("vtt.table.handouts.restore") }}
            </button>
          </div>
          <div class="handout-workspace__tags">
            <label v-for="tag in library.tags" :key="tag.id"
              ><input
                type="checkbox"
                :checked="libraryEntry.tagIds.includes(tag.id)"
                :disabled="libraryTrash"
                @change="toggleLibraryTag(tag.id)"
              />{{ tag.name }}</label
            >
          </div>
          <HandoutEditor
            v-if="!libraryTrash"
            v-model="libraryEntry.content"
            :allow-mentions="false"
            @upload-image="uploadImage"
            @error="showError"
          />
          <HandoutDocument
            v-else
            :content="libraryEntry.content"
            :assets="libraryEntry.assets || []"
          />
          <p class="handout-workspace__hint">
            {{ $t("vtt.table.handouts.libraryHint") }}
          </p>
        </template>
        <p v-else class="handout-workspace__empty">
          {{ $t("vtt.table.handouts.selectLibraryItem") }}
        </p>
      </main>
    </div>

    <div
      v-else-if="tab === 'campaign'"
      class="handout-workspace__body handout-workspace__body--campaign"
    >
      <aside
        v-show="openInWindows || !compact || !campaignHandout"
        class="handout-workspace__sidebar"
      >
        <input
          v-model.trim="campaignQuery"
          type="search"
          :placeholder="$t('vtt.table.handouts.search')"
          @input="debouncedCampaignLoad"
        />
        <div class="handout-workspace__campaign-filter">
          <button
            type="button"
            :class="{ active: !campaignTag }"
            @click="filterCampaignTag('')"
          >
            {{ $t("vtt.table.handouts.all") }}
          </button>
          <button
            v-for="tag in campaignTags"
            :key="tag"
            type="button"
            :class="{ active: campaignTag === tag }"
            @click="filterCampaignTag(tag)"
          >
            {{ tag }}
          </button>
        </div>
        <h3 v-if="!campaignTrash">{{ $t("vtt.table.handouts.inbox") }}</h3>
        <template v-if="!campaignTrash">
          <button
            v-for="notification in notifications"
            :key="notification.id"
            type="button"
            class="handout-workspace__notification"
            :class="{ unread: !notification.readAt }"
            @click="selectCampaignHandout(notification.handoutId)"
          >
            <span>{{ notification.title }}</span>
            <small>{{
              notification.readAt
                ? $t("vtt.table.handouts.read")
                : $t("vtt.table.handouts.unread")
            }}</small>
          </button>
        </template>
        <button
          v-for="item in campaign.items"
          :key="item.id"
          type="button"
          class="handout-workspace__item"
          :class="{ active: campaignHandout?.id === item.id }"
          @click="selectCampaignHandout(item.id)"
        >
          <span>{{ item.title }}</span
          ><small>{{ item.tags.join(", ") }}</small>
        </button>
        <p v-if="!campaign.items.length" class="handout-workspace__empty">
          {{ $t("vtt.table.handouts.emptyCampaign") }}
        </p>
      </aside>

      <main
        v-if="!openInWindows"
        v-show="!compact || Boolean(campaignHandout)"
        class="handout-workspace__detail"
      >
        <template v-if="campaignHandout">
          <div class="handout-workspace__detail-head">
            <button
              v-if="compact"
              type="button"
              class="handout-workspace__back"
              @click="closeCampaignDetail"
            >
              ← {{ $t("vtt.table.handouts.backToList") }}
            </button>
            <template v-if="campaignHandout.canEdit && !campaignTrash">
              <input
                v-model.trim="campaignHandout.title"
                :aria-label="$t('vtt.table.handouts.title')"
              />
              <button
                type="button"
                :disabled="saving"
                @click="saveCampaignHandout"
              >
                {{ $t("vtt.table.handouts.save") }}
              </button>
              <button
                type="button"
                :disabled="saving"
                @click="deleteCampaignHandout"
              >
                {{ $t("vtt.table.handouts.trash") }}
              </button>
            </template>
            <template v-else
              ><h2>{{ campaignHandout.title }}</h2></template
            >
            <button
              v-if="campaignTrash && campaignHandout.canEdit"
              type="button"
              :disabled="saving"
              @click="restoreCampaignHandout"
            >
              {{ $t("vtt.table.handouts.restore") }}
            </button>
            <button
              v-if="canManage && !campaignTrash"
              type="button"
              @click="shareOpen = !shareOpen"
            >
              {{ $t("vtt.table.handouts.share") }}
            </button>
          </div>

          <section
            v-if="shareOpen && canManage"
            class="handout-workspace__share"
          >
            <label
              >{{ $t("vtt.table.handouts.audience") }}
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
              class="handout-workspace__recipients"
            >
              <label v-for="member in activeMembers" :key="memberId(member)"
                ><input
                  type="checkbox"
                  :value="memberId(member)"
                  v-model="shareRecipients"
                />{{ member.username || member.email || member.name }}</label
              >
            </div>
            <button
              type="button"
              :disabled="saving"
              @click="shareCampaignHandout"
            >
              {{ $t("vtt.table.handouts.saveAudience") }}
            </button>
          </section>

          <template v-if="campaignHandout.canEdit && !campaignTrash">
            <div class="handout-workspace__tags">
              <label v-for="tag in campaignTags" :key="tag"
                ><input
                  type="checkbox"
                  :checked="campaignHandout.tags.includes(tag)"
                  @change="toggleCampaignTag(tag)"
                />{{ tag }}</label
              >
              <input
                v-model.trim="newCampaignTag"
                :placeholder="$t('vtt.table.handouts.newTag')"
                @keyup.enter="addCampaignTag"
              />
            </div>
            <HandoutEditor
              v-model="campaignHandout.content"
              :allow-mentions="true"
              :mention-targets="mentionTargets"
              @upload-image="uploadImage"
              @error="showError"
            />
          </template>
          <HandoutDocument
            v-else
            :content="campaignHandout.content"
            :assets="campaignHandout.assets"
          />

          <section class="handout-workspace__relations">
            <h3>{{ $t("vtt.table.handouts.relations") }}</h3>
            <ul>
              <li
                v-for="link in campaignHandout.links || []"
                :key="`${link.placement}:${link.targetType}:${link.targetId}`"
              >
                {{ link.placement === "mention" ? "@" : "↗" }} {{ link.label }}
                <button
                  v-if="
                    campaignHandout.canEdit &&
                    !campaignTrash &&
                    link.placement === 'related'
                  "
                  type="button"
                  :aria-label="$t('vtt.table.handouts.removeRelation')"
                  @click="removeRelatedLink(link)"
                >
                  ×
                </button>
              </li>
            </ul>
            <template v-if="campaignHandout.canEdit && !campaignTrash">
              <select v-model="relatedTarget">
                <option value="">
                  {{ $t("vtt.table.handouts.addRelation") }}
                </option>
                <option
                  v-for="target in mentionTargets"
                  :key="`rel:${target.type}:${target.id}`"
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
            </template>
          </section>
        </template>
        <p v-else class="handout-workspace__empty">
          {{ $t("vtt.table.handouts.selectCampaignItem") }}
        </p>
      </main>
    </div>

    <div
      v-else-if="tab === 'trash' && canManage"
      class="handout-workspace__body handout-workspace__body--trash"
    >
      <aside class="handout-workspace__sidebar">
        <button
          v-for="item in trashItems"
          :key="`${item.scope}:${item.id}`"
          type="button"
          class="handout-workspace__item handout-workspace__trash-item"
          @click="openTrashItem(item)"
        >
          <span>{{ item.title }}</span>
          <small>{{
            $t(
              item.scope === "library"
                ? "vtt.table.handouts.myLibrary"
                : "vtt.table.handouts.campaignMaterials",
            )
          }}</small>
        </button>
        <p v-if="!trashItems.length" class="handout-workspace__empty">
          {{ $t("vtt.table.handouts.emptyTrash") }}
        </p>
      </aside>
    </div>
  </section>
</template>

<script>
import HandoutDocument from "./HandoutDocument.vue";
import HandoutEditor from "./HandoutEditor.vue";
import { handoutApiClient } from "@/lib/handouts/handoutApiClient";

const emptyDocument = () => ({ type: "doc", content: [{ type: "paragraph" }] });
const initialLibraryEntry = () => ({
  id: null,
  title: "",
  content: emptyDocument(),
  folderId: null,
  revision: null,
  tagIds: [],
});

export default {
  name: "HandoutWorkspace",
  components: { HandoutDocument, HandoutEditor },
  props: {
    campaignId: { type: [Number, String], required: true },
    campaignData: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false },
    openInWindows: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    scenes: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
  },
  emits: ["unread-count", "open-handout"],
  data: () => ({
    tab: "campaign",
    loading: false,
    saving: false,
    error: "",
    library: { items: [], folders: [], tags: [] },
    campaign: { items: [] },
    trash: { libraryItems: [], campaignItems: [] },
    libraryEntry: null,
    campaignHandout: null,
    libraryQuery: "",
    libraryFolderId: null,
    libraryTagId: null,
    libraryTrash: false,
    campaignQuery: "",
    campaignTag: "",
    campaignTrash: false,
    unreadCount: 0,
    notifications: [],
    shareOpen: false,
    shareMode: "private",
    shareRecipients: [],
    newCampaignTag: "",
    relatedTarget: "",
    libraryTimer: null,
    campaignTimer: null,
  }),
  computed: {
    activeMembers() {
      return this.members.filter((member) => member.isActive !== false);
    },
    campaignTags() {
      return [
        ...new Set(
          this.campaign.items
            .flatMap((item) => item.tags || [])
            .concat(this.campaignHandout?.tags || []),
        ),
      ].sort();
    },
    trashItems() {
      return [
        ...this.trash.libraryItems.map((item) => ({
          ...item,
          scope: "library",
        })),
        ...this.trash.campaignItems.map((item) => ({
          ...item,
          scope: "campaign",
        })),
      ].sort((left, right) =>
        String(right.deletedAt || right.updatedAt || "").localeCompare(
          String(left.deletedAt || left.updatedAt || ""),
        ),
      );
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
          label: item.name || "#" + item.id,
        })),
      ];
    },
  },
  watch: {
    campaignId: {
      immediate: true,
      handler() {
        this.resetAndLoad();
      },
    },
    canManage(value) {
      if (value) {
        this.loadLibrary();
      } else if (this.tab !== "campaign") {
        this.activateTab("campaign");
      }
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.libraryTimer);
    window.clearTimeout(this.campaignTimer);
    window.removeEventListener(
      "blatyrpg:handout-available",
      this.handleAvailable,
    );
    window.removeEventListener(
      "blatyrpg:handout-refresh",
      this.handleAvailable,
    );
  },
  mounted() {
    window.addEventListener("blatyrpg:handout-available", this.handleAvailable);
    window.addEventListener("blatyrpg:handout-refresh", this.handleAvailable);
  },
  methods: {
    closeLibraryDetail() {
      this.libraryEntry = null;
    },
    closeCampaignDetail() {
      this.campaignHandout = null;
      this.shareOpen = false;
    },
    async resetAndLoad() {
      this.error = "";
      this.tab = this.canManage ? "library" : "campaign";
      this.libraryEntry = null;
      this.campaignHandout = null;
      this.trash = { libraryItems: [], campaignItems: [] };
      await Promise.all([
        this.loadCampaign(),
        this.loadNotifications(),
        this.canManage ? this.loadLibrary() : Promise.resolve(),
      ]);
    },
    async loadLibrary() {
      if (!this.canManage) return;
      this.loading = true;
      try {
        const result = await handoutApiClient.listLibrary({
          q: this.libraryQuery,
          folderId: this.libraryFolderId,
          tagId: this.libraryTagId,
          trash: this.libraryTrash,
        });
        this.library = {
          items: result.items || [],
          folders: result.folders || [],
          tags: result.tags || [],
        };
        this.error = "";
      } catch (error) {
        this.showError(error);
      } finally {
        this.loading = false;
      }
    },
    async loadCampaign() {
      this.loading = true;
      try {
        const result = await handoutApiClient.listCampaign(this.campaignId, {
          q: this.campaignQuery,
          tag: this.campaignTag,
          trash: this.campaignTrash,
        });
        this.campaign = { items: result.items || [] };
        this.error = "";
      } catch (error) {
        this.showError(error);
      } finally {
        this.loading = false;
      }
    },
    async loadNotifications() {
      try {
        const result = await handoutApiClient.listNotifications(
          this.campaignId,
        );
        this.notifications = result.items || [];
        this.unreadCount = Number(result.unreadCount || 0);
        this.$emit("unread-count", this.unreadCount);
      } catch (_error) {
        /* The panel still works if notifications are unavailable. */
      }
    },
    async loadTrash() {
      if (!this.canManage) return;
      this.loading = true;
      try {
        const [library, campaign] = await Promise.all([
          handoutApiClient.listLibrary({ trash: true }),
          handoutApiClient.listCampaign(this.campaignId, { trash: true }),
        ]);
        this.trash = {
          libraryItems: library.items || [],
          campaignItems: campaign.items || [],
        };
        this.error = "";
      } catch (error) {
        this.showError(error);
      } finally {
        this.loading = false;
      }
    },
    activateTab(tab) {
      if (tab === "trash" && !this.canManage) return;
      this.tab = tab;
      this.libraryEntry = null;
      this.campaignHandout = null;
      this.shareOpen = false;
      if (tab === "library") this.loadLibrary();
      if (tab === "campaign") this.loadCampaign();
      if (tab === "trash") this.loadTrash();
    },
    debouncedLibraryLoad() {
      window.clearTimeout(this.libraryTimer);
      this.libraryTimer = window.setTimeout(() => this.loadLibrary(), 220);
    },
    debouncedCampaignLoad() {
      window.clearTimeout(this.campaignTimer);
      this.campaignTimer = window.setTimeout(() => this.loadCampaign(), 220);
    },
    filterFolder(id) {
      this.libraryFolderId = id;
      this.loadLibrary();
    },
    filterTag(id) {
      this.libraryTagId = id;
      this.loadLibrary();
    },
    filterCampaignTag(tag) {
      this.campaignTag = tag;
      this.loadCampaign();
    },
    toggleLibraryTrash() {
      this.libraryTrash = !this.libraryTrash;
      this.libraryEntry = null;
      this.loadLibrary();
    },
    toggleCampaignTrash() {
      this.campaignTrash = !this.campaignTrash;
      this.campaignHandout = null;
      this.shareOpen = false;
      this.loadCampaign();
    },
    folderDepth(folder) {
      let depth = 0;
      let parent = folder.parentId;
      const visited = new Set([folder.id]);
      while (parent && !visited.has(parent)) {
        visited.add(parent);
        const item = this.library.folders.find(
          (candidate) => candidate.id === parent,
        );
        parent = item?.parentId;
        depth += 1;
      }
      return depth;
    },
    memberId(member) {
      return Number(member.userId || member.id);
    },
    async selectLibraryEntry(id) {
      if (this.openInWindows) {
        const item = this.library.items.find(
          (candidate) => Number(candidate.id) === Number(id),
        );
        this.$emit("open-handout", {
          scope: "library",
          handoutId: Number(id),
          title: item?.title || "",
        });
        return;
      }
      try {
        const { entry } = await handoutApiClient.getLibraryEntry(id);
        const tagIds = this.library.tags
          .filter((tag) => (entry.tags || []).includes(tag.name))
          .map((tag) => tag.id);
        this.libraryEntry = {
          ...entry,
          content: entry.content || emptyDocument(),
          tagIds,
        };
      } catch (error) {
        this.showError(error);
      }
    },
    openTrashItem(item) {
      this.$emit("open-handout", {
        scope: item.scope,
        handoutId: Number(item.id),
        title: item.title || "",
      });
    },
    newLibraryEntry() {
      if (this.openInWindows) {
        this.$emit("open-handout", {
          scope: "library",
          handoutId: null,
          title: this.$t("vtt.table.handouts.newHandout"),
          startEditing: true,
        });
        return;
      }
      this.libraryEntry = initialLibraryEntry();
    },
    toggleLibraryTag(id) {
      const ids = new Set(this.libraryEntry.tagIds || []);
      ids.has(id) ? ids.delete(id) : ids.add(id);
      this.libraryEntry.tagIds = [...ids];
    },
    async saveLibraryEntry() {
      if (!this.libraryEntry?.title)
        return this.showError(this.$t("vtt.table.handouts.titleRequired"));
      this.saving = true;
      try {
        const draft = {
          title: this.libraryEntry.title,
          content: this.libraryEntry.content,
          folderId: this.libraryEntry.folderId,
          tagIds: this.libraryEntry.tagIds,
        };
        const result = this.libraryEntry.id
          ? await handoutApiClient.updateLibraryEntry(this.libraryEntry.id, {
              ...draft,
              revision: this.libraryEntry.revision,
            })
          : await handoutApiClient.createLibraryEntry(draft);
        const entry = result.entry;
        this.libraryEntry = {
          ...entry,
          content: entry.content || emptyDocument(),
          tagIds: this.library.tags
            .filter((tag) => (entry.tags || []).includes(tag.name))
            .map((tag) => tag.id),
        };
        await this.loadLibrary();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async deleteLibraryEntry() {
      if (
        !this.libraryEntry?.id ||
        !window.confirm(this.$t("vtt.table.handouts.confirmTrash"))
      )
        return;
      this.saving = true;
      try {
        await handoutApiClient.deleteLibraryEntry(
          this.libraryEntry.id,
          this.libraryEntry.revision,
        );
        this.libraryEntry = null;
        await this.loadLibrary();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async restoreLibraryEntry() {
      if (!this.libraryEntry?.id) return;
      this.saving = true;
      try {
        const { entry } = await handoutApiClient.restoreLibraryEntry(
          this.libraryEntry.id,
        );
        this.libraryTrash = false;
        this.libraryEntry = {
          ...entry,
          content: entry.content || emptyDocument(),
          tagIds: this.library.tags
            .filter((tag) => (entry.tags || []).includes(tag.name))
            .map((tag) => tag.id),
        };
        await this.loadLibrary();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async createFolder() {
      const name = String(
        window.prompt(this.$t("vtt.table.handouts.folderPrompt")) || "",
      ).trim();
      if (!name) return;
      try {
        await handoutApiClient.createFolder({
          name,
          parentId: this.libraryFolderId,
        });
        await this.loadLibrary();
      } catch (error) {
        this.showError(error);
      }
    },
    async createTag() {
      const name = String(
        window.prompt(this.$t("vtt.table.handouts.tagPrompt")) || "",
      ).trim();
      if (!name) return;
      try {
        await handoutApiClient.createTag({ name });
        await this.loadLibrary();
      } catch (error) {
        this.showError(error);
      }
    },
    async publishLibraryEntry() {
      if (!this.libraryEntry?.id) return;
      this.saving = true;
      try {
        const { handout } = await handoutApiClient.publish(
          this.campaignId,
          this.libraryEntry.id,
        );
        await this.loadCampaign();
        this.tab = "campaign";
        this.campaignHandout = handout;
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async selectCampaignHandout(id) {
      if (this.openInWindows) {
        const item = this.campaign.items.find(
          (candidate) => Number(candidate.id) === Number(id),
        );
        const notification = this.notifications.find(
          (candidate) => Number(candidate.handoutId) === Number(id),
        );
        this.$emit("open-handout", {
          scope: "campaign",
          handoutId: Number(id),
          title: item?.title || notification?.title || "",
        });
        return;
      }
      try {
        const { handout } = await handoutApiClient.getCampaignHandout(
          this.campaignId,
          id,
        );
        this.campaignHandout = {
          ...handout,
          content: handout.content || emptyDocument(),
          assets: handout.assets || [],
          links: handout.links || [],
        };
        this.shareMode = handout.audienceMode || "private";
        this.shareRecipients = handout.recipients || [];
        this.shareOpen = false;
        const notification = await handoutApiClient.listNotifications(
          this.campaignId,
        );
        const matching = (notification.items || []).find(
          (item) => Number(item.handoutId) === Number(id) && !item.readAt,
        );
        if (matching)
          await handoutApiClient.markNotificationRead(
            this.campaignId,
            matching.id,
          );
        await this.loadNotifications();
      } catch (error) {
        this.showError(error);
      }
    },
    toggleCampaignTag(tag) {
      const tags = new Set(this.campaignHandout.tags || []);
      tags.has(tag) ? tags.delete(tag) : tags.add(tag);
      this.campaignHandout.tags = [...tags];
    },
    addCampaignTag() {
      const tag = this.newCampaignTag.trim();
      if (tag && !this.campaignHandout.tags.includes(tag))
        this.campaignHandout.tags = [...this.campaignHandout.tags, tag];
      this.newCampaignTag = "";
    },
    relatedLinks() {
      return (this.campaignHandout.links || [])
        .filter((link) => link.placement === "related")
        .map(({ targetType, targetId, label }) => ({
          targetType,
          targetId,
          label,
        }));
    },
    addRelatedLink() {
      const [targetType, targetId] = this.relatedTarget.split(":");
      const target = this.mentionTargets.find(
        (item) => item.type === targetType && String(item.id) === targetId,
      );
      if (!target) return;
      if (
        !this.relatedLinks().some(
          (link) =>
            link.targetType === target.type &&
            Number(link.targetId) === Number(target.id),
        )
      ) {
        this.campaignHandout.links = [
          ...this.campaignHandout.links,
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
      this.campaignHandout.links = (this.campaignHandout.links || []).filter(
        (candidate) =>
          !(
            candidate.placement === "related" &&
            candidate.targetType === link.targetType &&
            Number(candidate.targetId) === Number(link.targetId)
          ),
      );
    },
    async saveCampaignHandout() {
      if (!this.campaignHandout?.canEdit) return;
      this.saving = true;
      try {
        const { handout } = await handoutApiClient.updateCampaignHandout(
          this.campaignId,
          this.campaignHandout.id,
          {
            title: this.campaignHandout.title,
            content: this.campaignHandout.content,
            tags: this.campaignHandout.tags,
            relatedLinks: this.relatedLinks(),
            revision: this.campaignHandout.revision,
          },
        );
        this.campaignHandout = {
          ...handout,
          content: handout.content || emptyDocument(),
          assets: handout.assets || [],
          links: handout.links || [],
        };
        await this.loadCampaign();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async shareCampaignHandout() {
      if (!this.campaignHandout) return;
      this.saving = true;
      try {
        const result = await handoutApiClient.shareCampaignHandout(
          this.campaignId,
          this.campaignHandout.id,
          {
            revision: this.campaignHandout.revision,
            audienceMode: this.shareMode,
            recipientIds:
              this.shareMode === "selected_active_members"
                ? this.shareRecipients
                : [],
          },
        );
        const { handout } = result;
        this.campaignHandout = {
          ...this.campaignHandout,
          ...handout,
          content: handout.content || this.campaignHandout.content,
        };
        this.shareOpen = false;
        if (result.delivery?.batchId) {
          const requestId =
            typeof crypto?.randomUUID === "function"
              ? crypto.randomUUID()
              : "handout-" +
                Date.now() +
                "-" +
                Math.random().toString(36).slice(2);
          this.$store
            .dispatch("realtime/sendHandoutAvailability", {
              requestId,
              batchId: result.delivery.batchId,
            })
            .catch(() => {});
        }
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async deleteCampaignHandout() {
      if (
        !this.campaignHandout?.canEdit ||
        !window.confirm(this.$t("vtt.table.handouts.confirmTrash"))
      )
        return;
      this.saving = true;
      try {
        await handoutApiClient.deleteCampaignHandout(
          this.campaignId,
          this.campaignHandout.id,
          this.campaignHandout.revision,
        );
        this.campaignHandout = null;
        await this.loadCampaign();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async restoreCampaignHandout() {
      if (!this.campaignHandout?.canEdit) return;
      this.saving = true;
      try {
        const { handout } = await handoutApiClient.restoreCampaignHandout(
          this.campaignId,
          this.campaignHandout.id,
        );
        this.campaignTrash = false;
        this.campaignHandout = {
          ...handout,
          content: handout.content || emptyDocument(),
          assets: handout.assets || [],
          links: handout.links || [],
        };
        await this.loadCampaign();
      } catch (error) {
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    async uploadImage({ file, resolve, reject }) {
      try {
        const result = await handoutApiClient.uploadAsset(file);
        const asset = result.asset;
        resolve(
          String(asset?.mimeType || "").startsWith("image/")
            ? { ...asset, previewUrl: URL.createObjectURL(file) }
            : asset,
        );
      } catch (error) {
        reject(error);
      }
    },
    handleAvailable(event) {
      if (Number(event?.detail?.campaignId) !== Number(this.campaignId)) return;
      this.loadCampaign();
      this.loadNotifications();
      if (this.canManage) this.loadLibrary();
      if (this.canManage && this.tab === "trash") this.loadTrash();
    },
    showError(error) {
      const code = error?.code || error?.payload?.code;
      if (code === "revision_conflict") {
        this.error = this.$t("vtt.table.handouts.revisionConflict");
      } else {
        this.error =
          typeof error === "string"
            ? error
            : error?.payload?.message ||
              error?.code ||
              error?.message ||
              this.$t("vtt.table.handouts.failed");
      }
      window.setTimeout(() => {
        this.error = "";
      }, 5000);
    },
  },
};
</script>

<style src="./handout-workspace.css"></style>
