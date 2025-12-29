<template>
  <nav :class="{ 'nav-dice': isDiceRoute }">
    <router-link to="/">Home</router-link> |
    <router-link to="/about">About</router-link> |
    <router-link to="/dice">Dice Roller</router-link>
  </nav>
  <router-view />
</template>

<script>
  export default {
    name: 'Home',
    
    data() {
      return {
        localization: {},
        // Nazwa aplikacji (fallback do tytułu zakładki)
        appTitle: (typeof process !== 'undefined' && process.env && process.env.VUE_APP_TITLE)
        ? process.env.VUE_APP_TITLE
        : 'BlatyRPG',
    };
  },
  watch: {
    // Ustawia tytuł zakładki na podstawie meta.title w routach
    $route: {
      immediate: true,
      handler(to) {
        const pageTitle = to?.meta?.title;
        document.title = pageTitle ? `${pageTitle} — ${this.appTitle}` : this.appTitle;
      },
    },
  },
  computed: {
    isDiceRoute() {
      return this.$route?.path === "/dice";
    },
  },
};
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
}

nav {
  padding: 30px;
}

nav a {
  font-weight: bold;
  color: #2c3e50;
}

nav a.router-link-exact-active {
  color: #42b983;
}

nav.nav-dice {
  position: fixed;
  top: 12px;
  right: 12px;
  padding: 6px 10px;
  background: rgba(0, 0, 0, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 10px;
  font-size: 0.9rem;
  z-index: 20;
}

nav.nav-dice a {
  color: #ffffff;
}

nav.nav-dice a.router-link-exact-active {
  color: #ffd166;
}
</style>
