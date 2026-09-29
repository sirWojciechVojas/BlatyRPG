<template>
  <div class="table-context-panel">
    <template v-if="panelId === 'graphics'">
      <p class="table-context-panel__intro">
        {{ $t("vtt.table.graphics.description") }}
      </p>
      <small v-if="canManage" class="table-context-panel__hint">
        {{ $t("vtt.table.graphics.dragHint") }}
      </small>
      <div v-if="graphics.length" class="table-context-panel__gallery">
        <figure
          v-for="asset in graphics"
          :key="asset.key"
          :draggable="canManage"
          @dragstart="startAssetDrag($event, asset)"
        >
          <SceneBackgroundImage :src="asset.url" :alt="asset.label" />
          <figcaption>{{ asset.label }}</figcaption>
        </figure>
      </div>
      <p v-else class="table-context-panel__empty">
        {{ $t("vtt.table.graphics.empty") }}
      </p>
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
  </div>
</template>

<script>
import SceneBackgroundImage from "@/components/vtt/scene/SceneBackgroundImage.vue";
import { writeTileAssetDrag } from "@/lib/vtt/tileDrop";

export default {
  name: "TableContextPanel",
  components: { SceneBackgroundImage },
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
  },
  methods: {
    startAssetDrag(event, asset) {
      if (this.canManage) writeTileAssetDrag(event.dataTransfer, asset);
    },
    campaignRoute(name) {
      return { name, params: { campaignId: this.campaign.id } };
    },
  },
};
</script>
