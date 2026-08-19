<template>
  <aside v-if="enabled" class="shop-access-dock">
    <button
      type="button"
      class="shop-access-tab"
      :aria-expanded="open"
      :title="$t('shop.access.title')"
      @click="open = !open"
    >
      <span>{{ currentLabel }}</span>
      <span aria-hidden="true">{{ open ? "‹" : "›" }}</span>
    </button>
    <section
      v-if="open"
      class="shop-access-dialog"
      role="region"
      :aria-label="$t('shop.access.title')"
    >
      <h2>{{ $t("shop.access.shortTitle") }}</h2>
      <p>{{ $t("shop.access.notice") }}</p>
      <div v-if="loading">{{ $t("ui.loading") }}</div>
      <div v-else-if="error" class="shop-access-error">
        {{ $t("shop.access.loadError") }}
      </div>
      <div v-else-if="currentUser" class="shop-access-options">
        <div class="shop-access-user">
          <span
            class="shop-access-user__icon bi"
            :class="
              currentUser.mode === 'gm'
                ? 'bi-person-badge-fill'
                : 'bi-person-fill'
            "
            aria-hidden="true"
          ></span>
          <span class="shop-access-user__identity">
            <small>{{ $t("shop.access.activeUser") }}</small>
            <strong>{{ currentUser.label }}</strong>
          </span>
          <button
            v-if="userOptions.length > 1"
            type="button"
            class="shop-access-change-user"
            :class="{ 'shop-access-change-user--active': userPickerOpen }"
            :disabled="switching"
            :title="$t('shop.access.changeUser')"
            :aria-label="$t('shop.access.changeUser')"
            :aria-expanded="userPickerOpen"
            @click="userPickerOpen = !userPickerOpen"
          >
            <span class="bi bi-arrow-repeat" aria-hidden="true"></span>
            <span class="bi bi-person-fill" aria-hidden="true"></span>
          </button>
        </div>
        <div v-if="userPickerOpen" class="shop-access-user-picker">
          <button
            v-for="user in userOptions"
            :key="user.key"
            type="button"
            class="shop-access-user-option"
            :class="{
              'shop-access-user-option--active': user.key === activeUserKey,
            }"
            :disabled="switching"
            @click="selectUser(user)"
          >
            <span
              class="bi"
              :class="
                user.mode === 'gm' ? 'bi-person-badge-fill' : 'bi-person-fill'
              "
              aria-hidden="true"
            ></span>
            <span>{{ user.label }}</span>
          </button>
        </div>
        <div v-else class="shop-access-group">
          <strong class="shop-access-group__title">{{
            $t("shop.access.availableCharacters")
          }}</strong>
          <div class="shop-access-characters">
            <button
              v-for="character in currentUser.characters"
              :key="`${currentUser.key}:${character.ownerCode}:${character.characterId || 0}`"
              type="button"
              class="shop-access-option"
              :class="{
                'shop-access-option--gm': currentUser.mode === 'gm',
                'shop-access-option--active':
                  currentUser.key === activeUserKey &&
                  character.ownerCode === session?.ownerCode,
              }"
              :disabled="switching"
              @click="selectCharacter(character)"
            >
              <strong>{{ character.name }}</strong>
              <span>{{ character.ownerCode }}</span>
            </button>
          </div>
        </div>
      </div>
    </section>
  </aside>
</template>

<script>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useStore } from "vuex";
import i18n from "@/i18n";
import { shopApiClient, createShopApiConfig } from "@/lib/trade/shopApiClient";
import {
  getShopAccessSession,
  setShopAccessSession,
} from "@/lib/trade/shopAccessSession";

export default {
  name: "ShopAccessModeSelector",
  setup() {
    const route = useRoute();
    const store = useStore();
    const enabled = ref(false);
    const open = ref(false);
    const loading = ref(false);
    const switching = ref(false);
    const userPickerOpen = ref(false);
    const error = ref(false);
    const options = ref({ modes: {}, characters: [], players: [] });
    const session = ref(getShopAccessSession());
    const campaignId = computed(() =>
      Number(
        route.params.campaignId || process.env.VUE_APP_SHOP_CAMPAIGN_ID || 1,
      ),
    );
    const currentLabel = computed(() => {
      if (session.value?.mode === "gm") {
        return `DEV · GM${session.value.name ? ` · ${session.value.name}` : ""}`;
      }
      if (session.value?.mode === "player") {
        return `DEV · ${session.value.playerLabel || session.value.name || session.value.ownerCode}`;
      }
      return "DEV";
    });
    const userOptions = computed(() => {
      const users = [];
      const gmCharacters = Array.isArray(options.value.characters)
        ? options.value.characters
        : [];
      if (options.value.modes?.gm && gmCharacters.length) {
        users.push({
          key: "gm",
          id: "gm",
          mode: "gm",
          label: i18n.global.t("shop.access.gm"),
          characters: gmCharacters,
        });
      }
      (options.value.players || []).forEach((player) => {
        const characters = Array.isArray(player.characters)
          ? player.characters
          : [];
        if (!characters.length) return;
        const playerNumber = i18n.global.t("shop.access.playerNumber", {
          number: player.number,
        });
        users.push({
          key: `player:${player.id}`,
          id: String(player.id || ""),
          mode: "player",
          label: player.name
            ? `${playerNumber} · ${player.name}`
            : playerNumber,
          player,
          characters,
        });
      });
      return users;
    });
    const activeUserKey = computed(() =>
      session.value?.mode === "gm"
        ? "gm"
        : session.value?.mode === "player"
          ? `player:${session.value.playerId}`
          : userOptions.value[0]?.key || "",
    );
    const currentUser = computed(
      () =>
        userOptions.value.find((user) => user.key === activeUserKey.value) ||
        userOptions.value[0] ||
        null,
    );
    const load = async () => {
      loading.value = true;
      error.value = false;
      try {
        const response = await shopApiClient.getAccessOptions(
          createShopApiConfig({ campaignId: campaignId.value }),
        );
        options.value = response || { modes: {}, characters: [], players: [] };
        enabled.value = response?.developmentSelectorEnabled === true;
        open.value = enabled.value && !session.value;
      } catch (requestError) {
        error.value = true;
      } finally {
        loading.value = false;
      }
    };
    const apply = async (next, { keepOpen = false } = {}) => {
      setShopAccessSession(next);
      session.value = next;
      open.value = keepOpen;
      switching.value = true;
      try {
        if (store.hasModule("shop")) {
          // GM is still authorized as GM by the API, but choosing a character
          // always opens the regular customer-facing shop for that character.
          store.commit("shop/enterCharacterShoppingMode");
          await store.dispatch("shop/loadTradingData", {
            campaignId: campaignId.value,
            ownerCode: next.ownerCode,
            forceReload: true,
          });
        }
      } finally {
        switching.value = false;
      }
    };
    const selectGm = (character, applyOptions) =>
      apply(
        {
          mode: "gm",
          ownerCode: String(character?.ownerCode || "").toUpperCase(),
          characterId: Number(character?.characterId) || null,
          name: String(character?.name || ""),
          playerId: "gm",
          playerLabel: "GM",
        },
        applyOptions,
      );
    const selectPlayer = (player, character, applyOptions) =>
      apply(
        {
          mode: "player",
          ownerCode: String(character.ownerCode || "").toUpperCase(),
          characterId: Number(character.characterId) || null,
          name: String(character.name || character.ownerCode || ""),
          playerId: String(player.id || ""),
          playerLabel: String(player.name || ""),
        },
        applyOptions,
      );
    const selectCharacter = (character) => {
      if (currentUser.value?.mode === "gm") {
        return selectGm(character);
      }
      return selectPlayer(currentUser.value.player, character);
    };
    const selectUser = (user) => {
      userPickerOpen.value = false;
      if (user?.key === currentUser.value?.key) return Promise.resolve();
      const character = user?.characters?.[0];
      if (!user || !character) return Promise.resolve();
      return user.mode === "gm"
        ? selectGm(character, { keepOpen: true })
        : selectPlayer(user.player, character, { keepOpen: true });
    };

    onMounted(load);
    watch(campaignId, load);
    return {
      enabled,
      open,
      loading,
      error,
      switching,
      userPickerOpen,
      options,
      session,
      currentLabel,
      userOptions,
      activeUserKey,
      currentUser,
      selectCharacter,
      selectUser,
    };
  },
};
</script>

<style scoped>
.shop-access-dock {
  position: fixed;
  top: 50%;
  left: 0;
  z-index: 1200;
  display: flex;
  align-items: flex-start;
  transform: translateY(-50%);
  color: #fff3d5;
  filter: drop-shadow(0 5px 12px #0008);
}
.shop-access-tab {
  order: 2;
  display: flex;
  align-items: center;
  gap: 5px;
  min-height: 108px;
  width: 34px;
  border: 1px solid #c7954d;
  border-left: 0;
  border-radius: 0 8px 8px 0;
  padding: 6px 5px;
  color: inherit;
  background: #382415f2;
  font-size: 11px;
}
.shop-access-tab span:first-child {
  overflow: hidden;
  max-height: 86px;
  writing-mode: vertical-rl;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.shop-access-dialog {
  order: 1;
  width: 320px;
  border: 1px solid #c7954d;
  border-left: 0;
  border-radius: 0 10px 10px 0;
  padding: 13px;
  color: #fff3d5;
  background: #2b1c12f5;
  text-align: left;
}
.shop-access-dialog h2 {
  margin: 0 0 4px;
  font-size: 15px;
}
.shop-access-dialog p {
  margin: 0;
  color: #d8c5a8;
  font-size: 11px;
  line-height: 1.3;
}
.shop-access-options {
  display: flex;
  flex-direction: column;
  gap: 9px;
  margin-top: 10px;
}
.shop-access-user {
  display: grid;
  grid-template-columns: 24px 1fr 38px;
  align-items: center;
  gap: 7px;
  border: 1px solid #8b6538;
  border-radius: 7px;
  padding: 6px 7px;
  background: #1d140eaa;
}
.shop-access-user__icon {
  color: #e7bd78;
  font-size: 18px;
  text-align: center;
}
.shop-access-user__identity {
  display: flex;
  min-width: 0;
  flex-direction: column;
}
.shop-access-user__identity small {
  color: #baa98d;
  font-size: 9px;
  line-height: 1.1;
}
.shop-access-user__identity strong {
  overflow: hidden;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.shop-access-change-user {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;
  width: 36px;
  height: 30px;
  border: 1px solid #bd843b;
  border-radius: 6px;
  color: #fff3d5;
  background: #50351f;
}
.shop-access-change-user:hover,
.shop-access-change-user:focus,
.shop-access-change-user--active {
  border-color: #f0c171;
  background: #684524;
}
.shop-access-user-picker {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 4px;
}
.shop-access-user-option {
  display: grid;
  grid-template-columns: 18px 1fr;
  align-items: center;
  gap: 5px;
  min-width: 0;
  min-height: 30px;
  border: 1px solid #755735;
  border-radius: 5px;
  padding: 4px 6px;
  color: #e8d7bc;
  background: #302116;
  font-size: 10px;
  text-align: left;
}
.shop-access-user-option span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.shop-access-user-option:hover,
.shop-access-user-option:focus,
.shop-access-user-option--active {
  border-color: #d5a95f;
  color: #fff3d5;
  background: #50351f;
}
.shop-access-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.shop-access-characters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
  gap: 4px;
}
.shop-access-group__title {
  font-size: 11px;
  color: #e7bd78;
}
.shop-access-group__title small {
  color: #baa98d;
  font-size: 10px;
  font-weight: 400;
}
.shop-access-option {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
  min-height: 36px;
  border: 1px solid #755735;
  border-radius: 6px;
  padding: 5px 7px;
  color: #fff3d5;
  background: #3b291b;
  text-align: left;
}
.shop-access-option:hover,
.shop-access-option:focus {
  border-color: #e2b465;
  background: #50351f;
}
.shop-access-option strong {
  overflow: hidden;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.shop-access-option span {
  color: #d8c5a8;
  font-size: 10px;
}
.shop-access-option--gm {
  border-color: #bd843b;
}
.shop-access-option--active {
  border-color: #efc56f;
  box-shadow: inset 3px 0 #efc56f;
  background: #5a3a20;
}
.shop-access-option:disabled,
.shop-access-change-user:disabled,
.shop-access-user-option:disabled {
  cursor: wait;
  opacity: 0.65;
}
.shop-access-error {
  color: #ffb4a8;
  font-size: 11px;
}
@media (max-width: 560px) {
  .shop-access-dialog {
    width: 250px;
  }
}
</style>
