<template>
  <div class="admin-characters-layout container-fluid h-100 p-0">
    <section class="admin-panel admin-panel--table w-100 p-0">
      <header class="admin-section-heading px-3 pt-2">
        <div>
          <h2>{{ $t("admin.characters.title") }}</h2>
          <p>{{ $t("admin.characters.description") }}</p>
        </div>
        <label class="admin-character-search">
          <span class="ui-visually-hidden">{{
            $t("admin.actions.searchCharacters")
          }}</span>
          <input
            v-model.trim="query"
            type="search"
            :placeholder="$t('admin.actions.searchCharacters')"
          />
        </label>
      </header>
      <p v-if="error" class="admin-alert error mx-3 mb-2" role="alert">
        {{ error }}
      </p>

      <div class="admin-character-workspace row g-2 m-0 px-2 pb-2">
        <section class="col-12 col-xl-3 h-100">
          <div class="admin-character-column h-100">
            <header>
              <h3>{{ $t("admin.characters.catalog") }}</h3>
              <small>{{ $t("admin.characters.dragHint") }}</small>
            </header>
            <div class="admin-character-list">
              <article
                v-for="character in filteredCharacters"
                :key="character.id"
                class="admin-character-card"
                draggable="true"
                @dragstart="startDrag(character, $event)"
              >
                <span>{{ initials(character.name) }}</span>
                <strong>{{ character.name }}</strong>
                <small>{{
                  $t("admin.characters.assignmentCount", {
                    tables: campaignsFor(character.id).length,
                    owners: ownersFor(character.id).length,
                  })
                }}</small>
              </article>
              <p
                v-if="!filteredCharacters.length"
                class="admin-character-empty"
              >
                {{ $t("admin.characters.empty") }}
              </p>
            </div>
          </div>
        </section>

        <section class="col-12 col-xl-4 h-100">
          <div class="admin-character-column h-100">
            <header>
              <h3>{{ $t("admin.characters.tableContainers") }}</h3>
              <small>{{ $t("admin.characters.multiTableHint") }}</small>
            </header>
            <div class="admin-container-list">
              <article
                v-for="campaign in campaigns"
                :key="campaign.id"
                class="admin-assignment-container"
                :class="{ busy: isBusy('campaign', campaign.id) }"
                @dragover.prevent
                @drop.prevent="dropOnCampaign(campaign)"
              >
                <header>
                  <strong>{{ campaign.name }}</strong>
                  <small>{{ charactersForCampaign(campaign.id).length }}</small>
                </header>
                <div class="admin-assignment-chips">
                  <span
                    v-for="character in charactersForCampaign(campaign.id)"
                    :key="character.id"
                  >
                    {{ character.name }}
                    <button
                      type="button"
                      :title="$t('admin.characters.removeFromTable')"
                      @click="changeCampaign(character, campaign.id, false)"
                    >
                      ×
                    </button>
                  </span>
                  <em v-if="!charactersForCampaign(campaign.id).length">{{
                    $t("admin.characters.dropHere")
                  }}</em>
                </div>
              </article>
            </div>
          </div>
        </section>

        <section class="col-12 col-xl-5 h-100">
          <div class="admin-character-column h-100">
            <header>
              <h3>{{ $t("admin.characters.ownerContainers") }}</h3>
              <small>{{ $t("admin.characters.multiOwnerHint") }}</small>
            </header>
            <div class="admin-container-list admin-owner-containers">
              <article
                v-for="gm in gameMasters"
                :key="`${gm.campaignId}:${gm.userId}`"
                class="admin-assignment-container"
                :class="{ busy: isBusy('owner', gm.campaignId, gm.userId) }"
                @dragover.prevent
                @drop.prevent="dropOnOwner(gm)"
              >
                <header>
                  <strong>{{ gm.username }}</strong>
                  <small>{{ campaignName(gm.campaignId) }}</small>
                </header>
                <div class="admin-assignment-chips">
                  <span
                    v-for="character in charactersForOwner(gm)"
                    :key="character.id"
                  >
                    {{ character.name }}
                    <button
                      type="button"
                      :title="$t('admin.characters.removeFromOwner')"
                      @click="changeOwner(character, gm, false)"
                    >
                      ×
                    </button>
                  </span>
                  <em v-if="!charactersForOwner(gm).length">{{
                    $t("admin.characters.dropHere")
                  }}</em>
                </div>
              </article>
            </div>
          </div>
        </section>
      </div>
    </section>
  </div>
</template>

<script src="./options/AdminCharactersTab.options.js"></script>
