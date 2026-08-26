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
        v-for="combatant in combat.combatants"
        :key="combatant.id"
        :class="{
          active: combat.activeTokenId === combatant.tokenId,
          defeated: combatant.defeated,
        }"
      >
        <span class="table-combat-panel__avatar">
          <img
            v-if="combatant.token.imageUrl"
            :src="combatant.token.imageUrl"
            alt=""
          />
          <b v-else>{{ initials(combatant.token.name) }}</b>
        </span>
        <span class="table-combat-panel__identity">
          <strong>{{ combatant.token.name }}</strong>
          <small>
            {{ $t("vtt.table.combat.movementShort") }}
            {{ combatant.token.movementPoints }} /
            {{ combatant.token.movementRange }}
          </small>
        </span>
        <label>
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
      <div
        v-for="token in tokens"
        :key="token.id"
        class="table-combat-panel__movement-row"
      >
        <button
          v-if="combat?.id"
          type="button"
          :class="{ active: combatantIds.has(token.id) }"
          :title="$t('vtt.table.combat.toggleRoster')"
          :disabled="busy"
          @click="toggle(token.id)"
        >
          {{ combatantIds.has(token.id) ? "✓" : "+" }}
        </button>
        <strong>{{ token.name }}</strong>
        <label>
          <span>{{ $t("vtt.table.combat.points") }}</span>
          <input
            :value="draft(token).points"
            type="number"
            min="0"
            :max="draft(token).range"
            step="0.5"
            @input="edit(token, 'points', $event.target.value)"
          />
        </label>
        <label>
          <span>{{ $t("vtt.table.combat.range") }}</span>
          <input
            :value="draft(token).range"
            type="number"
            min="0"
            max="10000"
            step="0.5"
            @input="edit(token, 'range', $event.target.value)"
          />
        </label>
        <select
          :value="draft(token).resetMode"
          :title="$t('vtt.table.combat.resetMode')"
          @change="edit(token, 'resetMode', $event.target.value)"
        >
          <option value="turn">{{ $t("vtt.token.movement.turn") }}</option>
          <option value="round">{{ $t("vtt.token.movement.round") }}</option>
          <option value="manual">{{ $t("vtt.token.movement.manual") }}</option>
        </select>
        <button type="button" :disabled="busy" @click="saveMovement(token)">
          {{ $t("vtt.table.combat.assign") }}
        </button>
        <button
          type="button"
          :disabled="busy"
          @click="resetMovement([token.id])"
        >
          ↺
        </button>
      </div>
    </section>
  </section>
</template>

<script>
export default {
  name: "TableCombatPanel",
  props: {
    combat: { type: Object, default: null },
    tokens: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    error: { type: Object, default: null },
  },
  emits: ["command"],
  data: () => ({ movementDrafts: {} }),
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
    initials(name) {
      return String(name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("");
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
