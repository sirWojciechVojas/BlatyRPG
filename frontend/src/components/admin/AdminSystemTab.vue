<template>
  <div class="admin-tab-grid admin-system-layout">
    <section class="admin-panel admin-system-card">
      <header>
        <h2>{{ $t("admin.system.title") }}</h2>
        <span class="admin-health"
          ><i></i>{{ $t("admin.system.operational") }}</span
        >
      </header>
      <dl>
        <div>
          <dt>{{ $t("admin.system.environment") }}</dt>
          <dd>{{ system.environment || "—" }}</dd>
        </div>
        <div>
          <dt>PHP</dt>
          <dd>{{ system.phpVersion || "—" }}</dd>
        </div>
        <div>
          <dt>{{ $t("admin.system.database") }}</dt>
          <dd>{{ system.database || "—" }}</dd>
        </div>
        <div>
          <dt>{{ $t("admin.metrics.activeSessions") }}</dt>
          <dd>{{ system.activeSessions }}</dd>
        </div>
        <div>
          <dt>{{ $t("admin.system.generated") }}</dt>
          <dd>{{ formatDate(system.generatedAt) }}</dd>
        </div>
      </dl>
    </section>
    <section class="admin-panel admin-system-card">
      <header>
        <h2>{{ $t("admin.system.security") }}</h2>
      </header>
      <ul class="admin-check-list">
        <li><i></i>{{ $t("admin.system.sessions") }}</li>
        <li><i></i>{{ $t("admin.system.accountRoles") }}</li>
        <li><i></i>{{ $t("admin.system.campaignRoles") }}</li>
        <li><i></i>{{ $t("admin.system.backendGuard") }}</li>
      </ul>
    </section>
    <section class="admin-panel admin-system-card admin-system-card--wide">
      <header>
        <h2>{{ $t("admin.system.roleModel") }}</h2>
      </header>
      <div class="admin-role-model">
        <div>
          <small>{{ $t("admin.system.application") }}</small
          ><strong
            >{{ $t("admin.roles.user") }} ·
            {{ $t("admin.roles.admin") }}</strong
          >
        </div>
        <span>→</span>
        <div>
          <small>{{ $t("admin.system.perTable") }}</small
          ><strong
            >{{ $t("admin.campaignRoles.gm") }} ·
            {{ $t("admin.campaignRoles.assistant") }} ·
            {{ $t("admin.campaignRoles.player") }} ·
            {{ $t("admin.campaignRoles.observer") }}</strong
          >
        </div>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: "AdminSystemTab",
  props: { system: { type: Object, required: true } },
  methods: {
    formatDate(value) {
      if (!value) return "—";
      return new Intl.DateTimeFormat(this.$i18n.locale, {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(new Date(value));
    },
  },
};
</script>
