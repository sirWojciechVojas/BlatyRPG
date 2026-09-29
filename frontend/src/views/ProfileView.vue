<template>
  <main class="user-panel-page" :style="styleVars">
    <section class="user-panel-shell">
      <header class="user-panel-header">
        <span class="user-panel-avatar" aria-hidden="true">
          <img
            v-if="avatarVisible"
            :src="user.avatarUrl"
            alt=""
            @error="avatarVisible = false"
          />
          <span v-else>{{ initials }}</span>
        </span>
        <div>
          <p>{{ $t("auth.userPanel.eyebrow") }}</p>
          <h1>{{ displayName }}</h1>
          <span>{{ user.email }} · {{ roleLabel }}</span>
        </div>
        <router-link class="user-panel-return" :to="{ name: 'tables' }">
          {{ $t("auth.userPanel.backToTables") }}
        </router-link>
      </header>

      <nav
        class="user-panel-tabs"
        role="tablist"
        :aria-label="$t('auth.userPanel.title')"
      >
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.id"
          @click="selectTab(tab.id)"
        >
          <span aria-hidden="true">{{ tab.icon }}</span
          >{{ $t(tab.label) }}
        </button>
      </nav>

      <p
        v-if="error"
        class="user-panel-alert user-panel-alert--error"
        role="alert"
      >
        {{ error }}
      </p>
      <p v-if="notice" class="user-panel-alert" role="status">{{ notice }}</p>

      <UserOverviewTab
        v-if="activeTab === 'overview'"
        :campaigns="campaigns"
        :invitations="invitations"
        :sessions="sessions"
        :loading="loading"
      />
      <UserProfileForm
        v-else-if="activeTab === 'profile'"
        :user="user"
        :busy="busy === 'profile'"
        @save="saveProfile"
      />
      <UserSecurityPanel
        v-else-if="activeTab === 'security'"
        :sessions="sessions"
        :busy="busy"
        :oauth-providers="oauthProviders"
        :oauth-identities="oauthIdentities"
        @change-password="changePassword"
        @revoke-session="revokeSession"
        @revoke-others="revokeOtherSessions"
        @link-oauth="linkOAuth"
      />
      <UserPreferencesPanel
        v-else
        :locales="locales"
        :current-locale="currentLocale"
        @change-locale="changeLocale"
      />
    </section>
  </main>
</template>

<script src="./options/ProfileView.options.js" />
<style src="./styles/UserPanel.css" />
