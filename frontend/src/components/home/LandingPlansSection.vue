<template>
  <section class="section plans-section" id="plans">
    <div class="section-inner">
      <div class="section-head">
        <p class="eyebrow">{{ $t("landing.plans.eyebrow") }}</p>
        <h2>{{ $t("landing.plans.title") }}</h2>
        <p class="section-lead">{{ $t("landing.plans.lead") }}</p>
      </div>

      <p v-if="loading" class="plans-state" role="status">
        {{ $t("landing.plans.loading") }}
      </p>
      <div
        v-else-if="error"
        class="plans-state plans-state--error"
        role="alert"
      >
        <span>{{ $t("landing.plans.error") }}</span>
        <button type="button" class="ghost-btn" @click="$emit('retry')">
          {{ $t("landing.plans.retry") }}
        </button>
      </div>
      <div v-else class="plans-grid">
        <article
          v-for="plan in plans"
          :key="plan.code"
          class="plan-card"
          :class="{ 'plan-card--highlighted': plan.highlighted }"
        >
          <span v-if="plan.highlighted" class="plan-badge">
            {{ $t("landing.plans.recommended") }}
          </span>
          <h3>{{ $t(`landing.plans.catalog.${plan.code}.name`) }}</h3>
          <p>{{ $t(`landing.plans.catalog.${plan.code}.description`) }}</p>
          <div class="plan-price">
            <strong v-if="plan.price.amountMinor === 0">
              {{ $t("landing.plans.free") }}
            </strong>
            <strong v-else>{{ formatPrice(plan.price) }}</strong>
            <small>{{
              $t(`landing.plans.intervals.${plan.price.interval}`)
            }}</small>
          </div>
          <dl class="plan-limits">
            <div v-for="[key, value] in limitEntries(plan)" :key="key">
              <dt>{{ $t(`landing.plans.limits.${key}`) }}</dt>
              <dd>{{ formatLimit(key, value) }}</dd>
            </div>
          </dl>
          <ul class="plan-features">
            <li v-for="feature in plan.features" :key="feature">
              {{ $t(`landing.plans.features.${feature}`) }}
            </li>
          </ul>
          <router-link class="cta-btn" :to="{ name: 'register' }">
            {{ $t("landing.plans.choose") }}
          </router-link>
        </article>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "LandingPlansSection",
  props: {
    plans: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  emits: ["retry"],
  methods: {
    limitEntries(plan) {
      return Object.entries(plan.limits || {});
    },
    formatPrice(price) {
      const locale =
        typeof this.$i18n.locale === "string"
          ? this.$i18n.locale
          : this.$i18n.locale.value;
      return new Intl.NumberFormat(locale, {
        style: "currency",
        currency: price.currency,
      }).format(price.amountMinor / 100);
    },
    formatLimit(key, value) {
      if (value === null) return this.$t("landing.plans.unlimited");
      if (key === "storageMb" && value >= 1024) {
        return this.$t("landing.plans.storageGb", { value: value / 1024 });
      }
      if (key === "storageMb") {
        return this.$t("landing.plans.storageMb", { value });
      }
      return new Intl.NumberFormat(this.$i18n.locale).format(value);
    },
  },
};
</script>
