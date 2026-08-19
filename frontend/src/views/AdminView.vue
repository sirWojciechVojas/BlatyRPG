<template>
  <div class="admin-page">
    <header class="admin-header">
      <div>
        <p>{{ $t("admin.eyebrow") }}</p>
        <h1>{{ $t("admin.title") }}</h1>
        <span>{{ $t("admin.subtitle") }}</span>
      </div>
      <router-link class="admin-secondary" :to="{ name: 'home' }">
        {{ $t("admin.actions.back") }}
      </router-link>
    </header>

    <main class="admin-main">
      <p v-if="error" class="admin-alert error" role="alert">
        {{ error }}
        <button type="button" @click="load">
          {{ $t("admin.actions.retry") }}
        </button>
      </p>
      <section class="admin-metrics" :aria-label="$t('admin.metrics.title')">
        <article v-for="metric in metricCards" :key="metric.label">
          <strong>{{ metric.value }}</strong
          ><span>{{ metric.label }}</span>
        </article>
      </section>

      <div v-if="loading" class="admin-loading" aria-live="polite">
        {{ $t("admin.loading") }}
      </div>
      <template v-else>
        <section class="admin-panel">
          <div class="admin-section-heading">
            <div>
              <h2>{{ $t("admin.users.title") }}</h2>
              <p>{{ $t("admin.users.description") }}</p>
            </div>
            <button class="admin-secondary" type="button" @click="load">
              {{ $t("admin.actions.refresh") }}
            </button>
          </div>
          <AdminUserTable
            :users="users"
            :current-user-id="currentUserId"
            :busy-user-id="busyUserId"
            @role-change="changeRole"
          />
          <p v-if="roleError" class="admin-alert error" role="alert">
            {{ roleError }}
          </p>
        </section>

        <AdminUserCreateForm
          ref="createForm"
          class="admin-panel"
          :busy="creating"
          :error="createError"
          @submit="createUser"
        />

        <section class="admin-panel">
          <div class="admin-section-heading">
            <div>
              <h2>{{ $t("admin.campaigns.title") }}</h2>
              <p>{{ $t("admin.campaigns.description") }}</p>
            </div>
          </div>
          <AdminCampaignTable :campaigns="campaigns" />
        </section>
      </template>
    </main>
  </div>
</template>

<script src="./options/AdminView.options.js"></script>
<style src="./styles/AdminView.css"></style>
