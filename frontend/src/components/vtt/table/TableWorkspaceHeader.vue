<template>
  <header class="table-workspace-header">
    <router-link class="table-workspace-header__brand" :to="{ name: 'tables' }">
      BR
    </router-link>

    <div class="table-workspace-header__identity">
      <small>{{ $t("vtt.table.header.kicker") }}</small>
      <strong>{{ campaign.name || $t("vtt.scene.workspace.title") }}</strong>
      <span aria-hidden="true">/</span>
      <span>{{ scene?.name || $t("vtt.scene.workspace.noSceneShort") }}</span>
    </div>

    <div class="table-workspace-header__scenes">
      <button
        type="button"
        :disabled="busy || scenes.length < 2"
        :title="$t('vtt.table.header.previousScene')"
        @click="$emit('previous-scene')"
      >
        ‹
      </button>
      <select
        :value="selectedId ?? ''"
        :aria-label="$t('vtt.scene.navigation.label')"
        :disabled="busy || !scenes.length"
        @change="$emit('select-scene', $event.target.value)"
      >
        <option v-for="item in scenes" :key="item.id" :value="item.id">
          {{ item.name }}{{ item.id === activeId ? activeSuffix : "" }}
        </option>
      </select>
      <button
        type="button"
        :disabled="busy || scenes.length < 2"
        :title="$t('vtt.table.header.nextScene')"
        @click="$emit('next-scene')"
      >
        ›
      </button>
      <button
        v-if="canManage && scene && scene.id !== activeId"
        type="button"
        class="table-workspace-header__activate"
        :disabled="busy || !scene.isVisible"
        :title="$t('vtt.scene.actions.activate')"
        @click="$emit('activate')"
      >
        ▶
      </button>
    </div>

    <div class="table-workspace-header__presence">
      <span
        class="presence-dot"
        :class="{ online: realtimeStatus === 'ready' }"
      ></span>
      <span>{{ $t(`campaignLobby.connection.${realtimeStatus}`) }}</span>
      <span class="table-workspace-header__members">
        {{ $t("vtt.table.header.online", { count: onlineMembers.length }) }}
      </span>
    </div>

    <button
      v-if="canManage"
      class="table-workspace-header__pause"
      type="button"
      disabled
      :title="$t('vtt.table.header.pauseUnavailable')"
    >
      ‖
      <span>{{ $t("vtt.table.header.pause") }}</span>
    </button>
  </header>
</template>

<script>
export default {
  name: "TableWorkspaceHeader",
  props: {
    campaign: { type: Object, default: () => ({}) },
    scene: { type: Object, default: null },
    scenes: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    activeId: { type: [Number, String], default: null },
    onlineMembers: { type: Array, default: () => [] },
    realtimeStatus: { type: String, default: "disconnected" },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["select-scene", "previous-scene", "next-scene", "activate"],
  computed: {
    activeSuffix() {
      return ` · ${this.$t("vtt.scene.status.active")}`;
    },
  },
};
</script>
