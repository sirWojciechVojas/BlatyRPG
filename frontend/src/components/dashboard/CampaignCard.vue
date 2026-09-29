<template>
  <article
    ref="root"
    class="brpg-card"
    :class="{ 'brpg-card--inactive': !campaign.isActive }"
    :aria-labelledby="titleId"
  >
    <div class="brpg-card-art" :class="{ 'is-placeholder': fallbackFailed }">
      <img
        v-if="!fallbackFailed"
        :src="artworkSrc"
        alt=""
        width="768"
        height="1024"
        :loading="priority ? 'eager' : 'lazy'"
        decoding="async"
        @error="handleArtworkError"
      />
    </div>

    <div class="brpg-ribbon" :class="`brpg-ribbon--${ribbon}`">
      <h3 :id="titleId">{{ campaign.name }}</h3>
    </div>

    <div class="brpg-card-footer">
      <div class="brpg-system" :title="systemName">
        <span class="brpg-icon brpg-icon--book" aria-hidden="true" />
        <span>{{ systemName }}</span>
      </div>
      <div
        class="brpg-status"
        :class="{ 'brpg-status--inactive': !campaign.isActive }"
      >
        {{ statusLabel }}
      </div>
      <div class="brpg-actions">
        <button class="brpg-primary" type="button" @click="launchCampaign">
          <span class="brpg-icon brpg-icon--play" aria-hidden="true" />
          <span>{{ $t("dashboard.campaign.launch") }}</span>
        </button>
        <button
          ref="menuTrigger"
          class="brpg-square"
          type="button"
          aria-haspopup="menu"
          :aria-expanded="menuOpen"
          :aria-label="
            $t('dashboard.campaign.menuLabel', { name: campaign.name })
          "
          @click="toggleMenu"
          @keydown.down.prevent="openMenu(true)"
          @keydown.esc.stop="closeMenu(true)"
        >
          <span class="brpg-icon brpg-icon--more" aria-hidden="true" />
        </button>
      </div>

      <div
        v-if="menuOpen"
        ref="menu"
        class="brpg-dropdown"
        role="menu"
        @keydown="handleMenuKeydown"
      >
        <router-link
          v-for="item in menuItems"
          :key="item.key"
          :to="item.to"
          role="menuitem"
          @click="closeMenu(false)"
        >
          {{ item.label }}
        </router-link>
      </div>
    </div>
  </article>
</template>

<script>
export default {
  name: "CampaignCard",
  props: {
    campaign: { type: Object, required: true },
    systemName: { type: String, required: true },
    fallbackArtwork: { type: String, required: true },
    ribbon: {
      type: String,
      default: "red",
      validator: (value) => ["red", "blue", "green", "neutral"].includes(value),
    },
    priority: { type: Boolean, default: false },
  },
  data: () => ({
    imageFailed: false,
    fallbackFailed: false,
    menuOpen: false,
  }),
  computed: {
    titleId() {
      return `campaign-title-${String(this.campaign.id).replace(/[^a-z0-9_-]/gi, "-")}`;
    },
    artworkSrc() {
      return !this.imageFailed && this.campaign.bannerUrl
        ? this.campaign.bannerUrl
        : this.fallbackArtwork;
    },
    statusLabel() {
      return this.$t(
        this.campaign.isActive
          ? "dashboard.campaign.active"
          : "dashboard.campaign.inactive",
      );
    },
    menuItems() {
      const campaignId = this.campaign.id;
      const items = [
        {
          key: "characters",
          label: this.$t("dashboard.campaign.openCharacters"),
          to: {
            name: "scene-workspace",
            params: { campaignId },
            hash: "#table-characters",
          },
        },
        {
          key: "chat",
          label: this.$t("dashboard.campaign.openChat"),
          to: {
            name: "scene-workspace",
            params: { campaignId },
            hash: "#campaign-chat",
          },
        },
      ];
      if (this.campaign.capabilities?.canOpenShop) {
        items.push({
          key: "shop",
          label: this.$t("dashboard.campaign.openShop"),
          to: { name: "shop-gm", params: { campaignId } },
        });
      }
      return items;
    },
  },
  watch: {
    "campaign.bannerUrl"() {
      this.imageFailed = false;
      this.fallbackFailed = false;
    },
    $route() {
      this.closeMenu(false);
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.handleOutsidePointer);
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.handleOutsidePointer);
  },
  methods: {
    launchCampaign() {
      this.$router.push({
        name: "scene-workspace",
        params: { campaignId: this.campaign.id },
      });
    },
    handleArtworkError() {
      if (!this.imageFailed && this.campaign.bannerUrl) {
        this.imageFailed = true;
        return;
      }
      this.fallbackFailed = true;
    },
    toggleMenu() {
      if (this.menuOpen) this.closeMenu(false);
      else this.openMenu(false);
    },
    openMenu(focusFirst = false) {
      this.menuOpen = true;
      if (focusFirst) {
        this.$nextTick(() => this.menuElements()[0]?.focus());
      }
    },
    closeMenu(restoreFocus = false) {
      const wasOpen = this.menuOpen;
      this.menuOpen = false;
      if (wasOpen && restoreFocus) {
        this.$nextTick(() => this.$refs.menuTrigger?.focus());
      }
    },
    menuElements() {
      return [
        ...(this.$refs.menu?.querySelectorAll('[role="menuitem"]') || []),
      ];
    },
    handleMenuKeydown(event) {
      const items = this.menuElements();
      const current = items.indexOf(document.activeElement);
      if (event.key === "Escape") {
        event.preventDefault();
        this.closeMenu(true);
      } else if (event.key === "ArrowDown") {
        event.preventDefault();
        items[(current + 1 + items.length) % items.length]?.focus();
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        items[(current - 1 + items.length) % items.length]?.focus();
      } else if (event.key === "Home") {
        event.preventDefault();
        items[0]?.focus();
      } else if (event.key === "End") {
        event.preventDefault();
        items.at(-1)?.focus();
      }
    },
    handleOutsidePointer(event) {
      if (this.menuOpen && !this.$refs.root?.contains(event.target)) {
        this.closeMenu(false);
      }
    },
  },
};
</script>
