<template>
  <section class="campaign-panel campaign-settings-panel">
    <div class="campaign-panel-heading">
      <div>
        <p class="campaign-panel-kicker">
          {{ $t("campaignLobby.settings.kicker") }}
        </p>
        <h2>{{ $t("campaignLobby.settings.title") }}</h2>
      </div>
      <span class="campaign-status-chip">{{
        $t(`campaignLobby.status.${draft.status}`)
      }}</span>
    </div>
    <form class="campaign-panel-form" @submit.prevent="submit">
      <div class="campaign-settings-grid">
        <label class="campaign-settings-wide">
          <span>{{ $t("campaignLobby.fields.name") }}</span>
          <input
            v-model.trim="draft.name"
            required
            maxlength="255"
            :disabled="busy"
          />
        </label>
        <label>
          <span>{{ $t("campaignLobby.fields.system") }}</span>
          <select
            v-model.number="draft.systemId"
            required
            :disabled="busy"
            @change="selectSystem"
          >
            <option
              v-for="system in systems"
              :key="system.id"
              :value="system.id"
            >
              {{ system.name }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("campaignLobby.fields.world") }}</span>
          <select
            v-model.number="draft.universeId"
            required
            :disabled="busy || !worlds.length"
          >
            <option v-for="world in worlds" :key="world.id" :value="world.id">
              {{ world.name }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("campaignLobby.fields.status") }}</span>
          <select v-model="draft.status" :disabled="busy">
            <option v-for="status in statuses" :key="status" :value="status">
              {{ $t(`campaignLobby.status.${status}`) }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("campaignLobby.fields.banner") }}</span>
          <input
            v-model.trim="draft.bannerUrl"
            maxlength="2048"
            :disabled="busy"
          />
        </label>
        <label class="campaign-settings-wide">
          <span>{{ $t("campaignLobby.fields.description") }}</span>
          <textarea
            v-model.trim="draft.description"
            maxlength="10000"
            rows="2"
            :disabled="busy"
          />
        </label>
      </div>

      <fieldset class="campaign-table-settings">
        <legend>{{ $t("campaignLobby.settings.table") }}</legend>
        <label>
          <span>{{ $t("campaignLobby.fields.visibility") }}</span>
          <select v-model="draft.settings.tableVisibility" :disabled="busy">
            <option value="invite_only">
              {{ $t("campaignLobby.visibility.inviteOnly") }}
            </option>
            <option value="private">
              {{ $t("campaignLobby.visibility.private") }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("campaignLobby.fields.diceVisibility") }}</span>
          <select v-model="draft.settings.diceVisibility" :disabled="busy">
            <option value="public">
              {{ $t("campaignLobby.dice.public") }}
            </option>
            <option value="gm">{{ $t("campaignLobby.dice.gm") }}</option>
            <option value="private">
              {{ $t("campaignLobby.dice.private") }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("campaignLobby.fields.gridSize") }}</span>
          <input
            v-model.number="draft.settings.defaultGridSize"
            type="number"
            min="16"
            max="256"
            :disabled="busy"
          />
        </label>
        <div class="campaign-toggle-grid">
          <label
            v-for="field in toggles"
            :key="field.key"
            class="campaign-toggle"
          >
            <input
              v-model="draft.settings[field.key]"
              type="checkbox"
              :disabled="busy"
            />
            <span>{{ $t(field.label) }}</span>
          </label>
        </div>
      </fieldset>
      <button
        class="campaign-primary-action"
        type="submit"
        :disabled="busy || !draft.systemId || !draft.universeId"
      >
        {{ $t("campaignLobby.actions.save") }}
      </button>
    </form>
  </section>
</template>

<script>
import {
  campaignSettingsDraft,
  systemsFromGames,
  worldsForSystem,
} from "@/lib/campaign/campaignSettingsDraft";

export default {
  name: "CampaignSettingsPanel",
  props: {
    campaign: { type: Object, required: true },
    games: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
  },
  emits: ["save"],
  data() {
    return {
      draft: campaignSettingsDraft(this.campaign),
      statuses: ["active", "paused", "archived"],
      toggles: [
        {
          key: "allowPlayerDrawing",
          label: "campaignLobby.settings.allowDrawing",
        },
        {
          key: "allowPlayerTokenMovement",
          label: "campaignLobby.settings.allowMovement",
        },
        { key: "autoOpenLastScene", label: "campaignLobby.settings.autoScene" },
        {
          key: "showPlayerCursors",
          label: "campaignLobby.settings.showCursors",
        },
      ],
    };
  },
  computed: {
    systems() {
      return systemsFromGames(this.games);
    },
    worlds() {
      return worldsForSystem(this.games, this.draft.systemId);
    },
  },
  watch: {
    campaign: {
      deep: true,
      handler(value) {
        this.draft = campaignSettingsDraft(value);
      },
    },
  },
  methods: {
    selectSystem() {
      if (!this.worlds.some((world) => world.id === this.draft.universeId)) {
        this.draft.universeId = this.worlds[0]?.id || null;
      }
    },
    submit() {
      this.$emit("save", {
        ...this.draft,
        settings: { ...this.draft.settings },
      });
    },
  },
};
</script>
