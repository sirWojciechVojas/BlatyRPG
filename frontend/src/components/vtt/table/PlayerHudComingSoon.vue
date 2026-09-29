<template>
  <section class="player-hud-coming-soon" role="status">
    <span class="player-hud-coming-soon__mark" aria-hidden="true">◆</span>
    <h3>{{ title }}</h3>
    <p>{{ $t("vtt.table.playerHud.modal.comingSoon", { module: title }) }}</p>
  </section>
</template>

<script>
export default {
  name: "PlayerHudComingSoon",
  props: {
    title: { type: String, required: true },
  },
};
</script>

<style scoped>
.player-hud-coming-soon {
  display: grid;
  width: 100%;
  height: 100%;
  place-content: center;
  justify-items: center;
  gap: 0.8rem;
  padding: 2rem;
  color: #2b1b0d;
  background:
    linear-gradient(rgb(244 226 183 / 88%), rgb(212 177 116 / 90%)),
    url("../../../assets/app-ui/img/character-stats/CharacterStats-bg.png")
      center / cover;
  font-family: Garamond, Georgia, serif;
  text-align: center;
}

.player-hud-coming-soon__mark {
  color: #7c3f1e;
  font-size: 2.4rem;
  text-shadow: 0 2px 0 rgb(255 244 208 / 65%);
}

.player-hud-coming-soon h3,
.player-hud-coming-soon p {
  margin: 0;
}

.player-hud-coming-soon h3 {
  font-size: clamp(1.7rem, 4vw, 2.7rem);
}

.player-hud-coming-soon p {
  max-width: 34rem;
  font-size: clamp(1rem, 2vw, 1.3rem);
}
</style>
