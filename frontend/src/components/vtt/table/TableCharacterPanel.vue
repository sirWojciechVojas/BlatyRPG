<template>
  <section
    class="table-character-panel"
    :class="{ 'table-character-panel--compact': compact }"
  >
    <aside class="table-character-panel__browser-shell">
      <div
        class="table-character-panel__browser"
        :class="{ 'has-group-drag': draggedGroupCharacterId }"
      >
        <section
          v-if="compact && chosenCharacter"
          class="table-character-panel__selected-character"
          :aria-label="$t('vtt.table.characters.selectedLabel')"
        >
          <small>{{ $t("vtt.table.characters.selected") }}</small>
          <button
            type="button"
            :title="$t('vtt.table.playerHud.actions.clearSelection')"
            :aria-label="$t('vtt.table.playerHud.actions.clearSelection')"
            @click="clearHudCharacterSelection"
          >
            <AuthenticatedImage
              :src="avatar(chosenCharacter)"
              alt=""
              draggable="false"
            />
            <span>
              <strong>{{ chosenCharacter.name }}</strong>
              <small>{{ details(chosenCharacter) }}</small>
            </span>
            <span
              class="table-character-panel__clear-selection"
              aria-hidden="true"
              >×</span
            >
          </button>
        </section>

        <header class="table-character-panel__search-bar">
          <label class="table-character-panel__search-field">
            <span aria-hidden="true">⌕</span>
            <input
              v-model.trim="query"
              type="search"
              :placeholder="$t('characters.list.title')"
              :aria-label="$t('characters.list.title')"
            />
          </label>
          <nav :aria-label="$t('vtt.table.characters.listActions')">
            <button
              v-if="canCreate && !compact"
              type="button"
              class="table-character-panel__create"
              :disabled="creating"
              :title="$t('characters.actions.new')"
              :aria-label="$t('characters.actions.new')"
              @click="openCreate"
            >
              ＋
            </button>
            <button
              v-if="canManageGroups"
              type="button"
              class="table-character-panel__add-group"
              :title="$t('vtt.table.characters.addGroup')"
              :aria-label="$t('vtt.table.characters.addGroup')"
              @click="addGroup()"
            >
              ▣
            </button>
            <button
              type="button"
              :disabled="loading"
              :title="$t('characters.actions.refresh')"
              :aria-label="$t('characters.actions.refresh')"
              @click="loadCharacters"
            >
              ↻
            </button>
          </nav>
        </header>

        <div class="table-character-panel__groups-shell">
          <div
            ref="characterBrowser"
            class="table-character-panel__groups"
            @scroll.passive="syncCharacterScrollbar"
          >
            <p
              v-if="compact && loadError"
              class="table-character-panel__error"
              role="alert"
            >
              {{ loadError }}
            </p>
            <p v-else-if="loading && !characters.length" role="status">
              {{ $t("characters.loading.list") }}
            </p>
            <p v-else-if="!filteredCharacters.length">
              {{ $t("characters.empty.title") }}
            </p>
            <template v-else>
              <section
                v-for="section in groupSections"
                :key="section.group.id"
                class="table-character-panel__group"
                :style="{ '--group-depth': section.depth }"
              >
                <header
                  class="table-character-panel__group-header"
                  :class="{
                    'is-drop-target': isGroupDropTarget(section.group.id),
                    selected: activeGroupId === section.group.id,
                  }"
                  @click="selectGroup(section.group.id)"
                  @dragover.prevent="previewGroupDrop($event, section.group.id)"
                  @drop.prevent="commitGroupDrop($event)"
                >
                  <span class="table-character-panel__group-title">
                    <button
                      type="button"
                      class="table-character-panel__group-toggle"
                      :aria-expanded="
                        String(!isGroupCollapsed(section.group.id))
                      "
                      :title="
                        $t(
                          isGroupCollapsed(section.group.id)
                            ? 'vtt.table.characters.expandGroup'
                            : 'vtt.table.characters.collapseGroup',
                        )
                      "
                      @click.stop="toggleGroup(section.group.id)"
                    >
                      {{ isGroupCollapsed(section.group.id) ? "▸" : "▾" }}
                    </button>
                    <strong>{{ groupName(section.group) }}</strong>
                  </span>
                  <span
                    class="table-character-panel__group-actions"
                    v-if="canManageGroups"
                  >
                    <button
                      v-if="!section.group.root"
                      type="button"
                      :title="$t('vtt.table.characters.renameGroup')"
                      @click.stop="renameGroup(section.group)"
                    >
                      ✎
                    </button>
                  </span>
                </header>
                <p
                  v-if="
                    !isGroupCollapsed(section.group.id) &&
                    !section.characters.length &&
                    !section.children.length
                  "
                  class="table-character-panel__group-empty"
                >
                  {{ $t("vtt.table.characters.groupEmpty") }}
                </p>
                <template v-if="!isGroupCollapsed(section.group.id)">
                  <div
                    v-for="(character, index) in section.characters"
                    :key="character.id"
                    class="table-character-panel__item"
                    :class="{
                      selected:
                        character.id === selectedId ||
                        Number(character.id) === Number(selectedHudCharacterId),
                      'table-character-panel__item--draggable': canManageGroups,
                      'is-dragging': character.id === draggedCharacterId,
                      'is-group-dragging':
                        Number(character.id) ===
                        Number(draggedGroupCharacterId),
                      'is-drop-before': isDropBefore(
                        section.group.id,
                        character.id,
                      ),
                      'is-drop-after': isDropAfter(
                        section.group.id,
                        character.id,
                      ),
                    }"
                    @dragover.prevent="
                      previewGroupDrop(
                        $event,
                        section.group.id,
                        section.characters,
                        index,
                      )
                    "
                    @drop.prevent="commitGroupDrop($event)"
                  >
                    <span
                      v-if="canManageGroups"
                      class="table-character-panel__character-actions"
                    >
                      <button
                        type="button"
                        class="table-character-panel__drag-handle"
                        draggable="true"
                        :title="$t('vtt.table.characters.dragCharacter')"
                        :aria-label="$t('vtt.table.characters.dragCharacter')"
                        @dragstart.stop="beginGroupDrag($event, character)"
                        @dragend="finishGroupDrag"
                      >
                        ⠿
                      </button>
                    </span>
                    <button
                      type="button"
                      class="table-character-panel__identity"
                      :draggable="canCreateToken"
                      :title="
                        canCreateToken
                          ? $t('vtt.token.dragActor')
                          : character.name
                      "
                      @dragstart="dragCharacter($event, character)"
                      @dragend="finishCharacterDrag"
                      @click="selectCharacterIfExpanded(character.id)"
                    >
                      <AuthenticatedImage
                        :src="avatar(character)"
                        alt=""
                        draggable="false"
                      />
                      <span>
                        <strong>{{ character.name }}</strong>
                        <small>{{ details(character) }}</small>
                      </span>
                    </button>
                    <small class="table-character-panel__character-id"
                      >#{{ character.id }}</small
                    >
                    <button
                      v-if="
                        compact &&
                        canSelectForHud &&
                        canSelectCharacterForHud(character)
                      "
                      type="button"
                      class="table-character-panel__choose"
                      :disabled="
                        Number(character.id) === Number(selectedHudCharacterId)
                      "
                      :aria-pressed="
                        String(
                          Number(character.id) ===
                            Number(selectedHudCharacterId),
                        )
                      "
                      :title="$t('vtt.table.playerHud.actions.choose')"
                      :aria-label="
                        $t('vtt.table.playerHud.actions.chooseCharacter', {
                          name: character.name,
                        })
                      "
                      @click="selectCharacterForHud(character.id)"
                    >
                      {{
                        Number(character.id) === Number(selectedHudCharacterId)
                          ? "✓"
                          : "○"
                      }}
                    </button>
                  </div>
                </template>
              </section>
            </template>
          </div>
          <div
            v-if="characterScrollbar.visible"
            ref="characterScrollbarTrack"
            class="table-character-panel__scrollbar"
            aria-hidden="true"
            @pointerdown.self="jumpCharacterScrollbar"
          >
            <span
              class="table-character-panel__scrollbar-thumb"
              :style="{
                height: `${characterScrollbar.thumbHeight}px`,
                transform: `translateY(${characterScrollbar.thumbTop}px)`,
              }"
              @pointerdown.stop.prevent="startCharacterScrollbarDrag"
            />
          </div>
        </div>

        <button
          v-if="compact"
          type="button"
          class="table-character-panel__promote"
          @click="$emit('open-window')"
        >
          {{ $t("vtt.table.characters.openFull") }}
        </button>
      </div>
    </aside>

    <div v-if="!compact" class="table-character-panel__sheet">
      <p v-if="notice" class="table-character-panel__notice" role="status">
        {{ notice }}
      </p>
      <p v-if="loadError" class="table-character-panel__error" role="alert">
        {{ loadError }}
      </p>
      <TableCharacterAccessPanel
        v-if="canManageAccess && selectedId"
        :character-id="selectedId"
        :members="members"
        @changed="handleAccessChanged"
      />
      <TableCharacterCreateForm
        v-if="showingCreate && canCreate"
        :campaign="campaign"
        :busy="creating"
        :error="createError"
        @cancel="showingCreate = false"
        @create="createCharacter"
      />
      <p v-else-if="loadingSheet" class="table-character-panel__loading">
        {{ $t("characters.loading.sheet") }}
      </p>
      <CharacterSheetEditor
        v-else
        :campaign-id="campaignId"
        :character="selectedCharacter"
        :saving="saving || deleting"
        :error="saveError"
        @save="saveCharacter"
        @delete="deleteCharacter"
      />
    </div>
  </section>
</template>

<script>
import CharacterSheetEditor from "@/components/characters/CharacterSheetEditor.vue";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import { characterApiClient } from "@/lib/character/characterApiClient";
import { characterErrorKey } from "@/lib/character/characterErrorKey";
import {
  resolveCharacterAvatar,
  resolveCharacterTokenSource,
} from "@/lib/trade/characterAvatar";
import { beginActorDrag, endActorDrag } from "@/lib/vtt/actorDragSession";
import TableCharacterCreateForm from "./TableCharacterCreateForm.vue";
import TableCharacterAccessPanel from "./TableCharacterAccessPanel.vue";
import { tableCharacterCreationMethods } from "./tableCharacterCreationMethods";
import {
  appendGroup,
  characterGroupTree,
  createCharacterGroupLayout,
  moveCharacterToGroup,
  placeCharacterBefore,
  renameGroup as renameCharacterGroup,
} from "./characterPanelGroups";

export default {
  name: "TableCharacterPanel",
  components: {
    AuthenticatedImage,
    CharacterSheetEditor,
    TableCharacterAccessPanel,
    TableCharacterCreateForm,
  },
  props: {
    campaignId: { type: [Number, String], required: true },
    campaign: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    canCreateToken: { type: Boolean, default: false },
    canSelectForHud: { type: Boolean, default: false },
    canManageGroups: { type: Boolean, default: false },
    canManageAccess: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    initialCharacterId: { type: [Number, String], default: null },
    selectedHudCharacterId: { type: [Number, String], default: null },
  },
  emits: ["changed", "select-for-hud", "open-window"],
  data: () => ({
    characters: [],
    selectedId: null,
    selectedCharacter: null,
    query: "",
    loading: false,
    loadingSheet: false,
    saving: false,
    deleting: false,
    loadError: "",
    saveError: "",
    notice: "",
    canCreate: false,
    showingCreate: false,
    creating: false,
    createError: "",
    draggedCharacterId: null,
    draggedGroupCharacterId: null,
    groupDropTarget: null,
    activeGroupId: "players",
    characterScrollbar: { visible: false, thumbHeight: 0, thumbTop: 0 },
    characterScrollbarDrag: null,
    characterScrollbarObserver: null,
    groupLayout: null,
    listRequestSequence: 0,
    sheetRequestSequence: 0,
    createRequestSequence: 0,
  }),
  computed: {
    filteredCharacters() {
      const query = this.query.toLocaleLowerCase();
      return query
        ? this.characters.filter((item) =>
            item.name.toLocaleLowerCase().includes(query),
          )
        : this.characters;
    },
    groupSections() {
      const flatten = (groups, depth = 0) =>
        groups.flatMap((group) => [
          {
            group,
            depth,
            characters: group.characters,
            children: group.children,
          },
          ...(this.isGroupCollapsed(group.id)
            ? []
            : flatten(group.children, depth + 1)),
        ]);
      return flatten(
        characterGroupTree(this.groupLayout, this.filteredCharacters),
      );
    },
    chosenCharacter() {
      return (
        this.characters.find(
          (character) =>
            Number(character.id) === Number(this.selectedHudCharacterId),
        ) || null
      );
    },
  },
  watch: {
    campaignId: "resetAndLoad",
    initialCharacterId(value) {
      if (!this.compact && value && Number(value) !== this.selectedId)
        this.selectCharacter(value);
    },
    query() {
      this.$nextTick(this.syncCharacterScrollbar);
    },
    selectedHudCharacterId() {
      this.$nextTick(this.syncCharacterScrollbar);
    },
    groupLayout() {
      this.$nextTick(this.syncCharacterScrollbar);
    },
  },
  mounted() {
    this.loadCharacters();
    window.addEventListener("resize", this.syncCharacterScrollbar);
    window.addEventListener(
      "blatyrpg:character-access-changed",
      this.handleCharacterAccessRealtimeEvent,
    );
    if (typeof ResizeObserver !== "undefined") {
      this.characterScrollbarObserver = new ResizeObserver(() =>
        this.syncCharacterScrollbar(),
      );
      this.characterScrollbarObserver.observe(this.$refs.characterBrowser);
    }
  },
  beforeUnmount() {
    this.listRequestSequence += 1;
    this.sheetRequestSequence += 1;
    this.createRequestSequence += 1;
    window.removeEventListener("resize", this.syncCharacterScrollbar);
    window.removeEventListener(
      "blatyrpg:character-access-changed",
      this.handleCharacterAccessRealtimeEvent,
    );
    window.removeEventListener("pointermove", this.moveCharacterScrollbarDrag);
    window.removeEventListener("pointerup", this.stopCharacterScrollbarDrag);
    this.characterScrollbarObserver?.disconnect();
    endActorDrag();
  },
  methods: {
    ...tableCharacterCreationMethods,
    resetAndLoad() {
      this.listRequestSequence += 1;
      this.sheetRequestSequence += 1;
      this.characters = [];
      this.selectedId = null;
      this.selectedCharacter = null;
      this.groupLayout = null;
      this.activeGroupId = "players";
      this.resetCharacterCreation();
      this.loadCharacters();
    },
    async loadCharacters() {
      const sequence = ++this.listRequestSequence;
      this.loading = true;
      this.loadError = "";
      try {
        const result = await characterApiClient.list(this.campaignId, {
          assignedOnly: true,
        });
        if (sequence !== this.listRequestSequence) return;
        this.characters = result.characters;
        this.groupLayout = createCharacterGroupLayout(
          this.readGroupLayout(),
          this.characters,
        );
        this.activeGroupId = this.groupLayout.activeGroupId;
        this.persistGroupLayout();
        this.configureCharacterCreation(result);
        const selectedStillAvailable = this.characters.some(
          (character) => Number(character.id) === Number(this.selectedId),
        );
        if (!selectedStillAvailable) {
          this.selectedId = null;
          this.selectedCharacter = null;
        }
        const nextId =
          this.initialCharacterId ||
          (selectedStillAvailable ? this.selectedId : null) ||
          this.characters[0]?.id;
        if (!this.compact && nextId) await this.selectCharacter(nextId);
        this.$nextTick(this.syncCharacterScrollbar);
      } catch (error) {
        if (sequence === this.listRequestSequence) {
          this.loadError = this.$t(characterErrorKey(error, "load"));
        }
      } finally {
        if (sequence === this.listRequestSequence) this.loading = false;
      }
    },
    async selectCharacter(id) {
      const characterId = Number(id);
      const sequence = ++this.sheetRequestSequence;
      this.selectedId = characterId;
      this.loadingSheet = true;
      this.saveError = "";
      try {
        const character = await characterApiClient.get(
          this.campaignId,
          characterId,
        );
        if (sequence !== this.sheetRequestSequence) return;
        this.selectedCharacter = character;
        this.replaceCharacter(character);
      } catch (error) {
        if (sequence === this.sheetRequestSequence) {
          this.saveError = this.$t(characterErrorKey(error, "load"));
        }
      } finally {
        if (sequence === this.sheetRequestSequence) this.loadingSheet = false;
      }
    },
    async handleAccessChanged() {
      const selectedId = this.selectedId;
      await this.loadCharacters();
      this.$emit("changed", selectedId);
    },
    handleCharacterAccessRealtimeEvent(event) {
      if (Number(event?.detail?.campaignId) !== Number(this.campaignId)) return;
      this.handleAccessChanged();
    },
    canSelectCharacterForHud(character) {
      return this.canManageGroups || character?.capabilities?.canEdit === true;
    },
    selectCharacterIfExpanded(id) {
      if (!this.compact) this.selectCharacter(id);
    },
    selectCharacterForHud(id) {
      if (!this.canSelectForHud) return;
      this.$emit("select-for-hud", Number(id) || id);
    },
    clearHudCharacterSelection() {
      if (!this.canSelectForHud) return;
      this.$emit("select-for-hud", null);
    },
    groupStorageKey() {
      return `blatyrpg.table-character-groups.${this.campaignId}`;
    },
    readGroupLayout() {
      try {
        return JSON.parse(
          window.localStorage.getItem(this.groupStorageKey()) || "null",
        );
      } catch (_error) {
        return null;
      }
    },
    persistGroupLayout() {
      try {
        window.localStorage.setItem(
          this.groupStorageKey(),
          JSON.stringify(
            createCharacterGroupLayout(this.groupLayout, this.characters),
          ),
        );
      } catch (_error) {
        // The panel remains usable when browser storage is unavailable.
      }
    },
    updateGroupLayout(nextLayout) {
      this.groupLayout = createCharacterGroupLayout(
        nextLayout,
        this.characters,
      );
      this.activeGroupId = this.groupLayout.activeGroupId;
      this.persistGroupLayout();
    },
    syncCharacterScrollbar() {
      const browser = this.$refs.characterBrowser;
      if (!browser) return;
      const overflow = browser.scrollHeight > browser.clientHeight + 1;
      if (!overflow) {
        this.characterScrollbar = {
          visible: false,
          thumbHeight: 0,
          thumbTop: 0,
        };
        return;
      }
      const wasVisible = this.characterScrollbar.visible;
      const trackHeight =
        this.$refs.characterScrollbarTrack?.clientHeight ||
        browser.clientHeight;
      const thumbHeight = Math.max(
        28,
        (trackHeight * browser.clientHeight) / browser.scrollHeight,
      );
      const scrollableHeight = browser.scrollHeight - browser.clientHeight;
      const thumbTrackHeight = trackHeight - thumbHeight;
      this.characterScrollbar = {
        visible: true,
        thumbHeight,
        thumbTop: (browser.scrollTop / scrollableHeight) * thumbTrackHeight,
      };
      if (!wasVisible) this.$nextTick(this.syncCharacterScrollbar);
    },
    startCharacterScrollbarDrag(event) {
      const browser = this.$refs.characterBrowser;
      if (!browser) return;
      const scrollableHeight = browser.scrollHeight - browser.clientHeight;
      const trackHeight =
        (this.$refs.characterScrollbarTrack?.clientHeight ||
          browser.clientHeight) - this.characterScrollbar.thumbHeight;
      if (scrollableHeight <= 0 || trackHeight <= 0) return;
      this.characterScrollbarDrag = {
        startY: event.clientY,
        startScrollTop: browser.scrollTop,
        scrollPerPixel: scrollableHeight / trackHeight,
      };
      window.addEventListener("pointermove", this.moveCharacterScrollbarDrag);
      window.addEventListener("pointerup", this.stopCharacterScrollbarDrag, {
        once: true,
      });
    },
    moveCharacterScrollbarDrag(event) {
      const browser = this.$refs.characterBrowser;
      const drag = this.characterScrollbarDrag;
      if (!browser || !drag) return;
      browser.scrollTop =
        drag.startScrollTop +
        (event.clientY - drag.startY) * drag.scrollPerPixel;
    },
    jumpCharacterScrollbar(event) {
      const browser = this.$refs.characterBrowser;
      const track = this.$refs.characterScrollbarTrack;
      if (!browser || !track) return;
      const scrollableHeight = browser.scrollHeight - browser.clientHeight;
      const thumbTrackHeight =
        track.clientHeight - this.characterScrollbar.thumbHeight;
      if (scrollableHeight <= 0 || thumbTrackHeight <= 0) return;
      const bounds = track.getBoundingClientRect();
      const offset = Math.max(
        0,
        Math.min(
          thumbTrackHeight,
          event.clientY - bounds.top - this.characterScrollbar.thumbHeight / 2,
        ),
      );
      browser.scrollTop = (offset / thumbTrackHeight) * scrollableHeight;
    },
    stopCharacterScrollbarDrag() {
      this.characterScrollbarDrag = null;
      window.removeEventListener(
        "pointermove",
        this.moveCharacterScrollbarDrag,
      );
    },
    groupName(group) {
      if (group.id === "players")
        return this.$t("vtt.table.characters.playerHeroes");
      if (group.id === "npcs")
        return this.$t("vtt.table.characters.nonPlayerHeroes");
      return group.name;
    },
    isGroupCollapsed(groupId) {
      return this.groupLayout?.collapsed?.[String(groupId)] === true;
    },
    toggleGroup(groupId) {
      const id = String(groupId);
      const collapsed = { ...(this.groupLayout?.collapsed || {}) };
      if (collapsed[id]) delete collapsed[id];
      else collapsed[id] = true;
      this.updateGroupLayout({ ...this.groupLayout, collapsed });
    },
    selectGroup(groupId) {
      this.updateGroupLayout({
        ...this.groupLayout,
        activeGroupId: String(groupId),
      });
    },
    addGroup() {
      const name = window.prompt(
        this.$t("vtt.table.characters.groupNamePrompt"),
      );
      if (!name?.trim()) return;
      this.updateGroupLayout(
        appendGroup(this.groupLayout, this.activeGroupId || "players", name),
      );
    },
    renameGroup(group) {
      if (group.root) return;
      const name = window.prompt(
        this.$t("vtt.table.characters.renameGroupPrompt"),
        group.name,
      );
      if (!name?.trim()) return;
      this.updateGroupLayout(
        renameCharacterGroup(this.groupLayout, group.id, name),
      );
    },
    beginGroupDrag(event, character) {
      if (!this.canManageGroups) return;
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData(
        "application/x-blatyrpg-character",
        String(character.id),
      );
      event.dataTransfer.setData("text/plain", String(character.id));
      this.draggedGroupCharacterId = character.id;
      this.groupDropTarget = null;
    },
    finishGroupDrag() {
      this.draggedGroupCharacterId = null;
      this.groupDropTarget = null;
    },
    draggedGroupCharacter(event) {
      const id = Number(
        event.dataTransfer?.getData("application/x-blatyrpg-character") ||
          this.draggedGroupCharacterId,
      );
      return (
        this.characters.find((character) => Number(character.id) === id) || null
      );
    },
    previewGroupDrop(event, groupId, characters = [], index = null) {
      if (!this.draggedGroupCharacter(event)) return;
      event.dataTransfer.dropEffect = "move";
      let beforeCharacterId = null;
      if (index !== null) {
        const bounds = event.currentTarget.getBoundingClientRect();
        const after = event.clientY > bounds.top + bounds.height / 2;
        beforeCharacterId = after
          ? characters[index + 1]?.id || null
          : characters[index]?.id || null;
      }
      this.groupDropTarget = {
        groupId: String(groupId),
        beforeCharacterId: beforeCharacterId ? Number(beforeCharacterId) : null,
      };
    },
    isGroupDropTarget(groupId) {
      return (
        this.groupDropTarget?.groupId === String(groupId) &&
        this.groupDropTarget.beforeCharacterId === null
      );
    },
    isDropBefore(groupId, characterId) {
      return (
        this.groupDropTarget?.groupId === String(groupId) &&
        Number(this.groupDropTarget.beforeCharacterId) === Number(characterId)
      );
    },
    isDropAfter(groupId, characterId) {
      const target = this.groupDropTarget;
      if (
        !target ||
        target.groupId !== String(groupId) ||
        target.beforeCharacterId !== null
      ) {
        return false;
      }
      const group = this.groupSections.find(
        (section) => section.group.id === String(groupId),
      );
      const characters = group?.characters || [];
      return characters[characters.length - 1]?.id === characterId;
    },
    commitGroupDrop(event) {
      const character = this.draggedGroupCharacter(event);
      const target = this.groupDropTarget;
      if (!character || !target) return;
      const moved = moveCharacterToGroup(
        this.groupLayout,
        character.id,
        target.groupId,
      );
      this.updateGroupLayout(
        target.beforeCharacterId
          ? placeCharacterBefore(moved, character.id, target.beforeCharacterId)
          : moved,
      );
      this.finishGroupDrag();
    },
    async saveCharacter(draft) {
      if (!this.selectedId || this.saving) return;
      this.saving = true;
      this.saveError = "";
      try {
        const character = await characterApiClient.update(
          this.campaignId,
          this.selectedId,
          draft,
        );
        this.selectedCharacter = character;
        this.replaceCharacter(character);
        this.notice = this.$t("characters.notices.saved");
        this.$emit("changed", character);
      } catch (error) {
        this.saveError = this.$t(characterErrorKey(error, "save"));
      } finally {
        this.saving = false;
      }
    },
    async deleteCharacter() {
      const character = this.selectedCharacter;
      if (!character?.id || this.deleting) return;
      const question = this.$t("characters.delete.confirm", {
        name: character.name,
      });
      if (!window.confirm(question)) return;
      this.deleting = true;
      try {
        await characterApiClient.delete(this.campaignId, character.id);
        this.characters = this.characters.filter(
          (item) => item.id !== character.id,
        );
        this.selectedCharacter = null;
        this.selectedId = null;
        this.notice = this.$t("characters.notices.deleted");
        this.$emit("changed", character);
        if (this.characters[0])
          await this.selectCharacter(this.characters[0].id);
      } catch (error) {
        this.saveError = this.$t(characterErrorKey(error, "delete"));
      } finally {
        this.deleting = false;
      }
    },
    replaceCharacter(character) {
      const index = this.characters.findIndex(
        (item) => item.id === character.id,
      );
      if (index < 0) this.characters.push(character);
      else this.characters.splice(index, 1, character);
      this.characters.sort((left, right) =>
        left.name.localeCompare(right.name),
      );
    },
    avatar(character) {
      return resolveCharacterAvatar(character, character.name);
    },
    details(character) {
      const details = character.data?.details || {};
      return [details.race, details.profession, details.class]
        .filter(Boolean)
        .join(" · ");
    },
    dragCharacter(event, character) {
      if (!this.canCreateToken) {
        event.preventDefault();
        return;
      }
      const actor = beginActorDrag(event.dataTransfer, {
        id: character.id,
        name: character.name,
        imageUrl: resolveCharacterTokenSource(character, character, character),
      });
      if (!actor) event.preventDefault();
      else this.draggedCharacterId = actor.id;
    },
    finishCharacterDrag() {
      this.draggedCharacterId = null;
      endActorDrag();
    },
  },
};
</script>
