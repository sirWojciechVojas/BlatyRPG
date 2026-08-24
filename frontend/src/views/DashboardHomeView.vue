<template>
  <div class="dashboard-page" :style="styleVars">
    <main class="dashboard-main">
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
        :games="games"
        @submit="createCampaign"
      />
    </main>
    <footer class="dashboard-footer">{{ $t("dashboard.footer") }}</footer>
  </div>
</template>

<script src="./options/DashboardHomeView.options.js"></script>
<style src="./styles/DashboardHomeView.css"></style>
