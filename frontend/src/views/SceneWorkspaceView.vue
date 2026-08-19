<template>
  <main class="scene-workspace">
    <section
      v-if="!moduleReady || initialLoading"
      class="scene-workspace__state"
      role="status"
    >
      <h1>{{ $t("vtt.scene.workspace.title") }}</h1>
      <p>{{ $t("vtt.scene.workspace.loading") }}</p>
    </section>

    <section
      v-else-if="state.unauthorized"
      class="scene-workspace__state"
      role="alert"
    >
      <h1>{{ $t("vtt.scene.workspace.unauthorizedTitle") }}</h1>
      <p>{{ $t("vtt.scene.workspace.unauthorizedBody") }}</p>
    </section>

    <div
      v-else
      class="scene-workspace__layout"
      :class="{ 'scene-workspace__layout--drawer': drawerOpen }"
    >
      <header class="table-workspace-header">
        <div class="table-workspace-header__identity">
          <small>{{ $t("vtt.table.header.kicker") }}</small>
          <h1>{{ campaign.name || $t("vtt.scene.workspace.title") }}</h1>
          <span v-if="selectedScene">{{ selectedScene.name }}</span>
        </div>
        <div class="table-workspace-header__status">
          <span
            class="presence-dot"
            :class="{ online: realtime.status === 'ready' }"
          ></span>
          <span>{{
            $t(`campaignLobby.connection.${realtime.status || "disconnected"}`)
          }}</span>
          <span>{{
            $t("vtt.table.header.online", { count: onlineMembers.length })
          }}</span>
        </div>
        <router-link class="scene-button" :to="{ name: 'tables' }">
          {{ $t("vtt.table.header.switch") }}
        </router-link>
      </header>

      <GmToolPanel
        :scenes="scenes"
        :selected-id="state.selectedSceneId"
        :active-id="state.activeSceneId"
        :can-manage="canManage"
        :busy="busy"
        @select="selectScene"
        @create="openCreate"
        @edit="openEdit"
        @characters="selectUtility('characters')"
      />

      <section class="scene-workspace__main">
        <div v-if="state.error" class="scene-workspace__notice" role="alert">
          <span>{{ errorMessage }}</span>
          <button type="button" class="scene-button" @click="refresh">
            {{ $t("vtt.scene.actions.retry") }}
          </button>
        </div>
        <SceneToolbar
          :scene="selectedScene"
          :is-active="selectedScene?.id === state.activeSceneId"
          :can-manage="canManage"
          :busy="busy"
          :zoom-percent="zoomPercent"
          @zoom-out="zoomOut"
          @zoom-in="zoomIn"
          @fit="fitCanvas"
          @refresh="refresh"
          @activate="activate"
          @settings="openEdit"
        />
        <SceneCanvas
          :key="
            selectedScene
              ? `${selectedScene.id}:${selectedScene.revision}`
              : 'empty'
          "
          ref="canvas"
          :scene="selectedScene"
          @camera-change="zoomPercent = $event.zoomPercent"
        />
      </section>

      <SceneSettingsPanel
        v-if="settingsOpen && canManage"
        :scene="settingsMode === 'edit' ? selectedScene : null"
        :mode="settingsMode"
        :busy="busy"
        @save="saveSettings"
        @cancel="settingsOpen = false"
        @delete="requestDelete"
      />

      <TableUtilityDrawer
        v-else-if="activeUtility"
        :title="$t(activeUtility.labelKey)"
        :panel-id="activeUtility.id"
        @close="activePanelId = ''"
      >
        <CampaignChatPanel
          v-if="activePanelId === 'chat'"
          id="campaign-chat"
          :campaign-id="currentCampaignId"
          embedded
        />
        <TableContextPanel
          v-else
          :panel-id="activePanelId"
          :campaign="campaign"
          :scenes="scenes"
          :characters="characters"
          :members="members"
          :invitations="invitations"
          :realtime-status="realtime.status"
          :can-manage="canManage"
          :can-open-shop="canOpenShop"
        />
      </TableUtilityDrawer>

      <TableUtilityRail :active-id="activePanelId" @select="selectUtility" />
    </div>

    <UiConfirmDialog
      v-model="confirmDeleteOpen"
      :title="$t('vtt.scene.actions.delete')"
      :description="$t('vtt.scene.actions.deleteConfirm')"
      :confirm-label="$t('vtt.scene.actions.delete')"
      :cancel-label="$t('vtt.scene.actions.cancel')"
      :busy="busy"
      danger
      @confirm="deleteScene"
      @cancel="confirmDeleteOpen = false"
    />
  </main>
</template>

<script>
import options from "./options/SceneWorkspaceView.options";

export default options;
</script>

<style src="@/components/vtt/scene/scene-workspace.css"></style>
