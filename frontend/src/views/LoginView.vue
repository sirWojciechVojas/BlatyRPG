<template>
  <div class="dashboard-page auth-entry-page" :style="styleVars">
    <main class="dashboard-main auth-entry-main">
      <DashboardLoginPanel
        :busy="busy"
        :error="error"
        :logo="logo"
        :oauth-providers="oauthProviders"
        :oauth-busy="oauthBusy"
        @submit="login"
        @oauth="startOAuth"
      />
    </main>
  </div>
</template>

<script src="./options/LoginView.options.js"></script>
<style src="./styles/DashboardHomeView.css"></style>
<style src="./styles/LoginView.css"></style>
