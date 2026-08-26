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
      <TableWorkspaceHeader
        :campaign="campaign"
        :scene="selectedScene"
        :scenes="scenes"
        :selected-id="state.selectedSceneId"
        :active-id="state.activeSceneId"
        :online-members="onlineMembers"
        :realtime-status="realtime.status || 'disconnected'"
        :can-manage="canManage"
        :busy="busy"
        @select-scene="selectScene"
        @previous-scene="selectRelativeScene(-1)"
        @next-scene="selectRelativeScene(1)"
        @activate="activate"
      />

      <TableToolRail
        :active-id="activeSceneTool"
        :can-manage="canManage"
        @select="selectSceneTool"
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
          :active-tool="activeSceneTool"
          :tokens="selectedSceneTokens"
          :selected-token-id="state.selectedTokenId"
          :selected-token-ids="state.selectedTokenIds"
          :targeted-token-ids="state.targetedTokenIds"
          :members="members"
          :characters="characters"
          :token-busy="tokenBusy"
          :can-create-token="canCreateToken"
          :walls="selectedSceneWalls"
          :selected-wall-id="state.selectedWallId"
          :can-manage-walls="canManageWalls"
          :wall-busy="wallBusy"
          :lights="selectedSceneLights"
          :selected-light-id="state.selectedLightId"
          :can-manage-lights="canManageLights"
          :light-busy="lightBusy"
          :tiles="selectedSceneTiles"
          :selected-tile-id="state.selectedTileId"
          :can-manage-tiles="canManageTiles"
          :tile-busy="tileBusy"
          @camera-change="zoomPercent = $event.zoomPercent"
          @token-select="selectToken"
          @token-move="moveToken"
          @token-movement-limit="requestTokenMovement"
          @token-update="updateToken"
          @token-target="toggleTokenTarget"
          @token-delete="deleteToken"
          @token-create="createToken"
          @open-actor="openActor"
          @wall-select="selectWall"
          @wall-create="createWall"
          @wall-update="updateWall"
          @wall-delete="deleteWall"
          @light-select="selectLight"
          @light-create="createLight"
          @light-update="updateLight"
          @light-delete="deleteLight"
          @tile-select="selectTile"
          @tile-create="createTile"
          @tile-update="updateTile"
          @tile-delete="deleteTile"
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
        <TablePanelContent
          :panel-id="activePanelId"
          instance-id="drawer"
          :campaign-id="currentCampaignId"
          :campaign="campaign"
          :scenes="scenes"
          :selected-id="state.selectedSceneId"
          :active-id="state.activeSceneId"
          :characters="characters"
          :members="members"
          :invitations="invitations"
          :movement-requests="state.movementRequests"
          :realtime-status="realtime.status"
          :can-manage="canManage"
          :can-open-shop="canOpenShop"
          :can-create-token="canCreateToken"
          :can-resolve-movement="state.movementRequestCapabilities.canResolve"
          :movement-request-busy="state.movementRequestPhase === 'saving'"
          :character-id="focusedCharacterId"
          :busy="busy"
          @select-scene="selectScene"
          @create-scene="openCreate"
          @duplicate-scene="duplicateScene"
          @edit-scene="openEdit"
          @delete-scene="requestDelete"
          @activate-scene="activate"
          @character-changed="refreshCampaignContext"
          @open-window="openUtilityWindow"
          @resolve-movement-request="resolveTokenMovement"
        />
      </TableUtilityDrawer>

      <TableHotbar
        :actions="hotbarActions"
        :default-action-ids="defaultHotbarActions"
        :storage-key="hotbarStorageKey"
        @activate="runHotbarAction"
      />

      <TableUtilityRail
        :active-id="activePanelId"
        :available-ids="availableUtilityIds"
        :badges="{
          notifications: state.movementRequests.filter(
            (request) => request.status === 'pending',
          ).length,
        }"
        @select="selectUtility"
        @open="openUtilityWindow"
      />

      <TableFloatingWindow
        v-for="panelWindow in panelWindows"
        :key="panelWindow.id"
        :model="panelWindow"
        :title="$t(panelWindow.labelKey)"
        :icon="panelWindow.icon"
        @move="moveUtilityWindow"
        @focus="focusUtilityWindow"
        @minimize="toggleUtilityWindow"
        @close="closeUtilityWindow"
      >
        <TablePanelContent
          :panel-id="panelWindow.panelId"
          :instance-id="panelWindow.id"
          :campaign-id="currentCampaignId"
          :campaign="campaign"
          :scenes="scenes"
          :selected-id="state.selectedSceneId"
          :active-id="state.activeSceneId"
          :characters="characters"
          :members="members"
          :invitations="invitations"
          :movement-requests="state.movementRequests"
          :realtime-status="realtime.status"
          :can-manage="canManage"
          :can-open-shop="canOpenShop"
          :can-create-token="canCreateToken"
          :can-resolve-movement="state.movementRequestCapabilities.canResolve"
          :movement-request-busy="state.movementRequestPhase === 'saving'"
          :character-id="focusedCharacterId"
          :busy="busy"
          @select-scene="selectScene"
          @create-scene="openCreate"
          @duplicate-scene="duplicateScene"
          @edit-scene="openEdit"
          @delete-scene="requestDelete"
          @activate-scene="activate"
          @character-changed="refreshCampaignContext"
          @open-window="openUtilityWindow"
          @resolve-movement-request="resolveTokenMovement"
        />
      </TableFloatingWindow>
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
