<template>
  <div class="dashboard-page auth-entry-page" :style="styleVars">
    <header class="dashboard-topbar">
      <router-link class="dashboard-brand" :to="{ name: 'landing' }">
        <img :src="logo" alt="" />
        <span>
          <strong>{{ $t("dashboard.brand.title") }}</strong>
          <small>{{ $t("dashboard.brand.subtitle") }}</small>
        </span>
      </router-link>
      <router-link class="secondary-action" :to="{ name: 'landing' }">
        {{ $t("auth.actions.backToLanding") }}
      </router-link>
    </header>

    <main class="dashboard-main auth-entry-main">
      <DashboardLoginPanel :busy="busy" :error="error" @submit="login" />
    </main>
  </div>
</template>

<script src="./options/LoginView.options.js"></script>
<style src="./styles/DashboardHomeView.css"></style>
<style src="./styles/LoginView.css"></style>
