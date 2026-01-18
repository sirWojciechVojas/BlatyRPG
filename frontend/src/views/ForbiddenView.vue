<template>
  <ErrorPage
    code="403"
    :eyebrow="$t('forbiddenPage.eyebrow')"
    :title="$t('status.accessDenied')"
    :lead="$t('forbiddenPage.lead')"
    :primary-cta="$t('forbiddenPage.primaryCta')"
    :secondary-cta="$t('forbiddenPage.secondaryCta')"
    :hint="$t('forbiddenPage.hint')"
    :about-link-label="$t('nav.about')"
    :dice-alt="$t('forbiddenPage.diceAlt')"
  />
</template>

<script>
import ErrorPage from "@/components/ErrorPage.vue";

export default {
  name: "ForbiddenView",
  components: {
    ErrorPage,
  },
};
</script>
