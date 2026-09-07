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
        <div
          v-if="state.error"
          class="scene-workspace__notice"
          :class="{
            'scene-workspace__notice--movement': isMovementNotice,
          }"
          role="alert"
        >
          <span class="scene-workspace__notice-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M12 3 2.8 20h18.4L12 3Z" />
              <path d="M12 9v5m0 3h.01" />
            </svg>
          </span>
          <span class="scene-workspace__notice-copy">
            <strong v-if="isMovementNotice">
              {{ movementNoticeTitle }}
            </strong>
            <span>{{ errorMessage }}</span>
          </span>
          <button
            v-if="isMovementNotice"
            type="button"
            class="scene-button"
            @click="dismissSceneNotice"
          >
            {{ $t("vtt.scene.actions.acknowledge") }}
          </button>
          <button v-else type="button" class="scene-button" @click="refresh">
            {{ $t("vtt.scene.actions.retry") }}
          </button>
        </div>
        <div
          v-else-if="playerShopError"
          class="scene-workspace__notice"
          role="alert"
        >
          <span class="scene-workspace__notice-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M12 3 2.8 20h18.4L12 3Z" />
              <path d="M12 9v5m0 3h.01" />
            </svg>
          </span>
          <span class="scene-workspace__notice-copy">
            <span>{{ playerShopError }}</span>
          </span>
          <button
            type="button"
            class="scene-button"
            @click="playerShopError = ''"
          >
            {{ $t("vtt.scene.actions.acknowledge") }}
          </button>
        </div>
        <div class="scene-workspace__top-tools">
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
          <div
            v-if="activeSceneTool === 'walls' && canManageWalls"
            id="scene-wall-toolbar-dock"
            class="scene-wall-toolbar-dock"
          />
        </div>
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
          :active-turn-id="activeCombatTokenId"
          :waiting-turn-ids="waitingCombatTokenIds"
          :members="members"
          :characters="characters"
          :token-busy="tokenBusy"
          :can-create-token="canCreateToken"
          :walls="selectedSceneWalls"
          :selected-wall-id="state.selectedWallId"
          :can-manage-walls="canManageWalls"
          :wall-busy="wallBusy"
          wall-toolbar-target="#scene-wall-toolbar-dock"
          :lights="selectedSceneLights"
          :selected-light-id="state.selectedLightId"
          :can-manage-lights="canManageLights"
          :light-busy="lightBusy"
          :tiles="selectedSceneTiles"
          :selected-tile-id="state.selectedTileId"
          :can-manage-tiles="canManageTiles"
          :tile-busy="tileBusy"
          :can-manage-scene="canManage"
          :fog-state="selectedFogState"
          :fog-preview="fogPreview"
          :fog-busy="fogBusy"
          @camera-change="handleCameraChange"
          @token-select="selectToken"
          @token-move="moveToken"
          @token-move-group="moveTokenGroup"
          @token-movement-depleted="blockDepletedTokenMovement"
          @token-movement-limit="requestTokenMovement"
          @token-update="updateToken"
          @token-target="toggleTokenTarget"
          @token-delete="deleteToken"
          @token-create="createToken"
          @open-actor="openActor"
          @wall-select="selectWall"
          @wall-create="createWall"
          @wall-create-many="createWalls"
          @wall-insert-opening="insertWallOpening"
          @wall-update="updateWall"
          @wall-update-many="updateWalls"
          @wall-delete="deleteWall"
          @light-select="selectLight"
          @light-create="createLight"
          @light-update="updateLight"
          @light-delete="deleteLight"
          @tile-select="selectTile"
          @tile-create="createTile"
          @tile-update="updateTile"
          @tile-delete="deleteTile"
          @fog-patch="patchFog"
          @fog-preview-change="changeFogPreview"
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
          v-bind="tablePanelContext"
          @select-scene="selectScene"
          @create-scene="openCreate"
          @duplicate-scene="duplicateScene"
          @edit-scene="openEdit"
          @delete-scene="requestDelete"
          @activate-scene="activate"
          @character-changed="refreshCampaignContext"
          @select-character="selectHudCharacter"
          @open-window="openUtilityWindow"
          @resolve-movement-request="resolveTokenMovement"
          @combat-command="commandCombat"
          @handout-unread-count="handoutUnreadCount = $event"
          @open-handout="openHandoutWindow"
        />
      </TableUtilityDrawer>

      <PlayerCharacterHud
        :campaign-id="currentCampaignId"
        :characters="characters"
        :focused-character-id="playerHudCharacterId"
        :tokens="selectedSceneTokens"
        :can-manage="playerHudCanManage"
        :can-open-shop="canOpenShop"
        :shop-busy="playerShopOpening"
        :context-phase="campaignContext.phase || 'idle'"
        :context-generation="campaignContext.generation || 0"
        :context-unauthorized="campaignContext.unauthorized === true"
        :context-error="campaignContext.error || null"
        @open-character="openPlayerCharacter"
        @fit-map="fitCanvas"
        @open-shop="openPlayerShop"
        @open-dice="openPlayerDice"
        @open-settings="openPlayerSettings"
        @open-window="openUtilityWindow"
      />

      <TableUtilityRail
        :active-id="activePanelId"
        :available-ids="availableUtilityIds"
        :badges="{
          notifications:
            state.movementRequests.filter(
              (request) => request.status === 'pending',
            ).length + handoutUnreadCount,
          handouts: handoutUnreadCount,
        }"
        @select="selectUtility"
        @open="openUtilityWindow"
      />

      <TableFloatingWindow
        v-for="panelWindow in panelWindows"
        :key="panelWindow.id"
        :model="panelWindow"
        :title="panelWindow.title || $t(panelWindow.labelKey)"
        :icon="panelWindow.icon"
        @move="moveUtilityWindow"
        @focus="focusUtilityWindow"
        @minimize="toggleUtilityWindow"
        @close="closeUtilityWindow"
      >
        <TablePanelContent
          :panel-id="panelWindow.panelId"
          :instance-id="panelWindow.id"
          :handout-scope="panelWindow.handoutScope || ''"
          :handout-id="panelWindow.handoutId || null"
          :handout-start-editing="panelWindow.startEditing === true"
          v-bind="tablePanelContext"
          @select-scene="selectScene"
          @create-scene="openCreate"
          @duplicate-scene="duplicateScene"
          @edit-scene="openEdit"
          @delete-scene="requestDelete"
          @activate-scene="activate"
          @character-changed="refreshCampaignContext"
          @select-character="selectHudCharacter"
          @open-window="openUtilityWindow"
          @resolve-movement-request="resolveTokenMovement"
          @combat-command="commandCombat"
          @handout-unread-count="handoutUnreadCount = $event"
          @open-handout="openHandoutWindow"
          @handout-window-update="updateHandoutWindow"
          @close-window="closeUtilityWindow"
        />
      </TableFloatingWindow>
    </div>

    <component
      :is="playerShopComponent"
      v-if="playerShopMounted"
      ref="playerShopModal"
    />

    <PlayerCharacterStatsModal
      ref="playerCharacterStatsModal"
      :campaign-id="currentCampaignId"
      :character-id="playerCharacterStatsId"
    />

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
