<template>
  <main class="auth-page">
    <section class="auth-panel invitations-panel">
      <h1>{{ $t("campaignInvitations.title") }}</h1>
      <p v-if="loading">{{ $t("campaignInvitations.loading") }}</p>
      <p v-if="error" class="auth-error" role="alert">{{ error }}</p>
      <p v-if="!loading && !invitations.length">
        {{ $t("campaignInvitations.empty") }}
      </p>
      <ul>
        <li v-for="invitation in invitations" :key="invitation.id">
          <div>
            <strong>{{ invitation.campaignName }}</strong>
            <span>{{
              $t(`campaignInvitations.roles.${invitation.role}`)
            }}</span>
            <p v-if="invitation.message">{{ invitation.message }}</p>
          </div>
          <button
            type="button"
            :disabled="busyId === invitation.id"
            @click="respond(invitation.id, 'accept')"
          >
            {{ $t("campaignInvitations.actions.accept") }}
          </button>
          <button
            type="button"
            :disabled="busyId === invitation.id"
            @click="respond(invitation.id, 'reject')"
          >
            {{ $t("campaignInvitations.actions.reject") }}
          </button>
        </li>
      </ul>
      <router-link :to="{ name: 'home' }">{{
        $t("campaignInvitations.actions.back")
      }}</router-link>
    </section>
  </main>
</template>

<script>
import { campaignApiClient } from "@/lib/campaign/campaignApiClient";

export default {
  name: "MyInvitationsView",
  data: () => ({
    invitations: [],
    loading: true,
    busyId: null,
    error: "",
  }),
  async mounted() {
    await this.load();
  },
  methods: {
    message(error) {
      if (error?.network) return this.$t("campaignInvitations.errors.network");
      if (error?.status === 429)
        return this.$t("campaignInvitations.errors.rateLimited");
      return this.$t("campaignInvitations.errors.generic");
    },
    async load() {
      this.loading = true;
      this.error = "";
      try {
        this.invitations = await campaignApiClient.listMyInvitations();
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.loading = false;
      }
    },
    async respond(invitationId, action) {
      this.busyId = invitationId;
      this.error = "";
      try {
        await campaignApiClient.respondToInvitation(invitationId, action);
        this.invitations = this.invitations.filter(
          (item) => item.id !== invitationId,
        );
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.busyId = null;
      }
    },
  },
};
</script>

<style src="./styles/AuthViews.css" />
<style>
.invitations-panel ul {
  padding: 0;
  list-style: none;
}

.invitations-panel li {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid #5d4b34;
}

.invitations-panel li div {
  flex: 1;
  display: grid;
}
</style>
