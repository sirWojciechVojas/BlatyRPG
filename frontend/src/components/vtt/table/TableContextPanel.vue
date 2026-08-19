<template>
  <div class="table-context-panel">
    <template v-if="panelId === 'graphics'">
      <p class="table-context-panel__intro">
        {{ $t("vtt.table.graphics.description") }}
      </p>
      <div v-if="graphics.length" class="table-context-panel__gallery">
        <figure v-for="asset in graphics" :key="asset.key">
          <img :src="asset.url" :alt="asset.label" loading="lazy" />
          <figcaption>{{ asset.label }}</figcaption>
        </figure>
      </div>
      <p v-else class="table-context-panel__empty">
        {{ $t("vtt.table.graphics.empty") }}
      </p>
    </template>

    <template v-else-if="panelId === 'characters'">
      <ul v-if="characters.length" class="table-context-panel__list">
        <li v-for="character in characters" :key="character.id">
          <img v-if="character.avatarUrl" :src="character.avatarUrl" alt="" />
          <span>{{ character.name }}</span>
          <small>{{ character.visibility }}</small>
        </li>
      </ul>
      <p v-else class="table-context-panel__empty">
        {{ $t("vtt.table.characters.empty") }}
      </p>
      <router-link
        class="scene-button"
        :to="campaignRoute('character-workspace')"
      >
        {{ $t("vtt.table.characters.open") }}
      </router-link>
    </template>

    <template v-else-if="panelId === 'handouts'">
      <p class="table-context-panel__intro">
        {{ $t("vtt.table.handouts.description") }}
      </p>
      <p class="table-context-panel__empty">
        {{ $t("vtt.table.handouts.empty") }}
      </p>
    </template>

    <template v-else-if="panelId === 'scenario'">
      <p class="table-context-panel__intro">
        {{ campaign.description || $t("vtt.table.scenario.noDescription") }}
      </p>
      <dl class="table-context-panel__facts">
        <div>
          <dt>{{ $t("vtt.table.scenario.status") }}</dt>
          <dd>{{ campaign.status }}</dd>
        </div>
        <div>
          <dt>{{ $t("vtt.table.scenario.scenes") }}</dt>
          <dd>{{ scenes.length }}</dd>
        </div>
      </dl>
      <ol class="table-context-panel__list table-context-panel__list--ordered">
        <li v-for="scene in scenes" :key="scene.id">
          <span>{{ scene.name }}</span>
        </li>
      </ol>
    </template>

    <template v-else-if="panelId === 'shop'">
      <p class="table-context-panel__intro">
        {{ $t("vtt.table.shop.description") }}
      </p>
      <router-link
        v-if="canOpenShop"
        class="scene-button"
        :to="campaignRoute('shop-gm')"
      >
        {{ $t("vtt.table.shop.open") }}
      </router-link>
      <p v-else class="table-context-panel__empty">
        {{ $t("vtt.table.shop.unavailable") }}
      </p>
    </template>

    <template v-else-if="panelId === 'jukebox'">
      <p class="table-context-panel__intro">
        {{ $t("vtt.table.jukebox.description") }}
      </p>
      <p class="table-context-panel__empty">
        {{ $t("vtt.table.jukebox.empty") }}
      </p>
    </template>

    <template v-else-if="panelId === 'notifications'">
      <dl class="table-context-panel__facts">
        <div>
          <dt>{{ $t("vtt.table.notifications.connection") }}</dt>
          <dd>{{ realtimeStatus }}</dd>
        </div>
        <div>
          <dt>{{ $t("vtt.table.notifications.online") }}</dt>
          <dd>{{ onlineMembers.length }}/{{ members.length }}</dd>
        </div>
        <div v-if="canManage">
          <dt>{{ $t("vtt.table.notifications.invites") }}</dt>
          <dd>{{ invitations.length }}</dd>
        </div>
      </dl>
      <ul class="table-context-panel__list">
        <li v-for="member in onlineMembers" :key="member.userId">
          <span class="presence-dot online"></span
          ><span>{{ member.username || member.email }}</span>
        </li>
      </ul>
    </template>

    <template v-else-if="panelId === 'settings'">
      <p class="table-context-panel__intro">
        {{ $t("vtt.table.settings.description") }}
      </p>
      <div class="table-context-panel__actions">
        <router-link
          class="scene-button"
          :to="campaignRoute('campaign-lobby')"
          >{{ $t("vtt.table.settings.table") }}</router-link
        >
        <router-link class="scene-button" :to="{ name: 'profile' }">{{
          $t("vtt.table.settings.profile")
        }}</router-link>
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: "TableContextPanel",
  props: {
    panelId: { type: String, required: true },
    campaign: { type: Object, default: () => ({}) },
    scenes: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    realtimeStatus: { type: String, default: "disconnected" },
    canManage: { type: Boolean, default: false },
    canOpenShop: { type: Boolean, default: false },
  },
  computed: {
    graphics() {
      const items = [];
      if (this.campaign.bannerUrl)
        items.push({
          key: "banner",
          url: this.campaign.bannerUrl,
          label: this.campaign.name,
        });
      for (const scene of this.scenes) {
        if (scene.backgroundUrl)
          items.push({
            key: `scene-${scene.id}`,
            url: scene.backgroundUrl,
            label: scene.name,
          });
      }
      return items;
    },
    onlineMembers() {
      return this.members.filter((member) => member.isOnline);
    },
  },
  methods: {
    campaignRoute(name) {
      return { name, params: { campaignId: this.campaign.id } };
    },
  },
};
</script>
