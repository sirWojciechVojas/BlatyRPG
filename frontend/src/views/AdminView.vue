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
      <aside class="admin-tabs" :aria-label="$t('admin.tabs.label')">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          :class="{ active: activeTab === tab.id }"
          :aria-current="activeTab === tab.id ? 'page' : undefined"
          @click="activeTab = tab.id"
        >
          <span aria-hidden="true"><AdminIcon :name="tab.icon" /></span>
          <strong>{{ tab.label }}</strong>
          <small v-if="tab.count !== null">{{ tab.count }}</small>
        </button>
      </aside>

      <main class="admin-main">
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
            :role-error="roleError"
            @create="createUser"
            @role-change="changeRole"
          />
          <AdminCampaignsTab
            v-else-if="activeTab === 'campaigns'"
            :campaigns="campaigns"
            :analytics="analytics"
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
