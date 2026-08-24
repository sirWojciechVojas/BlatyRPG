<template>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>{{ $t("admin.fields.user") }}</th>
          <th>{{ $t("admin.fields.campaigns") }}</th>
          <th>{{ $t("admin.fields.created") }}</th>
          <th>{{ $t("admin.fields.role") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="`${user.id}-${user.role}`">
          <td>
            <span class="admin-user-cell">
              <span class="admin-user-avatar" aria-hidden="true">{{
                initials(user.username)
              }}</span>
              <span class="admin-user-identity">
                <strong>{{ user.username }}</strong>
                <small>{{ user.email }}</small>
              </span>
              <small v-if="user.id === currentUserId" class="admin-user-you">{{
                $t("admin.users.you")
              }}</small>
            </span>
          </td>
          <td>
            <span class="admin-count-chip">{{ user.campaignCount }}</span>
          </td>
          <td>{{ formatDate(user.createdAt) }}</td>
          <td>
            <select
              :value="user.role"
              :disabled="busyUserId > 0"
              :aria-label="
                $t('admin.users.changeRole', { name: user.username })
              "
              @change="changeRole(user, $event.target.value)"
            >
              <option v-for="role in roles" :key="role" :value="role">
                {{ $t(`admin.roles.${role}`) }}
              </option>
            </select>
          </td>
        </tr>
        <tr v-if="!users.length">
          <td colspan="4" class="admin-table-empty">
            {{ $t("admin.users.empty") }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
export default {
  name: "AdminUserTable",
  props: {
    users: { type: Array, required: true },
    currentUserId: { type: Number, default: 0 },
    busyUserId: { type: Number, default: 0 },
  },
  emits: ["role-change"],
  data: () => ({ roles: ["user", "admin"] }),
  methods: {
    initials(value) {
      return String(value || "?")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join("")
        .toLocaleUpperCase();
    },
    changeRole(user, role) {
      if (role !== user.role) this.$emit("role-change", { user, role });
    },
    formatDate(value) {
      if (!value) return "—";
      return new Intl.DateTimeFormat(this.$i18n.locale, {
        dateStyle: "short",
      }).format(new Date(value));
    },
  },
};
</script>
