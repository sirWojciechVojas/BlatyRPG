<!-- Panel GM sklepu: ShopWorkspaceShops. -->
<template>
  <section class="shop-workspace__body shop-workspace__body--shops">
    <div class="shop-workspace__subnav">
      <nav
        class="shop-workspace__subnav-tabs"
        :aria-label="$t('shop.workspace.shopSections')"
      >
        <button
          v-for="tab in shopSubtabs"
          :key="tab.id"
          type="button"
          :class="{ active: shopSubtab === tab.id }"
          @click="activateShopSubtab(tab.id)"
        >
          {{ $t(tab.label) }}
        </button>
      </nav>
      <div class="shop-workspace__subnav-actions">
        <label class="shop-workspace__shop-picker">
          <span>{{ $t("shop.workspace.shop") }}</span>
          <select
            class="form-select form-select-sm gm-combobox gm-combobox--shop"
            :value="activeShopId"
            @change="selectShop($event.target.value)"
          >
            <option v-for="shop in shops" :key="shop.id" :value="shop.id">
              {{ shop.name }}
            </option>
          </select>
        </label>
        <StatusChip
          :label="
            activeShop?.isActive !== false
              ? $t('shop.workspace.active')
              : $t('shop.workspace.inactive')
          "
          :tone="activeShop?.isActive !== false ? 'success' : 'neutral'"
          dot
        />
        <StatusChip
          v-if="formStatus.shop !== 'clean'"
          :label="$t(`shop.workspace.formStatus.${formStatus.shop}`)"
          :tone="formStatus.shop === 'error' ? 'danger' : 'warning'"
        />
        <button
          type="button"
          class="shop-workspace__add-shop"
          @click="openCreateShopDialog"
        >
          <span aria-hidden="true">＋</span>
          {{ $t("shop.workspace.createShop.action") }}
        </button>
        <ShopHelpTooltip
          :label="$t('shop.workspace.createShop.action')"
          :text="$t('shop.workspace.createShop.help')"
          align="right"
        />
        <button
          v-if="['profile', 'prices'].includes(shopSubtab)"
          type="button"
          class="primary shop-workspace__subnav-save"
          @click="saveProfile"
        >
          {{ $t("actions.save") }}
        </button>
        <details class="shop-workspace__menu">
          <summary :aria-label="$t('shop.workspace.moreActions')">•••</summary>
          <div>
            <button type="button" @click="requestDuplicate('profile')">
              {{ $t("shop.workspace.duplicateProfile") }}
            </button>
            <button
              type="button"
              @click="requestDuplicate('profile_with_offer')"
            >
              {{ $t("shop.workspace.duplicateWithOffer") }}
            </button>
            <button type="button" @click="exportShopJson">
              {{ $t("shop.workspace.exportJson") }}
            </button>
            <button
              type="button"
              class="danger"
              :disabled="shops.length <= 1"
              @click="confirmDeleteShop = true"
            >
              {{ $t("shop.workspace.deleteShop") }}
            </button>
          </div>
        </details>
      </div>
    </div>
    <ShopWorkspaceProfile v-if="shopSubtab === 'profile'" />
    <ShopWorkspaceOffer v-else-if="shopSubtab === 'offer'" />
    <ShopWorkspacePricing v-else />
  </section>
  <ShopWorkspaceCreateShopDialog
    v-if="createShopDialogOpen"
    :actor-options="actorOptions"
    :type-options="typeOptions"
    :existing-names="shops.map((shop) => shop.name).filter(Boolean)"
    :initial-owner-code="profileDraft.ownerCode"
    :initial-owner-name="profileDraft.ownerName"
    :busy="createShopPending"
    :error="createShopError"
    @close="closeCreateShopDialog"
    @create="submitNewShop"
  />
</template>
<script>
import { ref } from "vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import StatusChip from "@/components/shop/common/StatusChip.vue";
import ShopWorkspaceCreateShopDialog from "./ShopWorkspaceCreateShopDialog.vue";
import ShopWorkspaceOffer from "./ShopWorkspaceOffer.vue";
import ShopWorkspacePricing from "./ShopWorkspacePricing.vue";
import ShopWorkspaceProfile from "./ShopWorkspaceProfile.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceShops",
  components: {
    ShopHelpTooltip,
    StatusChip,
    ShopWorkspaceCreateShopDialog,
    ShopWorkspaceOffer,
    ShopWorkspacePricing,
    ShopWorkspaceProfile,
  },
  setup() {
    const workspace = useShopWorkspaceContext();
    const createShopDialogOpen = ref(false);
    const createShopPending = ref(false);
    const createShopError = ref("");

    function openCreateShopDialog() {
      createShopError.value = "";
      createShopDialogOpen.value = true;
    }
    function closeCreateShopDialog() {
      if (createShopPending.value) return;
      createShopDialogOpen.value = false;
      createShopError.value = "";
    }
    async function submitNewShop(payload) {
      createShopPending.value = true;
      createShopError.value = "";
      try {
        const shopId = await workspace.createNewShop(payload);
        if (shopId) {
          createShopDialogOpen.value = false;
          return;
        }
        if (shopId === undefined) return;
        createShopError.value = workspace.t(
          "shop.workspace.createShop.createError",
        );
      } catch (_error) {
        createShopError.value = workspace.t(
          "shop.workspace.createShop.createError",
        );
      } finally {
        createShopPending.value = false;
      }
    }

    return {
      ...workspace,
      createShopDialogOpen,
      createShopPending,
      createShopError,
      openCreateShopDialog,
      closeCreateShopDialog,
      submitNewShop,
    };
  },
};
</script>
