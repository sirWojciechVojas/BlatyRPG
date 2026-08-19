<template>
  <main class="campaign-lobby">
    <section v-if="!initialized && !localError" class="campaign-panel">
      {{ $t("campaignLobby.loading") }}
    </section>
    <p
      v-if="localError || context.error"
      class="campaign-lobby-error"
      role="alert"
    >
      {{ localError || $t("campaignLobby.errors.generic") }}
    </p>
    <template v-if="campaign">
      <header class="campaign-lobby-header">
        <img v-if="campaign.bannerUrl" :src="campaign.bannerUrl" alt="" />
        <div>
          <p>{{ $t(`campaignLobby.roles.${campaign.membershipRole}`) }}</p>
          <h1>{{ campaign.name }}</h1>
          <p>{{ campaign.description }}</p>
        </div>
        <router-link
          :to="{ name: 'scene-workspace', params: { campaignId: campaign.id } }"
        >
          {{ $t("campaignLobby.actions.enterVtt") }}
        </router-link>
      </header>

      <div class="campaign-lobby-grid">
        <OnlinePresencePanel
          :members="members"
          :status="realtime.status"
          :manual-retry-available="realtime.manualRetryAvailable"
          @retry="$store.dispatch('realtime/retry')"
        />
        <section class="campaign-panel">
          <h2>{{ $t("campaignLobby.characters.title") }}</h2>
          <ul class="campaign-list">
            <li v-for="character in context.characters" :key="character.id">
              <span>{{ character.name }}</span>
              <small>{{
                $t(`campaignLobby.access.${character.visibility}`)
              }}</small>
            </li>
          </ul>
        </section>
        <template v-if="canManage">
          <CampaignSettingsPanel
            :campaign="campaign"
            :busy="busy"
            @save="updateSettings"
          />
          <CampaignMembersPanel
            :members="members"
            :invitations="context.invitations"
            :owner-user-id="campaign.gameMasterId"
            :busy="busy"
            @invite="invite"
            @change-role="changeRole"
            @remove="removeMember"
            @revoke="revokeInvitation"
          />
          <CharacterAssignmentsPanel
            :characters="context.characters"
            :members="members"
            :permissions="permissions"
            :busy="busy"
            @load="loadPermissions"
            @access="setAccess"
            @owner="assignOwner"
            @visibility="setVisibility"
          />
        </template>
      </div>
    </template>
  </main>
</template>

<script src="./options/CampaignLobbyView.options.js" />
<style src="./styles/CampaignLobbyView.css" />
