<template>
  <section class="character-professions">
    <div v-if="!characterId" class="profession-state">
      <strong>{{ $t("vtt.table.professions.noCharacter") }}</strong>
      <span>{{ $t("vtt.table.professions.catalogStillAvailable") }}</span>
    </div>
    <div
      v-else-if="state.phase === 'loading'"
      class="profession-state"
      role="status"
    >
      {{ $t("vtt.table.professions.characterLoading") }}
    </div>
    <div
      v-else-if="state.phase === 'error'"
      class="profession-state profession-state--error"
      role="alert"
    >
      <strong>{{ characterError }}</strong>
      <button type="button" @click="retry">
        {{ $t("vtt.table.professions.retry") }}
      </button>
    </div>
    <template v-else>
      <header>
        <p>{{ $t("vtt.table.professions.characterProfessionKicker") }}</p>
        <h3>
          {{ state.record?.name || $t("vtt.table.professions.character") }}
        </h3>
      </header>

      <section class="character-professions__current">
        <h4>{{ $t("vtt.table.professions.currentProfession") }}</h4>
        <template v-if="state.current">
          <button
            v-if="state.current.linked"
            type="button"
            @click="openProfession(state.current.professionId)"
          >
            <strong>{{ displayName(state.current.name) }}</strong>
            <span>{{ $t("vtt.table.professions.openCard") }} →</span>
          </button>
          <div v-else class="character-professions__unlinked">
            <strong>{{
              $t("vtt.table.professions.unlinkedProfession")
            }}</strong>
            <span>{{
              $t("vtt.table.professions.entryNumber", {
                id: state.current.professionId,
              })
            }}</span>
          </div>
        </template>
        <p v-else>{{ $t("vtt.table.professions.currentProfessionMissing") }}</p>
      </section>

      <section
        v-if="canManageProfession"
        class="character-professions__manager"
      >
        <header>
          <h4>{{ $t("vtt.table.professions.gmProfessionChange") }}</h4>
          <b>{{ $t("vtt.table.professions.zeroXp") }}</b>
        </header>
        <form @submit.prevent="saveProfession">
          <label for="character-profession-select">
            {{ $t("vtt.table.professions.chooseNewProfession") }}
          </label>
          <div>
            <ProfessionAutocomplete
              v-model="selectedProfessionId"
              input-id="character-profession-select"
              :professions="professionOptions"
              :disabled="professionSaving"
            />
            <button type="submit" :disabled="professionSaveDisabled">
              {{
                $t(
                  professionSaving
                    ? "vtt.table.professions.savingProfession"
                    : "vtt.table.professions.saveProfession",
                )
              }}
            </button>
          </div>
        </form>
        <small>{{ $t("vtt.table.professions.gmProfessionChangeHint") }}</small>
        <p
          v-if="professionFeedback"
          :class="`is-${professionFeedback.type}`"
          role="status"
        >
          {{ $t(professionFeedback.key) }}
        </p>
      </section>

      <section class="character-professions__history">
        <h4>{{ $t("vtt.table.professions.history") }}</h4>
        <p v-if="state.historyAvailable === false">
          {{ $t("vtt.table.professions.historyUnavailable") }}
        </p>
        <p v-else-if="!orderedHistory.length">
          {{ $t("vtt.table.professions.noPreviousProfessions") }}
        </p>
        <ol v-else>
          <li v-for="(item, index) in orderedHistory" :key="item.historyId">
            <div class="character-professions__history-entry">
              <button
                v-if="item.linked"
                type="button"
                class="character-professions__history-link"
                @click="openProfession(item.professionId)"
              >
                {{ displayName(item.name) }}
              </button>
              <span v-else>{{
                $t("vtt.table.professions.unlinkedProfessionWithId", {
                  id: item.professionId,
                })
              }}</span>
              <em :class="{ 'is-finished': item.isFinished }">
                {{
                  $t(
                    item.isFinished
                      ? "vtt.table.professions.finished"
                      : "vtt.table.professions.completionUnconfirmed",
                  )
                }}
              </em>
            </div>
            <nav
              v-if="canManageProfession"
              :aria-label="$t('vtt.table.professions.historyActions')"
            >
              <button
                type="button"
                class="is-activate"
                :title="$t('vtt.table.professions.activateHistoryEntry')"
                :disabled="historyBusy"
                @click="activateHistory(item)"
              >
                ↥
              </button>
              <button
                v-if="canReorderHistory"
                type="button"
                :title="$t('vtt.table.professions.moveProfessionUp')"
                :disabled="index === 0 || historyBusy"
                @click="moveHistory(index, -1)"
              >
                ↑
              </button>
              <button
                v-if="canReorderHistory"
                type="button"
                :title="$t('vtt.table.professions.moveProfessionDown')"
                :disabled="index === orderedHistory.length - 1 || historyBusy"
                @click="moveHistory(index, 1)"
              >
                ↓
              </button>
              <button
                type="button"
                class="is-danger"
                :title="$t('vtt.table.professions.deleteHistoryEntry')"
                :disabled="historyBusy"
                @click="deleteHistory(item)"
              >
                ×
              </button>
            </nav>
          </li>
        </ol>
        <div
          v-if="canReorderHistory && orderedHistory.length"
          class="character-professions__history-save"
        >
          <button
            type="button"
            :disabled="!historyOrderDirty || historyBusy"
            @click="saveHistoryOrder"
          >
            {{
              $t(
                historyBusy === "order"
                  ? "vtt.table.professions.savingOrder"
                  : "vtt.table.professions.saveOrder",
              )
            }}
          </button>
          <span
            v-if="historyFeedback"
            :class="`is-${historyFeedback.type}`"
            role="status"
          >
            {{ $t(historyFeedback.key) }}
          </span>
        </div>
      </section>

      <aside>{{ $t("vtt.table.professions.advancementHint") }}</aside>
    </template>
  </section>
</template>

<script>
import { displayProfessionName } from "./professionPresentation";
import ProfessionAutocomplete from "./ProfessionAutocomplete.vue";

export default {
  name: "CharacterProfessions",
  components: { ProfessionAutocomplete },
  props: {
    campaignId: { type: [Number, String], required: true },
    characterId: { type: [Number, String], default: null },
  },
  data: () => ({
    selectedProfessionId: null,
    professionSaving: false,
    professionFeedback: null,
    historyOrder: [],
    historyBusy: null,
    historyFeedback: null,
  }),
  computed: {
    state() {
      return this.$store.state.professions.character;
    },
    characterError() {
      const status = Number(this.state.error?.status || 0);
      if (status === 401) return this.$t("vtt.table.professions.unauthorized");
      if (status === 403)
        return this.$t("vtt.table.professions.characterForbidden");
      if (status === 404)
        return this.$t("vtt.table.professions.characterNotFound");
      return this.$t("vtt.table.professions.characterLoadFailed");
    },
    canManageProfession() {
      return (
        this.state.historyAvailable !== false &&
        this.state.capabilities?.canManageProfession === true
      );
    },
    canReorderHistory() {
      return (
        this.canManageProfession &&
        this.state.capabilities?.canReorderProfessionHistory === true
      );
    },
    professionOptions() {
      return [...this.$store.state.professions.items].sort((left, right) =>
        displayProfessionName(left.name).localeCompare(
          displayProfessionName(right.name),
          "pl",
          { sensitivity: "base" },
        ),
      );
    },
    professionSaveDisabled() {
      return (
        this.professionSaving ||
        !Number(this.selectedProfessionId) ||
        Number(this.selectedProfessionId) ===
          Number(this.state.current?.professionId)
      );
    },
    orderedHistory() {
      const byId = new Map(
        this.state.history.map((item) => [Number(item.historyId), item]),
      );
      const ordered = this.historyOrder
        .map((id) => byId.get(Number(id)))
        .filter(Boolean);
      const included = new Set(ordered.map((item) => Number(item.historyId)));
      return ordered.concat(
        this.state.history.filter(
          (item) => !included.has(Number(item.historyId)),
        ),
      );
    },
    historyOrderDirty() {
      return this.orderedHistory.some(
        (item, index) =>
          Number(item.historyId) !==
          Number(this.state.history[index]?.historyId),
      );
    },
  },
  watch: {
    "state.current.professionId": {
      immediate: true,
      handler(value) {
        this.selectedProfessionId = value ? Number(value) : null;
      },
    },
    "state.history": {
      immediate: true,
      handler(value) {
        this.historyOrder = (value || []).map((item) => Number(item.historyId));
      },
    },
  },
  methods: {
    displayName: displayProfessionName,
    openProfession(id) {
      const exists = this.$store.state.professions.items.some(
        (item) => Number(item.id) === Number(id),
      );
      this.$store.commit("professions/SET_VIEW", "catalog");
      this.$store.commit("professions/SELECT", exists ? id : null);
    },
    retry() {
      this.$store.dispatch("professions/loadCharacter", {
        campaignId: this.campaignId,
        characterId: this.characterId,
        force: true,
      });
    },
    async saveProfession() {
      if (this.professionSaveDisabled) return;
      this.professionSaving = true;
      this.professionFeedback = null;
      try {
        const result = await this.$store.dispatch(
          "professions/changeCharacterProfession",
          {
            campaignId: this.campaignId,
            characterId: this.characterId,
            professionId: this.selectedProfessionId,
          },
        );
        this.professionFeedback = {
          type: "success",
          key:
            result?.changed === false
              ? "vtt.table.professions.professionUnchanged"
              : "vtt.table.professions.professionSaved",
        };
      } catch (_error) {
        this.professionFeedback = {
          type: "error",
          key: "vtt.table.professions.professionSaveFailed",
        };
      } finally {
        this.professionSaving = false;
      }
    },
    moveHistory(index, direction) {
      const target = index + direction;
      if (target < 0 || target >= this.orderedHistory.length) return;
      const next = this.orderedHistory.map((item) => Number(item.historyId));
      [next[index], next[target]] = [next[target], next[index]];
      this.historyOrder = next;
      this.historyFeedback = null;
    },
    async saveHistoryOrder() {
      if (!this.historyOrderDirty || this.historyBusy) return;
      this.historyBusy = "order";
      this.historyFeedback = null;
      try {
        await this.$store.dispatch("professions/reorderCharacterProfessions", {
          campaignId: this.campaignId,
          characterId: this.characterId,
          historyIds: this.orderedHistory.map((item) => item.historyId),
        });
        this.historyFeedback = {
          type: "success",
          key: "vtt.table.professions.orderSaved",
        };
      } catch (_error) {
        this.historyFeedback = {
          type: "error",
          key: "vtt.table.professions.orderSaveFailed",
        };
      } finally {
        this.historyBusy = null;
      }
    },
    async deleteHistory(item) {
      if (this.historyBusy) return;
      const confirmed = window.confirm(
        this.$t("vtt.table.professions.deleteHistoryConfirm", {
          name: displayProfessionName(item.name),
        }),
      );
      if (!confirmed) return;
      this.historyBusy = Number(item.historyId);
      this.historyFeedback = null;
      try {
        await this.$store.dispatch("professions/deleteCharacterProfession", {
          campaignId: this.campaignId,
          characterId: this.characterId,
          historyId: item.historyId,
        });
        this.historyFeedback = {
          type: "success",
          key: "vtt.table.professions.historyEntryDeleted",
        };
      } catch (_error) {
        this.historyFeedback = {
          type: "error",
          key: "vtt.table.professions.historyDeleteFailed",
        };
      } finally {
        this.historyBusy = null;
      }
    },
    async activateHistory(item) {
      if (this.historyBusy) return;
      const confirmed = window.confirm(
        this.$t("vtt.table.professions.activateHistoryConfirm", {
          name: displayProfessionName(item.name),
          current: displayProfessionName(this.state.current?.name),
        }),
      );
      if (!confirmed) return;
      this.historyBusy = `activate-${item.historyId}`;
      this.historyFeedback = null;
      try {
        await this.$store.dispatch("professions/activateCharacterProfession", {
          campaignId: this.campaignId,
          characterId: this.characterId,
          historyId: item.historyId,
        });
        this.historyFeedback = {
          type: "success",
          key: "vtt.table.professions.historyEntryActivated",
        };
      } catch (_error) {
        this.historyFeedback = {
          type: "error",
          key: "vtt.table.professions.historyActivationFailed",
        };
      } finally {
        this.historyBusy = null;
      }
    },
  },
};
</script>
