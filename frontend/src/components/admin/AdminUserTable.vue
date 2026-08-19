<template>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>{{ $t("admin.fields.user") }}</th>
          <th>{{ $t("admin.fields.email") }}</th>
          <th>{{ $t("admin.fields.campaigns") }}</th>
          <th>{{ $t("admin.fields.role") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="`${user.id}-${user.role}`">
          <td>
            <strong>{{ user.username }}</strong>
            <small v-if="user.id === currentUserId">{{
              $t("admin.users.you")
            }}</small>
          </td>
          <td>{{ user.email }}</td>
          <td>{{ user.campaignCount }}</td>
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
  data: () => ({ roles: ["player", "gm", "admin"] }),
  methods: {
    changeRole(user, role) {
      if (role !== user.role) this.$emit("role-change", { user, role });
    },
  },
};
</script>
