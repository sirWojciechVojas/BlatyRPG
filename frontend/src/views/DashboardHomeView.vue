<template>
  <div class="dashboard-page">
    <main class="dashboard-main" :aria-busy="isLoading">
      <section class="dashboard-intro">
        <div>
          <h1>{{ $t("dashboard.home.title", { name: displayName }) }}</h1>
          <p>{{ $t("dashboard.home.description") }}</p>
        </div>
        <button
          v-if="canCreateCampaign"
          ref="newCampaignButton"
          class="brpg-primary dashboard-new-campaign"
          type="button"
          :disabled="isLoading"
          @click="openCreateCampaign"
        >
          <span class="brpg-icon brpg-icon--plus" aria-hidden="true" />
          <span>{{ $t("dashboard.create.open") }}</span>
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
          <h2 id="campaigns-title">{{ $t("dashboard.campaign.title") }}</h2>
          <span class="campaign-heading-ornament" aria-hidden="true" />
          <span class="campaign-count">
            {{
              $t("dashboard.carousel.headingCount", {
                visible: carouselRange.visible,
                total: campaigns.length,
              })
            }}
          </span>
        </div>
        <div
          v-if="isLoading"
          class="dashboard-panel loading-panel campaign-loading-panel"
          aria-live="polite"
        >
          <span class="dashboard-spinner" aria-hidden="true" />
          <p>{{ $t("dashboard.loading.campaigns") }}</p>
        </div>
        <CampaignCarousel
          v-else-if="campaigns.length"
          ref="carousel"
          :campaigns="campaigns"
          :games="games"
          @range-change="handleRangeChange"
        />
        <div v-else-if="!dashboardError" class="dashboard-panel empty-panel">
          <h3>{{ $t("dashboard.empty.title") }}</h3>
          <p>{{ $t("dashboard.empty.description") }}</p>
          <button
            v-if="canCreateCampaign"
            class="brpg-primary empty-create-action"
            type="button"
            @click="openCreateCampaign"
          >
            <span class="brpg-icon brpg-icon--plus" aria-hidden="true" />
            <span>{{ $t("dashboard.create.open") }}</span>
          </button>
        </div>
      </section>
    </main>

    <div
      v-if="showCreateForm"
      class="campaign-create-backdrop"
      role="presentation"
      @pointerdown.self="closeCreateCampaign"
      @keydown.esc.stop.prevent="closeCreateCampaign"
    >
      <div
        ref="createDialog"
        class="campaign-create-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-title"
        tabindex="-1"
      >
        <button
          class="campaign-create-close"
          type="button"
          :disabled="isCreating"
          :aria-label="$t('dashboard.create.close')"
          @click="closeCreateCampaign"
        >
          ×
        </button>
        <CampaignCreateForm
          ref="createForm"
          :busy="isCreating"
          :error="creationError"
          :games="games"
          @submit="createCampaign"
        />
      </div>
    </div>
  </div>
</template>

<script src="./options/DashboardHomeView.options.js"></script>
<style src="./styles/DashboardHomeView.css"></style>
