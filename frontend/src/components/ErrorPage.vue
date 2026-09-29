<template>
  <section class="error-page">
    <div class="error-page__card">
      <p class="eyebrow">{{ eyebrow }}</p>
      <div class="error-page__headline">
        <span class="error-page__code">{{ code }}</span>
        <span class="error-page__title">{{ title }}</span>
      </div>
      <p class="lead">{{ lead }}</p>
      <div class="actions">
        <router-link class="cta-btn" :to="primaryTo">{{
          primaryCta
        }}</router-link>
        <button type="button" class="ghost-btn" @click="goBack">
          {{ secondaryCta }}
        </button>
      </div>
      <p class="hint">
        {{ hint }}
        <router-link class="ghost-link" :to="aboutTo">{{
          aboutLinkLabel
        }}</router-link
        >.
      </p>
    </div>
    <img class="error-page__dice" :src="assets.dice20" :alt="diceAlt" />
  </section>
</template>

<script>
import dice20 from "@/assets/app-ui/img/dice20.png";

export default {
  name: "ErrorPage",
  props: {
    code: {
      type: [String, Number],
      required: true,
    },
    eyebrow: {
      type: String,
      required: true,
    },
    title: {
      type: String,
      required: true,
    },
    lead: {
      type: String,
      required: true,
    },
    primaryCta: {
      type: String,
      required: true,
    },
    secondaryCta: {
      type: String,
      required: true,
    },
    hint: {
      type: String,
      required: true,
    },
    aboutLinkLabel: {
      type: String,
      required: true,
    },
    diceAlt: {
      type: String,
      required: true,
    },
    primaryTo: {
      type: String,
      default: "/",
    },
    aboutTo: {
      type: String,
      default: "/about",
    },
  },
  data() {
    return {
      assets: {
        dice20,
      },
    };
  },
  methods: {
    goBack() {
      this.$router.back();
    },
  },
};
</script>

<style scoped src="./ErrorPage.css"></style>
