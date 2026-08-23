<template>
  <CampaignChatPanel
    v-if="panelId === 'chat'"
    :id="`campaign-chat-${instanceId}`"
    :campaign-id="campaignId"
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

  <TableCharacterPanel
    v-else-if="panelId === 'characters'"
    :campaign-id="campaignId"
    @changed="$emit('character-changed', $event)"
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

const TableCharacterPanel = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-characters" */ "./TableCharacterPanel.vue"
    ),
);

export default {
  name: "TablePanelContent",
  components: {
    CampaignChatPanel,
    SceneManagerPanel,
    TableCharacterPanel,
    TableContextPanel,
  },
  props: {
    panelId: { type: String, required: true },
    instanceId: { type: String, default: "drawer" },
    campaignId: { type: [Number, String], required: true },
    campaign: { type: Object, default: () => ({}) },
    scenes: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    activeId: { type: [Number, String], default: null },
    characters: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    realtimeStatus: { type: String, default: "disconnected" },
    canManage: { type: Boolean, default: false },
    canOpenShop: { type: Boolean, default: false },
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
  ],
  computed: {
    selectedScene() {
      return this.scenes.find((scene) => scene.id === this.selectedId) || null;
    },
  },
};
</script>
