<template>
  <ErrorPage
    code="404"
    :eyebrow="$t('notFoundPage.eyebrow')"
    :title="$t('status.notFound')"
    :lead="$t('notFoundPage.lead')"
    :primary-cta="$t('notFoundPage.primaryCta')"
    :secondary-cta="$t('notFoundPage.secondaryCta')"
    :hint="$t('notFoundPage.hint')"
    :about-link-label="$t('nav.about')"
    :dice-alt="$t('notFoundPage.diceAlt')"
  />
</template>

<script>
import ErrorPage from "@/components/ErrorPage.vue";

export default {
  name: "NotFoundView",
  components: {
    ErrorPage,
  },
};
</script>
