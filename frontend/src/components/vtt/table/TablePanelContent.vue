<template>
  <CampaignChatPanel
    v-if="panelId === 'chat'"
    :id="`campaign-chat-${instanceId}`"
    :campaign-id="campaignId"
    :can-create-token="canCreateToken"
    :members="members"
    embedded
  />

  <SceneManagerPanel
    v-else-if="panelId === 'scenes'"
    :scene="selectedScene"
    :scenes="scenes"
    :selected-id="selectedId"
    :active-id="activeId"
    :can-manage="canManage"
    :can-manage-token-sync="canManageTokenSync"
    :busy="busy"
    @select="$emit('select-scene', $event)"
    @create="$emit('create-scene')"
    @duplicate="$emit('duplicate-scene')"
    @edit="$emit('edit-scene')"
    @delete="$emit('delete-scene')"
    @activate="$emit('activate-scene')"
    @token-sync="$emit('token-sync', selectedId)"
    @create-map="$emit('create-map')"
    @edit-map="$emit('edit-map', selectedId)"
  />

  <TableCombatPanel
    v-else-if="panelId === 'combat'"
    :combat="combat"
    :tokens="tokens"
    :can-manage="canManageCombat"
    :busy="combatBusy"
    :error="combatError"
    @command="$emit('combat-command', $event)"
  />

  <TokenSyncPanel
    v-else-if="panelId === 'token-sync'"
    :campaign-id="campaignId"
    :initial-scene-id="initialSceneId"
  />

  <TokenTemplatePanel
    v-else-if="panelId === 'token-templates'"
    :campaign-id="campaignId"
    :busy-id="tokenTemplateBusyId"
    @place="$emit('place-token-template', $event)"
  />

  <TableCharacterPanel
    v-else-if="panelId === 'characters'"
    :campaign-id="campaignId"
    :campaign="campaign"
    :compact="instanceId === 'drawer'"
    :can-create-token="canCreateToken"
    :can-select-for-hud="true"
    :can-manage-groups="canManage"
    :can-manage-access="canManage"
    :members="members"
    :initial-character-id="characterId"
    :selected-hud-character-id="hudCharacterId"
    @changed="$emit('character-changed', $event)"
    @select-for-hud="$emit('select-character', $event)"
    @open-window="$emit('open-window', 'characters')"
  />

  <TableShopPanel
    v-else-if="panelId === 'shop'"
    :compact="instanceId === 'drawer'"
    @promote="$emit('open-window', 'shop')"
  />

  <HandoutWindow
    v-else-if="panelId === 'handout-document'"
    :campaign-id="campaignId"
    :campaign-data="campaign"
    :scope="handoutScope"
    :handout-id="handoutId"
    :start-editing="handoutStartEditing"
    :can-manage="canManage"
    :members="members"
    :scenes="scenes"
    :characters="characters"
    @changed="$emit('handout-changed', $event)"
    @close="$emit('close-window', instanceId)"
    @open-handout="$emit('open-handout', $event)"
    @window-update="
      $emit('handout-window-update', { ...$event, windowId: instanceId })
    "
  />

  <HandoutWorkspace
    v-else-if="panelId === 'handouts'"
    :compact="instanceId === 'drawer'"
    :campaign-id="campaignId"
    :campaign-data="campaign"
    :can-manage="canManage"
    :members="members"
    :scenes="scenes"
    :characters="characters"
    open-in-windows
    @unread-count="$emit('handout-unread-count', $event)"
    @open-handout="$emit('open-handout', $event)"
  />

  <CompendiumWorkspace
    v-else-if="panelId === 'compendium'"
    :campaign-id="campaignId"
    :can-see-gm-hint="canSeeCompendiumBestiary"
    :compact="instanceId === 'drawer'"
    :scene-id="selectedId"
    :token-x="tokenX"
    :token-y="tokenY"
    @materialized="$emit('character-changed', $event)"
  />

  <TableMovementRequestsPanel
    v-else-if="panelId === 'notifications'"
    :requests="movementRequests"
    :members="members"
    :invitations="invitations"
    :realtime-status="realtimeStatus"
    :manual-retry-available="manualRetryAvailable"
    :can-manage="canManage"
    :can-resolve="canResolveMovement"
    :busy="movementRequestBusy"
    @resolve="$emit('resolve-movement-request', $event)"
    @retry="$emit('retry-realtime')"
  />

  <TableSettingsPanel
    v-else-if="panelId === 'settings'"
    :campaign="campaign"
    :members="members"
    :invitations="invitations"
    :can-manage="canManage"
  />

  <VoicePanel
    v-else-if="panelId === 'voice'"
    :campaign-id="campaignId"
    @open-settings="$emit('open-window', 'settings')"
  />

  <JukeboxPanel
    v-else-if="panelId === 'jukebox'"
    :campaign-id="campaignId"
    @open-settings="$emit('open-window', 'settings')"
  />

  <SoundEffectsPanel
    v-else-if="panelId === 'sound-effects'"
    :campaign-id="campaignId"
    :members="members"
    :compact="instanceId === 'drawer'"
  />

  <JournalContent
    v-else-if="panelId === 'journal'"
    :campaign-id="campaignId"
    :character-id="journalCharacterId || characterId"
    :characters="characters"
  />

  <BestiaryContent
    v-else-if="panelId === 'bestiary'"
    :campaign-id="campaignId"
    :character-id="bestiaryCharacterId || characterId"
    :variant="bestiaryVariant"
  />

  <ProfessionsContent
    v-else-if="panelId === 'professions'"
    :campaign-id="campaignId"
    :character-id="hudCharacterId || characterId"
    :compact="instanceId === 'drawer'"
    @open-window="$emit('open-window', 'professions')"
  />

  <CampaignCalendar
    v-else-if="panelId === 'calendar'"
    :campaign-id="campaignId"
    :members="members"
  />

  <TableContextPanel
    v-else
    :panel-id="panelId"
    :campaign="campaign"
    :scenes="scenes"
    :characters="characters"
    :members="members"
    :invitations="invitations"
    :realtime-status="realtimeStatus"
    :can-manage="canManage"
    :can-open-shop="canOpenShop"
  />
</template>

<script>
import { defineAsyncComponent } from "vue";
import CampaignChatPanel from "@/components/chat/CampaignChatPanel.vue";
import CampaignCalendar from "@/components/calendar/CampaignCalendar.vue";
import SceneManagerPanel from "@/components/vtt/scene/SceneManagerPanel.vue";
import TableContextPanel from "./TableContextPanel.vue";
import TableMovementRequestsPanel from "./TableMovementRequestsPanel.vue";
import TableSettingsPanel from "./TableSettingsPanel.vue";
import TableShopPanel from "./TableShopPanel.vue";

const TableCharacterPanel = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-characters" */ "./TableCharacterPanel.vue"
    ),
);
const TableCombatPanel = defineAsyncComponent(
  () => import(/* webpackChunkName: "table-combat" */ "./TableCombatPanel.vue"),
);
const TokenSyncPanel = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-token-sync" */ "../token/TokenSyncPanel.vue"
    ),
);
const TokenTemplatePanel = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-token-templates" */ "../token/TokenTemplatePanel.vue"
    ),
);
const HandoutWorkspace = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-handouts" */ "../handout/HandoutWorkspace.vue"
    ),
);
const HandoutWindow = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-handout-document" */ "../handout/HandoutWindow.vue"
    ),
);
const CompendiumWorkspace = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-compendium", webpackPrefetch: true */ "../compendium/CompendiumWorkspace.vue"
    ),
);
const VoicePanel = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-voice" */ "@/components/audio/VoicePanel.vue"
    ),
);
const JukeboxPanel = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-jukebox" */ "@/components/audio/JukeboxPanel.vue"
    ),
);
const SoundEffectsPanel = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-sound-effects" */ "@/components/audio/SoundEffectsPanel.vue"
    ),
);
const JournalContent = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-journal" */ "../journal/JournalContent.vue"
    ),
);
const BestiaryContent = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-bestiary" */ "../bestiary/BestiaryContent.vue"
    ),
);
const ProfessionsContent = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-professions" */ "../professions/ProfessionsContent.vue"
    ),
);
export default {
  name: "TablePanelContent",
  components: {
    CampaignChatPanel,
    SceneManagerPanel,
    TableCharacterPanel,
    TableCombatPanel,
    TokenSyncPanel,
    TokenTemplatePanel,
    HandoutWorkspace,
    HandoutWindow,
    CompendiumWorkspace,
    VoicePanel,
    JukeboxPanel,
    SoundEffectsPanel,
    JournalContent,
    BestiaryContent,
    ProfessionsContent,
    CampaignCalendar,
    TableContextPanel,
    TableMovementRequestsPanel,
    TableSettingsPanel,
    TableShopPanel,
  },
  props: {
    panelId: { type: String, required: true },
    instanceId: { type: String, default: "drawer" },
    campaignId: { type: [Number, String], required: true },
    campaign: { type: Object, default: () => ({}) },
    characterId: { type: [Number, String], default: null },
    journalCharacterId: { type: [Number, String], default: null },
    bestiaryCharacterId: { type: [Number, String], default: null },
    bestiaryVariant: { type: String, default: "parchment" },
    initialSceneId: { type: [Number, String], default: null },
    handoutScope: { type: String, default: "" },
    handoutId: { type: [Number, String], default: null },
    handoutStartEditing: { type: Boolean, default: false },
    tokenX: { type: Number, default: 0 },
    tokenY: { type: Number, default: 0 },
    hudCharacterId: { type: [Number, String], default: null },
    scenes: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    activeId: { type: [Number, String], default: null },
    characters: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    movementRequests: { type: Array, default: () => [] },
    tokens: { type: Array, default: () => [] },
    combat: { type: Object, default: null },
    combatError: { type: Object, default: null },
    realtimeStatus: { type: String, default: "disconnected" },
    manualRetryAvailable: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    canManageTokenSync: { type: Boolean, default: false },
    canOpenShop: { type: Boolean, default: false },
    canCreateToken: { type: Boolean, default: false },
    tokenTemplateBusyId: { type: [Number, String], default: null },
    canResolveMovement: { type: Boolean, default: false },
    canManageCombat: { type: Boolean, default: false },
    combatBusy: { type: Boolean, default: false },
    movementRequestBusy: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: [
    "select-scene",
    "create-scene",
    "duplicate-scene",
    "edit-scene",
    "delete-scene",
    "activate-scene",
    "token-sync",
    "create-map",
    "edit-map",
    "place-token-template",
    "character-changed",
    "select-character",
    "open-window",
    "resolve-movement-request",
    "retry-realtime",
    "combat-command",
    "handout-unread-count",
    "handout-changed",
    "open-handout",
    "handout-window-update",
    "close-window",
  ],
  computed: {
    selectedScene() {
      return this.scenes.find((scene) => scene.id === this.selectedId) || null;
    },
    canSeeCompendiumBestiary() {
      const role = String(this.campaign?.campaignRole || "").toLowerCase();
      return (
        this.canManage ||
        this.canManageTokenSync ||
        this.campaign?.capabilities?.canManage === true ||
        ["gm", "game_master", "assistant"].includes(role)
      );
    },
  },
};
</script>
