<template>
  <main class="scene-workspace">
    <SceneLoadingScreen
      v-if="!moduleReady"
      :loading="sceneLoading"
      :scene-name="loaderSceneName"
      :campaign-name="loaderCampaignName"
      :campaign-image="loaderCampaignImage"
      page
    />

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
      :class="{
        'scene-workspace__layout--drawer': drawerOpen,
        'scene-workspace__layout--dice': diceOverlayOpen,
      }"
    >
      <SceneLoadingScreen
        v-if="sceneLoading.active"
        :loading="sceneLoading"
        :scene-name="loaderSceneName"
        :campaign-name="loaderCampaignName"
        :campaign-image="loaderCampaignImage"
      />
      <TableWorkspaceHeader
        :campaign="campaign"
        :scene="selectedScene"
        :scenes="scenes"
        :selected-id="state.selectedSceneId"
        :active-id="state.activeSceneId"
        :online-members="onlineMembers"
        :realtime-status="realtime.status || 'disconnected'"
        :calendar-date="calendarCurrentDate"
        :calendar-time="calendarCurrentTime"
        :can-manage="canManage"
        :busy="busy"
        @select-scene="selectScene"
        @previous-scene="selectRelativeScene(-1)"
        @next-scene="selectRelativeScene(1)"
        @activate="activate"
        @open-calendar="openUtilityWindow('calendar')"
      />

      <TableToolRail
        v-show="!diceOverlayOpen"
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
          :current-user-id="currentUserId"
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
          :regions="selectedSceneRegions"
          :selected-region-id="state.selectedRegionId"
          :can-manage-regions="canManageRegions"
          :region-busy="regionBusy"
          :tiles="selectedSceneTiles"
          :selected-tile-id="state.selectedTileId"
          :can-manage-tiles="canManageTiles"
          :tile-busy="tileBusy"
          :can-manage-scene="canManage"
          :fog-state="selectedFogState"
          :fog-preview="fogPreview"
          :fog-busy="fogBusy"
          :token-sync-links="state.tokenSyncCatalog?.links || []"
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
          @token-template-create="placeTokenTemplate"
          @open-actor="openActor"
          @assign-character="openTokenCharacterAssignment"
          @wall-select="selectWall"
          @wall-create="createWall"
          @wall-create-many="createWalls"
          @wall-insert-opening="insertWallOpening"
          @wall-update="updateWall"
          @wall-update-many="updateWalls"
          @wall-delete="deleteWall"
          @wall-interact="interactWall"
          @wall-edit="editWallProperties"
          @light-select="selectLight"
          @light-create="createLight"
          @light-update="updateLight"
          @light-delete="deleteLight"
          @region-select="selectRegion"
          @region-create="createRegion"
          @region-update="updateRegion"
          @region-delete="deleteRegion"
          @tile-select="selectTile"
          @tile-create="createTile"
          @tile-update="updateTile"
          @tile-delete="deleteTile"
          @fog-patch="patchFog"
          @fog-preview-change="changeFogPreview"
        />
        <SceneDiceOverlay
          v-if="diceOverlayMounted"
          :visible="diceOverlayOpen"
          :mode="diceOverlayMode"
          :roll-request="diceRollRequest"
          @close="closePlayerDice"
          @roll-complete="publishDiceRoll"
        />
      </section>

      <TableUtilityDrawer
        v-if="activeUtility"
        v-show="!diceOverlayOpen"
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
          @token-sync="openTokenSyncFromScenes"
          @place-token-template="placeTokenTemplate"
          @character-changed="refreshCampaignContext"
          @select-character="selectHudCharacter"
          @open-window="openUtilityWindow"
          @resolve-movement-request="resolveTokenMovement"
          @retry-realtime="$store.dispatch('realtime/retry')"
          @combat-command="commandCombat"
          @handout-unread-count="handoutUnreadCount = $event"
          @open-handout="openHandoutWindow"
          @create-map="openMapBuilder()"
          @edit-map="openMapBuilder()"
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
        @fit-map="fitCanvas"
        @open-dice="openPlayerDice"
        @open-dice-selector="openPlayerDiceSelector"
        @open-modal="openPlayerHudModal"
      />

      <TableUtilityRail
        v-show="!diceOverlayOpen"
        :active-id="activePanelId"
        :available-ids="availableUtilityIds"
        :badges="{
          voice: $store.state.voice?.participants?.length || 0,
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
        :title="sceneSettingsWindowTitle(panelWindow)"
        :subtitle="sceneSettingsWindowSubtitle(panelWindow)"
        :status="sceneSettingsWindowStatus(panelWindow)"
        :icon="panelWindow.icon"
        @move="moveUtilityWindow"
        @resize="resizeUtilityWindow"
        @layer-change="syncUtilityWindowLayer"
        @minimize="toggleUtilityWindow"
        @maximize="toggleMaximizeUtilityWindow"
        @close="requestCloseUtilityWindow"
      >
        <MapBuilder
          v-if="panelWindow.windowType === 'map-builder' && canManage"
          :campaign-id="panelWindow.campaignId"
          :scene-id="panelWindow.sceneId"
          :initial-map-id="panelWindow.mapId"
          @meta-change="updateMapBuilderMeta(panelWindow, $event)"
          @published="handleMapPublished"
        />
        <SceneSettingsPanel
          v-else-if="panelWindow.windowType === 'scene-settings' && canManage"
          :campaign-id="panelWindow.campaignId"
          :instance-id="panelWindow.id"
          :scene="sceneForWindow(panelWindow)"
          :scenes="scenes"
          :mode="panelWindow.mode"
          :busy="busy"
          :active="isTopWindow(panelWindow)"
          @save="saveSceneSettings(panelWindow, $event)"
          @cancel="requestCloseUtilityWindow(panelWindow.id)"
          @delete="requestDelete(panelWindow.sceneId)"
          @clear-fog="clearSceneFog(panelWindow, $event)"
          @transition-darkness="transitionSceneDarkness(panelWindow, $event)"
          @dirty-change="updateSceneWindowState(panelWindow, { dirty: $event })"
          @status-change="
            updateSceneWindowState(panelWindow, { status: $event })
          "
          @title-change="
            updateSceneWindowState(panelWindow, { sceneName: $event })
          "
        />
        <HeroSpellbookContent
          v-else-if="panelWindow.windowType === 'magic'"
          :campaign-id="panelWindow.campaignId"
          :character-id="panelWindow.characterId"
          :initial-spell-id="panelWindow.initialSpellId"
        />
        <TablePanelContent
          v-else
          :panel-id="panelWindow.panelId"
          :instance-id="panelWindow.id"
          :handout-scope="panelWindow.handoutScope || ''"
          :handout-id="panelWindow.handoutId || null"
          :handout-start-editing="panelWindow.startEditing === true"
          :initial-scene-id="panelWindow.initialSceneId || null"
          :journal-character-id="
            panelWindow.characterId || tablePanelContext.characterId
          "
          :bestiary-character-id="
            panelWindow.characterId || tablePanelContext.characterId
          "
          bestiary-variant="wood"
          v-bind="tablePanelContext"
          @select-scene="selectScene"
          @create-scene="openCreate"
          @duplicate-scene="duplicateScene"
          @edit-scene="openEdit"
          @delete-scene="requestDelete"
          @activate-scene="activate"
          @token-sync="openTokenSyncFromScenes"
          @place-token-template="placeTokenTemplate"
          @character-changed="refreshCampaignContext"
          @select-character="selectHudCharacter"
          @open-window="openUtilityWindow"
          @resolve-movement-request="resolveTokenMovement"
          @retry-realtime="$store.dispatch('realtime/retry')"
          @combat-command="commandCombat"
          @handout-unread-count="handoutUnreadCount = $event"
          @open-handout="openHandoutWindow"
          @create-map="openMapBuilder()"
          @edit-map="openMapBuilder()"
          @handout-window-update="updateHandoutWindow"
          @close-window="closeUtilityWindow"
        />
      </TableFloatingWindow>
    </div>

    <PlayerHudModalShell
      :model-value="Boolean(activePlayerHudModal && playerHudModalDefinition)"
      :title="playerHudModalTitle"
      :width="playerHudModalDefinition?.width || 720"
      :height="playerHudModalDefinition?.height || 420"
      :content-class="playerHudModalDefinition?.contentClass || ''"
      :close-label="$t('vtt.table.playerHud.modal.close')"
      @request-close="requestClosePlayerHudModal"
    >
      <PlayerCharacterStatsModal
        v-if="activePlayerHudModal?.id === 'character'"
        :campaign-id="currentCampaignId"
        :character-id="activePlayerHudModal.characterId"
      />

      <component
        :is="playerShopComponent"
        v-else-if="activePlayerHudModal?.id === 'shop' && playerShopComponent"
      />

      <HeroSpellbookContent
        v-else-if="activePlayerHudModal?.id === 'spells'"
        :campaign-id="currentCampaignId"
        :character-id="activePlayerHudModal.characterId"
        :initial-spell-id="activePlayerHudModal.initialSpellId"
      />

      <SceneSettingsPanel
        v-else-if="activePlayerHudModal?.id === 'settings' && canManage"
        :campaign-id="currentCampaignId"
        instance-id="player-hud-settings"
        :scene="sceneForWindow(activePlayerHudModal)"
        :scenes="scenes"
        mode="edit"
        :busy="busy"
        active
        @save="savePlayerHudSceneSettings"
        @cancel="requestClosePlayerHudModal('cancel')"
        @delete="requestDelete(activePlayerHudModal.sceneId)"
        @clear-fog="clearSceneFog(activePlayerHudModal, $event)"
        @transition-darkness="
          transitionSceneDarkness(activePlayerHudModal, $event)
        "
        @dirty-change="updatePlayerHudSceneSettings({ dirty: $event })"
        @status-change="updatePlayerHudSceneSettings({ status: $event })"
        @title-change="updatePlayerHudSceneSettings({ sceneName: $event })"
      />

      <TablePanelContent
        v-else-if="
          ['combat', 'journal', 'bestiary'].includes(activePlayerHudModal?.id)
        "
        :panel-id="activePlayerHudModal.id"
        instance-id="player-hud-modal"
        :journal-character-id="activePlayerHudModal.characterId"
        :bestiary-character-id="activePlayerHudModal.characterId"
        bestiary-variant="wood"
        v-bind="tablePanelContext"
        @combat-command="commandCombat"
        @retry-realtime="$store.dispatch('realtime/retry')"
      />

      <PlayerHudComingSoon
        v-else-if="playerHudModalDefinition?.placeholder"
        :title="playerHudModalTitle"
      />
    </PlayerHudModalShell>

    <TokenCharacterAssignmentDialog
      v-if="tokenCharacterAssignmentToken"
      :token="tokenCharacterAssignmentToken"
      :characters="characters"
      :busy="tokenCharacterAssignmentBusy"
      :error="tokenCharacterAssignmentError"
      :created-character-id="tokenCharacterAssignmentCreatedId"
      @close="closeTokenCharacterAssignment"
      @assign="assignTokenCharacter"
      @create="createAndAssignTokenCharacter"
      @detach="assignTokenCharacter(null)"
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
      @cancel="cancelDeleteScene"
    />

    <UiConfirmDialog
      v-model="confirmDiscardOpen"
      :title="$t('vtt.scene.settings.discardTitle')"
      :description="$t('vtt.scene.settings.discardConfirm')"
      :confirm-label="$t('vtt.scene.settings.discard')"
      :cancel-label="$t('vtt.scene.actions.cancel')"
      danger
      @confirm="discardAndCloseSceneSettings"
      @cancel="cancelDiscardSceneSettings"
    />
  </main>
</template>

<script>
import options from "./options/SceneWorkspaceView.options";

export default options;
</script>

<style src="@/components/vtt/scene/scene-workspace.css"></style>
