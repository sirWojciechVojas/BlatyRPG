<template>
  <div class="admin-page">
    <header class="admin-header">
      <div class="admin-heading">
        <span class="admin-mark"><AdminIcon name="shield" /></span>
        <div>
          <p>{{ $t("admin.eyebrow") }}</p>
          <h1>{{ $t("admin.title") }}</h1>
        </div>
      </div>
      <div class="admin-header__actions">
        <span v-if="system.generatedAt" class="admin-sync">
          {{ $t("admin.system.updated") }} {{ formatTime(system.generatedAt) }}
        </span>
        <button
          class="admin-icon-button"
          type="button"
          :title="$t('admin.actions.refresh')"
          @click="load"
        >
          <AdminIcon name="refresh" />
        </button>
        <router-link class="admin-secondary" :to="{ name: 'home' }">{{
          $t("admin.actions.back")
        }}</router-link>
      </div>
    </header>

    <div class="admin-shell">
      <aside
        class="admin-tabs nav flex-column align-items-stretch gap-1 p-1"
        :aria-label="$t('admin.tabs.label')"
      >
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="nav-link text-start"
          :class="{ active: activeTab === tab.id }"
          :aria-current="activeTab === tab.id ? 'page' : undefined"
          @click="activeTab = tab.id"
        >
          <span aria-hidden="true"><AdminIcon :name="tab.icon" /></span>
          <strong>{{ tab.label }}</strong>
          <small v-if="tab.count !== null">{{ tab.count }}</small>
        </button>
      </aside>

      <main
        class="admin-main"
        :class="{
          'ps-0':
            activeTab === 'users' ||
            activeTab === 'characters' ||
            activeTab === 'compendium' ||
            activeTab === 'professions' ||
            activeTab === 'tokenTemplates' ||
            activeTab === 'assets',
          'admin-main--contained':
            activeTab === 'compendium' ||
            activeTab === 'professions' ||
            activeTab === 'tokenTemplates' ||
            activeTab === 'assets',
        }"
      >
        <p v-if="error" class="admin-alert error" role="alert">
          {{ error }}
          <button type="button" @click="load">
            {{ $t("admin.actions.retry") }}
          </button>
        </p>
        <div v-if="loading" class="admin-loading" aria-live="polite">
          <i></i>{{ $t("admin.loading") }}
        </div>
        <template v-else>
          <AdminOverviewTab
            v-if="activeTab === 'overview'"
            :metrics="metrics"
            :analytics="analytics"
            @navigate="activeTab = $event"
          />
          <AdminUsersTab
            v-else-if="activeTab === 'users'"
            ref="usersTab"
            :users="users"
            :current-user-id="currentUserId"
            :busy-user-id="busyUserId"
            :creating="creating"
            :create-error="createError"
            :create-field-errors="createFieldErrors"
            :role-error="roleError"
            @create="createUser"
            @field-change="clearCreateFieldError"
            @role-change="changeRole"
          />
          <AdminCampaignsTab
            v-else-if="activeTab === 'campaigns'"
            :campaigns="campaigns"
            :analytics="analytics"
          />
          <AdminCharactersTab
            v-else-if="activeTab === 'characters'"
            :characters="characters"
            :campaigns="campaigns"
            :character-campaigns="characterCampaigns"
            :character-owners="characterOwners"
            :game-masters="characterGameMasters"
            :busy-key="busyAssignmentKey"
            :error="characterError"
            @campaign-change="setCharacterCampaign"
            @owner-change="setCharacterOwner"
          />
          <AdminCompendiumTab
            v-else-if="activeTab === 'compendium'"
            @loaded="compendiumCount = $event.entries"
          />
          <AdminProfessionsTab
            v-else-if="activeTab === 'professions'"
            @loaded="professionCount = $event"
          />
          <AdminAudioTab
            v-else-if="activeTab === 'audio'"
            @loaded="audioCount = $event.tracks"
          />
          <AdminTokenTemplatesTab
            v-else-if="activeTab === 'tokenTemplates'"
            @loaded="tokenTemplateCount = $event"
          />
          <AdminAssetsTab
            v-else-if="activeTab === 'assets'"
            @loaded="assetCount = $event.assets"
          />
          <AdminActivityTab
            v-else-if="activeTab === 'activity'"
            :activity="activity"
            :analytics="analytics"
          />
          <AdminSystemTab v-else :system="system" />
        </template>
      </main>
    </div>
  </div>
</template>

<script src="./options/AdminView.options.js"></script>
<style src="./styles/AdminView.css"></style>
<style src="./styles/AdminTables.css"></style>
<style src="./styles/AdminOperations.css"></style>
<style src="./styles/AdminCharacters.css"></style>
<style src="./styles/AdminCompendium.css"></style>
<style src="./styles/AdminProfessions.css"></style>
<style src="./styles/AdminAudio.css"></style>
<style src="./styles/AdminTokenTemplates.css"></style>
<style src="./styles/AdminAssets.css"></style>
