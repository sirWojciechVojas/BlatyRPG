<template>
  <div class="admin-users-layout container-fluid h-100 p-0">
    <div class="row g-2 h-100 m-0">
      <div
        class="admin-users-list-column col-12 col-xl-9 col-xxl-10 h-100 ps-0"
      >
        <section class="admin-panel admin-panel--table w-100 p-0">
          <header class="admin-section-heading px-3 pt-2">
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
          <p v-if="roleError" class="admin-alert error mx-3" role="alert">
            {{ roleError }}
          </p>
        </section>
      </div>
      <div class="col-12 col-xl-3 col-xxl-2 pe-0">
        <AdminUserCreateForm
          ref="createForm"
          class="admin-panel admin-create-card w-100"
          :busy="creating"
          :error="createError"
          :field-errors="createFieldErrors"
          @submit="$emit('create', $event)"
          @field-change="$emit('field-change', $event)"
        />
      </div>
    </div>
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
    createFieldErrors: { type: Object, default: () => ({}) },
    roleError: { type: String, default: "" },
  },
  emits: ["create", "field-change", "role-change"],
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
