<template>
  <div class="dashboard-page" :style="styleVars">
    <header class="dashboard-topbar">
      <router-link class="dashboard-brand" :to="{ name: 'home' }">
        <img :src="logo" alt="" />
        <span>
          <strong>{{ $t("dashboard.brand.title") }}</strong>
          <small>{{ $t("dashboard.brand.subtitle") }}</small>
        </span>
      </router-link>
      <div class="dashboard-topbar-actions">
        <router-link
          v-if="isAdmin"
          class="secondary-action"
          :to="{ name: 'admin' }"
        >
          {{ $t("admin.title") }}
        </router-link>
        <router-link class="secondary-action" :to="{ name: 'dice' }">
          {{ $t("dashboard.actions.dice") }}
        </router-link>
        <label class="dashboard-locale">
          <span class="visually-hidden">{{ $t("nav.language") }}</span>
          <select v-model="currentLocale" :aria-label="$t('nav.language')">
            <option v-for="item in locales" :key="item.code" :value="item.code">
              {{ item.label }}
            </option>
          </select>
        </label>
        <template v-if="session">
          <router-link class="secondary-action" :to="{ name: 'profile' }">
            {{ $t("auth.profile.title") }}
          </router-link>
          <router-link
            class="secondary-action"
            :to="{ name: 'my-invitations' }"
          >
            {{ $t("campaignLobby.myInvitations.title") }}
          </router-link>
          <span class="user-chip">
            {{ session.user?.username || session.user?.email }}
          </span>
          <button class="text-action" type="button" @click="logout">
            {{ $t("dashboard.actions.logout") }}
          </button>
        </template>
      </div>
    </header>

    <main class="dashboard-main">
      <section
        v-if="isRestoring"
        class="dashboard-panel loading-panel"
        aria-live="polite"
      >
        <span class="dashboard-spinner" aria-hidden="true" />
        <p>{{ $t("dashboard.loading.session") }}</p>
      </section>

      <DashboardLoginPanel
        v-else-if="!session"
        :busy="isLoggingIn"
        :error="loginError"
        @submit="login"
      />

      <template v-else>
        <section class="dashboard-intro">
          <div>
            <p class="eyebrow">{{ $t("dashboard.home.eyebrow") }}</p>
            <h1>{{ $t("dashboard.home.title", { name: displayName }) }}</h1>
            <p>{{ $t("dashboard.home.description") }}</p>
          </div>
          <button
            class="secondary-action"
            type="button"
            :disabled="isLoading"
            @click="loadDashboard"
          >
            {{ $t("dashboard.actions.refresh") }}
          </button>
        </section>

        <p
          v-if="dashboardError"
          class="dashboard-alert dashboard-error"
          role="alert"
        >
          {{ dashboardError }}
          <button type="button" @click="loadDashboard">
            {{ $t("dashboard.actions.retry") }}
          </button>
        </p>

        <section class="campaign-section" aria-labelledby="campaigns-title">
          <div class="section-heading">
            <div>
              <p class="eyebrow">{{ $t("dashboard.campaign.eyebrow") }}</p>
              <h2 id="campaigns-title">{{ $t("dashboard.campaign.title") }}</h2>
            </div>
            <span class="campaign-count">{{ campaigns.length }}</span>
          </div>
          <div
            v-if="isLoading"
            class="dashboard-panel loading-panel"
            aria-live="polite"
          >
            <span class="dashboard-spinner" aria-hidden="true" />
            <p>{{ $t("dashboard.loading.campaigns") }}</p>
          </div>
          <div v-else-if="campaigns.length" class="campaign-grid">
            <CampaignCard
              v-for="campaign in campaigns"
              :key="campaign.id"
              :campaign="campaign"
            />
          </div>
          <div v-else-if="!dashboardError" class="dashboard-panel empty-panel">
            <h3>{{ $t("dashboard.empty.title") }}</h3>
            <p>{{ $t("dashboard.empty.description") }}</p>
          </div>
        </section>

        <CampaignCreateForm
          v-if="canCreateCampaign"
          ref="createForm"
          :busy="isCreating"
          :error="creationError"
          @submit="createCampaign"
        />
      </template>
    </main>
    <footer class="dashboard-footer">{{ $t("dashboard.footer") }}</footer>
  </div>
</template>

<script src="./options/DashboardHomeView.options.js"></script>
<style src="./styles/DashboardHomeView.css"></style>
