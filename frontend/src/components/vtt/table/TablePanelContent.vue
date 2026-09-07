<template>
  <CampaignChatPanel
    v-if="panelId === 'chat'"
    :id="`campaign-chat-${instanceId}`"
    :campaign-id="campaignId"
    :can-create-token="canCreateToken"
    embedded
  />

  <SceneManagerPanel
    v-else-if="panelId === 'scenes'"
    :scene="selectedScene"
    :scenes="scenes"
    :selected-id="selectedId"
    :active-id="activeId"
    :can-manage="canManage"
    :busy="busy"
    @select="$emit('select-scene', $event)"
    @create="$emit('create-scene')"
    @duplicate="$emit('duplicate-scene')"
    @edit="$emit('edit-scene')"
    @delete="$emit('delete-scene')"
    @activate="$emit('activate-scene')"
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

  <TableCharacterPanel
    v-else-if="panelId === 'characters'"
    :campaign-id="campaignId"
    :campaign-data="campaign"
    :compact="instanceId === 'drawer'"
    :can-create-token="canCreateToken"
    :can-select-for-hud="canManage"
    :can-manage-groups="canManage"
    :initial-character-id="characterId"
    :selected-hud-character-id="hudCharacterId"
    @changed="$emit('character-changed', $event)"
    @select-for-hud="$emit('select-character', $event)"
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
    :can-manage="canManage"
    :can-resolve="canResolveMovement"
    :busy="movementRequestBusy"
    @resolve="$emit('resolve-movement-request', $event)"
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
import SceneManagerPanel from "@/components/vtt/scene/SceneManagerPanel.vue";
import TableContextPanel from "./TableContextPanel.vue";
import TableMovementRequestsPanel from "./TableMovementRequestsPanel.vue";
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
      /* webpackChunkName: "table-compendium" */ "../compendium/CompendiumWorkspace.vue"
    ),
);
export default {
  name: "TablePanelContent",
  components: {
    CampaignChatPanel,
    SceneManagerPanel,
    TableCharacterPanel,
    TableCombatPanel,
    HandoutWorkspace,
    HandoutWindow,
    CompendiumWorkspace,
    TableContextPanel,
    TableMovementRequestsPanel,
    TableShopPanel,
  },
  props: {
    panelId: { type: String, required: true },
    instanceId: { type: String, default: "drawer" },
    campaignId: { type: [Number, String], required: true },
    campaign: { type: Object, default: () => ({}) },
    characterId: { type: [Number, String], default: null },
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
    canManage: { type: Boolean, default: false },
    canOpenShop: { type: Boolean, default: false },
    canCreateToken: { type: Boolean, default: false },
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
    "character-changed",
    "select-character",
    "open-window",
    "resolve-movement-request",
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
  },
};
</script>
