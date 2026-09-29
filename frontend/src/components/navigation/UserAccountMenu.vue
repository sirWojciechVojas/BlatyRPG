<template>
  <div ref="root" class="account-menu">
    <button
      class="account-menu__trigger"
      type="button"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-label="$t('auth.account.openMenu')"
      @click="open = !open"
      @keydown.escape.stop="close"
    >
      <span class="account-menu__avatar" aria-hidden="true">
        <img
          v-if="avatarVisible"
          :src="user.avatarUrl"
          alt=""
          @error="avatarVisible = false"
        />
        <span v-else>{{ initials }}</span>
      </span>
      <span class="account-menu__identity">
        <strong>{{ displayName }}</strong>
        <small>{{ roleLabel }}</small>
      </span>
      <span class="account-menu__chevron" aria-hidden="true">⌄</span>
    </button>

    <div v-if="open" class="account-menu__dropdown" role="menu">
      <header>
        <span class="account-menu__avatar account-menu__avatar--large">
          <img v-if="avatarVisible" :src="user.avatarUrl" alt="" />
          <span v-else>{{ initials }}</span>
        </span>
        <span>
          <strong>{{ displayName }}</strong>
          <small>{{ user.email }}</small>
        </span>
      </header>
      <div class="account-menu__links">
        <router-link :to="{ name: 'profile' }" role="menuitem">
          <span aria-hidden="true">◉</span>{{ $t("auth.account.panel") }}
        </router-link>
        <router-link :to="{ name: 'tables' }" role="menuitem">
          <span aria-hidden="true">▦</span>{{ $t("auth.account.tables") }}
        </router-link>
        <router-link :to="{ name: 'my-invitations' }" role="menuitem">
          <span aria-hidden="true">✉</span>{{ $t("auth.account.invitations") }}
        </router-link>
        <router-link v-if="isAdmin" :to="{ name: 'admin' }" role="menuitem">
          <span aria-hidden="true">◆</span>{{ $t("auth.account.admin") }}
        </router-link>
        <router-link :to="{ name: 'landing' }" role="menuitem">
          <span aria-hidden="true">⌂</span>{{ $t("auth.account.landing") }}
        </router-link>
      </div>
      <button
        class="account-menu__logout"
        type="button"
        role="menuitem"
        :disabled="loggingOut"
        @click="$emit('logout')"
      >
        <span aria-hidden="true">↪</span>
        {{
          loggingOut ? $t("auth.account.loggingOut") : $t("auth.account.logout")
        }}
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: "UserAccountMenu",
  props: {
    session: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
    loggingOut: { type: Boolean, default: false },
  },
  emits: ["logout"],
  data: () => ({ open: false, avatarVisible: true }),
  computed: {
    user() {
      return this.session?.user || {};
    },
    displayName() {
      return (
        this.user.username || this.user.email || this.$t("auth.account.user")
      );
    },
    initials() {
      return this.displayName.trim().slice(0, 2).toUpperCase();
    },
    roleLabel() {
      return this.$t(
        this.isAdmin ? "auth.account.roleAdmin" : "auth.account.roleUser",
      );
    },
  },
  watch: {
    "user.avatarUrl": {
      immediate: true,
      handler(value) {
        this.avatarVisible = Boolean(value);
      },
    },
    $route() {
      this.close();
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.handleOutside);
    document.addEventListener("keydown", this.handleKeydown);
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.handleOutside);
    document.removeEventListener("keydown", this.handleKeydown);
  },
  methods: {
    close() {
      this.open = false;
    },
    handleOutside(event) {
      if (this.open && !this.$refs.root?.contains(event.target)) this.close();
    },
    handleKeydown(event) {
      if (event.key === "Escape") this.close();
    },
  },
};
</script>

<style src="./styles/UserAccountMenu.css" scoped />
