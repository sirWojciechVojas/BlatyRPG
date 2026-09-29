<template>
  <section class="table-settings-panel">
    <nav
      class="table-settings-panel__tabs"
      :aria-label="$t('vtt.table.settings.tabsLabel')"
    >
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        :class="{ active: activeTab === tab.id }"
        :aria-pressed="String(activeTab === tab.id)"
        @click="activeTab = tab.id"
      >
        {{ $t(tab.labelKey) }}
      </button>
    </nav>

    <p v-if="activeError" class="table-settings-panel__error" role="alert">
      {{ activeError }}
    </p>

    <form
      v-if="activeTab === 'campaign' && canManage"
      class="table-settings-panel__form"
      @submit.prevent="saveCampaign"
    >
      <header class="table-settings-panel__heading">
        <div>
          <small>{{ $t("vtt.table.settings.campaign.kicker") }}</small>
          <h3>{{ $t("vtt.table.settings.campaign.title") }}</h3>
        </div>
        <span>{{ $t(`vtt.table.settings.status.${draft.status}`) }}</span>
      </header>

      <p v-if="catalogLoading" role="status">
        {{ $t("vtt.table.settings.catalogLoading") }}
      </p>

      <label>
        <span>{{ $t("vtt.table.settings.fields.name") }}</span>
        <input
          v-model.trim="draft.name"
          required
          maxlength="255"
          :disabled="campaignBusy"
        />
      </label>
      <label>
        <span>{{ $t("vtt.table.settings.fields.system") }}</span>
        <select
          v-model.number="draft.systemId"
          required
          :disabled="campaignBusy || catalogLoading"
          @change="selectSystem"
        >
          <option v-for="system in systems" :key="system.id" :value="system.id">
            {{ system.name }}
          </option>
        </select>
      </label>
      <label>
        <span>{{ $t("vtt.table.settings.fields.world") }}</span>
        <select
          v-model.number="draft.universeId"
          required
          :disabled="campaignBusy || catalogLoading || !worlds.length"
        >
          <option v-for="world in worlds" :key="world.id" :value="world.id">
            {{ world.name }}
          </option>
        </select>
      </label>
      <label>
        <span>{{ $t("vtt.table.settings.fields.status") }}</span>
        <select v-model="draft.status" :disabled="campaignBusy">
          <option v-for="status in statuses" :key="status" :value="status">
            {{ $t(`vtt.table.settings.status.${status}`) }}
          </option>
        </select>
      </label>
      <label>
        <span>{{ $t("vtt.table.settings.fields.banner") }}</span>
        <input
          v-model.trim="draft.bannerUrl"
          maxlength="2048"
          :disabled="campaignBusy"
        />
      </label>
      <label>
        <span>{{ $t("vtt.table.settings.fields.description") }}</span>
        <textarea
          v-model.trim="draft.description"
          maxlength="10000"
          rows="3"
          :disabled="campaignBusy"
        />
      </label>

      <fieldset>
        <legend>{{ $t("vtt.table.settings.rules.title") }}</legend>
        <label>
          <span>{{ $t("vtt.table.settings.fields.visibility") }}</span>
          <select
            v-model="draft.settings.tableVisibility"
            :disabled="campaignBusy"
          >
            <option value="invite_only">
              {{ $t("vtt.table.settings.visibility.inviteOnly") }}
            </option>
            <option value="private">
              {{ $t("vtt.table.settings.visibility.private") }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("vtt.table.settings.fields.diceVisibility") }}</span>
          <select
            v-model="draft.settings.diceVisibility"
            :disabled="campaignBusy"
          >
            <option value="public">
              {{ $t("vtt.table.settings.dice.public") }}
            </option>
            <option value="gm">{{ $t("vtt.table.settings.dice.gm") }}</option>
            <option value="private">
              {{ $t("vtt.table.settings.dice.private") }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("vtt.table.settings.fields.gridSize") }}</span>
          <input
            v-model.number="draft.settings.defaultGridSize"
            type="number"
            min="16"
            max="256"
            :disabled="campaignBusy"
          />
        </label>
        <label
          v-for="toggle in toggles"
          :key="toggle.key"
          class="table-settings-panel__toggle"
        >
          <input
            v-model="draft.settings[toggle.key]"
            type="checkbox"
            :disabled="campaignBusy"
          />
          <span>{{ $t(toggle.labelKey) }}</span>
        </label>
      </fieldset>

      <button
        type="submit"
        class="scene-button scene-button--primary"
        :disabled="
          campaignBusy || catalogLoading || !draft.systemId || !draft.universeId
        "
      >
        {{ $t("vtt.table.settings.actions.save") }}
      </button>
    </form>

    <section
      v-else-if="activeTab === 'members' && canManage"
      class="table-settings-panel__members"
    >
      <header class="table-settings-panel__heading">
        <div>
          <small>{{ $t("vtt.table.settings.members.kicker") }}</small>
          <h3>{{ $t("vtt.table.settings.members.title") }}</h3>
        </div>
      </header>

      <form class="table-settings-panel__invite" @submit.prevent="invite">
        <input
          v-model.trim="inviteDraft.identifier"
          :placeholder="$t('vtt.table.settings.members.identifier')"
          required
          maxlength="255"
          :disabled="campaignBusy"
        />
        <select v-model="inviteDraft.role" :disabled="campaignBusy">
          <option v-for="role in roles" :key="role" :value="role">
            {{ $t(`vtt.table.settings.roles.${role}`) }}
          </option>
        </select>
        <input
          v-model.trim="inviteDraft.message"
          :placeholder="$t('vtt.table.settings.members.message')"
          maxlength="500"
          :disabled="campaignBusy"
        />
        <button
          type="submit"
          class="scene-button scene-button--primary"
          :disabled="campaignBusy"
        >
          {{ $t("vtt.table.settings.actions.invite") }}
        </button>
      </form>

      <ul class="table-settings-panel__list">
        <li v-for="member in members" :key="member.userId">
          <span>
            <strong>{{ memberName(member) }}</strong>
            <small>{{ member.email }}</small>
          </span>
          <select
            :value="member.role"
            :disabled="campaignBusy || isOwner(member)"
            :aria-label="
              $t('vtt.table.settings.members.roleFor', {
                name: memberName(member),
              })
            "
            @change="changeRole(member.userId, $event.target.value)"
          >
            <option v-for="role in roles" :key="role" :value="role">
              {{ $t(`vtt.table.settings.roles.${role}`) }}
            </option>
          </select>
          <button
            type="button"
            class="scene-button scene-button--danger"
            :disabled="campaignBusy || isOwner(member)"
            @click="removeMember(member.userId)"
          >
            {{ $t("vtt.table.settings.actions.remove") }}
          </button>
        </li>
      </ul>

      <h4>{{ $t("vtt.table.settings.members.pending") }}</h4>
      <ul v-if="pendingInvitations.length" class="table-settings-panel__list">
        <li v-for="invitation in pendingInvitations" :key="invitation.id">
          <span>
            <strong>{{ invitationName(invitation) }}</strong>
            <small>{{
              $t(`vtt.table.settings.roles.${invitation.role}`)
            }}</small>
          </span>
          <button
            type="button"
            class="scene-button"
            :disabled="campaignBusy"
            @click="revokeInvitation(invitation.id)"
          >
            {{ $t("vtt.table.settings.actions.revoke") }}
          </button>
        </li>
      </ul>
      <p v-else class="table-settings-panel__empty">
        {{ $t("vtt.table.settings.members.noPending") }}
      </p>
    </section>

    <section v-else class="table-settings-panel__audio">
      <p>{{ $t("vtt.table.settings.audio.description") }}</p>
      <router-link class="scene-button" :to="{ name: 'profile' }">
        {{ $t("vtt.table.settings.audio.profile") }}
      </router-link>
      <AudioDeviceSettings :campaign-id="campaign.id" :can-manage="canManage" />
    </section>
  </section>
</template>

<script>
import AudioDeviceSettings from "@/components/audio/AudioDeviceSettings.vue";
import {
  campaignSettingsDraft,
  systemsFromGames,
  worldsForSystem,
} from "@/lib/campaign/campaignSettingsDraft";
import { gameCatalogApiClient } from "@/lib/catalog/gameCatalogApiClient";

export default {
  name: "TableSettingsPanel",
  components: { AudioDeviceSettings },
  props: {
    campaign: { type: Object, default: () => ({}) },
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
  },
  data() {
    return {
      activeTab: this.canManage ? "campaign" : "audio",
      draft: campaignSettingsDraft(this.campaign),
      inviteDraft: { identifier: "", role: "player", message: "" },
      games: [],
      catalogLoading: false,
      campaignError: "",
      membersError: "",
      statuses: ["active", "paused", "archived"],
      roles: ["gm", "assistant", "player", "observer"],
      toggles: [
        {
          key: "allowPlayerDrawing",
          labelKey: "vtt.table.settings.rules.allowDrawing",
        },
        {
          key: "allowPlayerTokenMovement",
          labelKey: "vtt.table.settings.rules.allowMovement",
        },
        {
          key: "autoOpenLastScene",
          labelKey: "vtt.table.settings.rules.autoScene",
        },
        {
          key: "showPlayerCursors",
          labelKey: "vtt.table.settings.rules.showCursors",
        },
      ],
    };
  },
  computed: {
    tabs() {
      const tabs = [];
      if (this.canManage) {
        tabs.push(
          { id: "campaign", labelKey: "vtt.table.settings.tabs.campaign" },
          { id: "members", labelKey: "vtt.table.settings.tabs.members" },
        );
      }
      tabs.push({ id: "audio", labelKey: "vtt.table.settings.tabs.audio" });
      return tabs;
    },
    campaignBusy() {
      return Boolean(this.$store.state.campaignContext?.pendingRequests);
    },
    systems() {
      return systemsFromGames(this.games);
    },
    worlds() {
      return worldsForSystem(this.games, this.draft.systemId);
    },
    pendingInvitations() {
      return this.invitations.filter((item) => item.status === "pending");
    },
    activeError() {
      return this.activeTab === "members"
        ? this.membersError
        : this.campaignError;
    },
  },
  watch: {
    campaign: {
      deep: true,
      handler(value) {
        this.draft = campaignSettingsDraft(value);
      },
    },
    activeTab(value) {
      if (value === "campaign") this.loadCatalog();
    },
    canManage(value) {
      if (!value && this.activeTab !== "audio") this.activeTab = "audio";
    },
  },
  mounted() {
    if (this.activeTab === "campaign") this.loadCatalog();
  },
  methods: {
    message(error) {
      if (error?.network) return this.$t("vtt.table.settings.errors.network");
      if (error?.status === 403)
        return this.$t("vtt.table.settings.errors.forbidden");
      if (error?.status === 429)
        return this.$t("vtt.table.settings.errors.rateLimited");
      return this.$t("vtt.table.settings.errors.generic");
    },
    async loadCatalog() {
      if (!this.canManage || this.games.length || this.catalogLoading) return;
      this.catalogLoading = true;
      this.campaignError = "";
      try {
        this.games = await gameCatalogApiClient.listGames();
      } catch (error) {
        this.campaignError = this.message(error);
      } finally {
        this.catalogLoading = false;
      }
    },
    selectSystem() {
      if (!this.worlds.some((world) => world.id === this.draft.universeId)) {
        this.draft.universeId = this.worlds[0]?.id || null;
      }
    },
    async run(action, payload, errorField) {
      this[errorField] = "";
      try {
        return await this.$store.dispatch(`campaignContext/${action}`, payload);
      } catch (error) {
        this[errorField] = this.message(error);
        return null;
      }
    },
    saveCampaign() {
      return this.run(
        "updateSettings",
        { ...this.draft, settings: { ...this.draft.settings } },
        "campaignError",
      );
    },
    async invite() {
      const result = await this.run(
        "invite",
        { ...this.inviteDraft },
        "membersError",
      );
      if (!result) return;
      this.inviteDraft.identifier = "";
      this.inviteDraft.message = "";
    },
    changeRole(userId, role) {
      return this.run("changeMemberRole", { userId, role }, "membersError");
    },
    removeMember(userId) {
      return this.run("removeMember", userId, "membersError");
    },
    revokeInvitation(invitationId) {
      return this.run("revokeInvitation", invitationId, "membersError");
    },
    isOwner(member) {
      return Number(member.userId) === Number(this.campaign.gameMasterId);
    },
    memberName(member) {
      return member.username || member.email || `#${member.userId}`;
    },
    invitationName(invitation) {
      return (
        invitation.invitee?.username ||
        invitation.invitee?.email ||
        `#${invitation.id}`
      );
    },
  },
};
</script>

<style scoped src="./table-settings.css"></style>
