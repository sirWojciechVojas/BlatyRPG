<template>
  <div class="admin-tab-grid admin-users-layout">
    <section class="admin-panel admin-panel--table">
      <header class="admin-section-heading">
        <div>
          <h2>{{ $t("admin.users.title") }}</h2>
          <p>{{ $t("admin.users.description") }}</p>
        </div>
        <label class="admin-search">
          <span class="ui-visually-hidden">{{
            $t("admin.actions.searchUsers")
          }}</span>
          <input
            v-model.trim="query"
            type="search"
            :placeholder="$t('admin.actions.searchUsers')"
          />
        </label>
      </header>
      <AdminUserTable
        :users="filteredUsers"
        :current-user-id="currentUserId"
        :busy-user-id="busyUserId"
        @role-change="$emit('role-change', $event)"
      />
      <p v-if="roleError" class="admin-alert error" role="alert">
        {{ roleError }}
      </p>
    </section>
    <AdminUserCreateForm
      ref="createForm"
      class="admin-panel admin-create-card"
      :busy="creating"
      :error="createError"
      @submit="$emit('create', $event)"
    />
  </div>
</template>

<script>
import AdminUserCreateForm from "./AdminUserCreateForm.vue";
import AdminUserTable from "./AdminUserTable.vue";

export default {
  name: "AdminUsersTab",
  components: { AdminUserCreateForm, AdminUserTable },
  props: {
    users: { type: Array, required: true },
    currentUserId: { type: Number, default: 0 },
    busyUserId: { type: Number, default: 0 },
    creating: Boolean,
    createError: { type: String, default: "" },
    roleError: { type: String, default: "" },
  },
  emits: ["create", "role-change"],
  data: () => ({ query: "" }),
  computed: {
    filteredUsers() {
      const query = this.query.toLocaleLowerCase();
      if (!query) return this.users;
      return this.users.filter((user) =>
        [user.username, user.email, user.role].some((value) =>
          String(value).toLocaleLowerCase().includes(query),
        ),
      );
    },
  },
  methods: {
    resetForm() {
      this.$refs.createForm?.reset();
    },
  },
};
</script>
