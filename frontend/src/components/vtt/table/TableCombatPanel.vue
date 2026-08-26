<template>
  <section class="table-combat-panel">
    <header class="table-combat-panel__status">
      <div>
        <small>{{ $t("vtt.table.combat.kicker") }}</small>
        <strong v-if="combat?.active">
          {{ $t("vtt.table.combat.roundTurn", turnLabel) }}
        </strong>
        <strong v-else>{{ $t("vtt.table.combat.inactive") }}</strong>
      </div>
      <div v-if="canManage" class="table-combat-panel__controls">
        <button
          v-if="!combat?.active"
          type="button"
          :disabled="busy || !tokens.length"
          @click="start"
        >
          {{ $t("vtt.table.combat.start") }}
        </button>
        <template v-else>
          <button type="button" :disabled="busy" @click="command('previous')">
            ‹
          </button>
          <button type="button" :disabled="busy" @click="command('next')">
            {{ $t("vtt.table.combat.next") }} ›
          </button>
          <button type="button" :disabled="busy" @click="command('end')">
            {{ $t("vtt.table.combat.end") }}
          </button>
        </template>
      </div>
    </header>

    <p v-if="error" class="table-combat-panel__error">
      {{ $t("vtt.table.combat.error") }}
    </p>

    <ol v-if="combat?.combatants?.length" class="table-combat-panel__list">
      <li
        v-for="(combatant, index) in combat.combatants"
        :key="combatant.id"
        :class="{
          active: combat.activeTokenId === combatant.tokenId,
          defeated: combatant.defeated,
        }"
      >
        <b class="table-combat-panel__rank">{{ index + 1 }}</b>
        <CombatTokenIdentity :token="combatant.token" />
        <CombatMovementBar
          :points="combatant.token.movementPoints"
          :range="combatant.token.movementRange"
          :prefix="$t('vtt.table.combat.movementShort')"
          :label="movementLabel(combatant.token)"
          :color="movementColor(combatant.token)"
        />
        <label class="table-combat-panel__initiative">
          <span>{{ $t("vtt.table.combat.initiative") }}</span>
          <input
            :value="combatant.initiative"
            type="number"
            step="0.01"
            :disabled="!canManage || busy"
            @change="setInitiative(combatant, $event.target.value)"
          />
        </label>
        <button
          v-if="canManage"
          type="button"
          :title="$t('vtt.table.combat.remove')"
          :disabled="busy"
          @click="toggle(combatant.tokenId)"
        >
          ×
        </button>
      </li>
    </ol>
    <p v-else class="table-combat-panel__empty">
      {{ $t("vtt.table.combat.empty") }}
    </p>

    <section v-if="canManage" class="table-combat-panel__movement">
      <header>
        <div>
          <small>{{ $t("vtt.table.combat.movementKicker") }}</small>
          <strong>{{ $t("vtt.table.combat.movementTitle") }}</strong>
        </div>
        <button type="button" :disabled="busy" @click="resetMovement([])">
          {{ $t("vtt.table.combat.resetAll") }}
        </button>
      </header>
      <CombatMovementRow
        v-for="token in tokens"
        :key="token.id"
        :token="token"
        :draft="draft(token)"
        :expanded="expandedTokenId === token.id"
        :in-roster="combatantIds.has(token.id)"
        :show-roster="Boolean(combat?.id)"
        :busy="busy"
        @toggle="toggle(token.id)"
        @expand="toggleMovementEditor(token.id)"
        @edit="edit(token, $event.key, $event.value)"
        @reset="resetMovement([token.id])"
        @save="saveMovement(token)"
      />
      <p v-if="!tokens.length" class="table-combat-panel__empty">
        {{ $t("vtt.table.combat.movementEmpty") }}
      </p>
    </section>
  </section>
</template>

<script>
import CombatMovementBar from "./CombatMovementBar.vue";
import CombatMovementRow from "./CombatMovementRow.vue";
import CombatTokenIdentity from "./CombatTokenIdentity.vue";
import { tokenMovementColor } from "@/lib/vtt/combatPresentation";

export default {
  name: "TableCombatPanel",
  components: {
    CombatMovementBar,
    CombatMovementRow,
    CombatTokenIdentity,
  },
  props: {
    combat: { type: Object, default: null },
    tokens: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    error: { type: Object, default: null },
  },
  emits: ["command"],
  data: () => ({ movementDrafts: {}, expandedTokenId: null }),
  watch: {
    tokens() {
      this.movementDrafts = {};
    },
  },
  computed: {
    combatantIds() {
      return new Set(
        (this.combat?.combatants || []).map(({ tokenId }) => tokenId),
      );
    },
    turnLabel() {
      return {
        round: this.combat?.round || 0,
        turn: (this.combat?.turnIndex || 0) + 1,
        count: this.combat?.combatants?.length || 0,
      };
    },
  },
  methods: {
    movementColor(token) {
      return tokenMovementColor(token);
    },
    movementLabel(token) {
      return this.$t("vtt.table.combat.movementValue", {
        points: token.movementPoints,
        range: token.movementRange,
      });
    },
    toggleMovementEditor(tokenId) {
      this.expandedTokenId = this.expandedTokenId === tokenId ? null : tokenId;
    },
    draft(token) {
      return (
        this.movementDrafts[token.id] || {
          points: token.movementPoints,
          range: token.movementRange,
          resetMode: token.movementResetMode,
        }
      );
    },
    edit(token, key, value) {
      const draft = { ...this.draft(token) };
      draft[key] =
        key === "resetMode" ? value : Math.max(0, Number(value) || 0);
      if (key === "range") draft.points = Math.min(draft.points, draft.range);
      this.movementDrafts = { ...this.movementDrafts, [token.id]: draft };
    },
    command(action, extra = {}) {
      this.$emit("command", {
        action,
        revision: this.combat?.revision,
        ...extra,
      });
    },
    start() {
      const tokenIds = this.combatantIds.size
        ? [...this.combatantIds]
        : this.tokens.map(({ id }) => id);
      const command = {
        action: "start",
        tokenIds,
      };
      if (this.combat?.id) command.revision = this.combat.revision;
      this.$emit("command", command);
    },
    toggle(tokenId) {
      this.command("toggle", { tokenId });
    },
    setInitiative(combatant, initiative) {
      this.command("initiative", {
        tokenId: combatant.tokenId,
        initiative: Number(initiative),
      });
    },
    resetMovement(tokenIds) {
      this.$emit("command", { action: "resetMovement", tokenIds });
    },
    saveMovement(token) {
      const draft = this.draft(token);
      this.$emit("command", {
        action: "setMovement",
        tokenId: token.id,
        tokenRevision: token.revision,
        movementRange: draft.range,
        movementPoints: draft.points,
        movementResetMode: draft.resetMode,
      });
    },
  },
};
</script>
