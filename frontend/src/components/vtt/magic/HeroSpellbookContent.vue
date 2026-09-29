<template>
  <section
    class="hero-spellbook"
    :style="{ '--magic-accent': profile.accentColor || '#8d4b32' }"
    :aria-busy="loading ? 'true' : 'false'"
  >
    <div v-if="loading && !data" class="hero-spellbook__state">
      Otwieranie księgi czarów…
    </div>
    <div v-else-if="error && !data" class="hero-spellbook__state" role="alert">
      <strong>Nie udało się otworzyć księgi.</strong>
      <span>{{ errorMessage }}</span>
      <button type="button" class="magic-button" @click="load">Ponów</button>
    </div>

    <template v-else-if="data">
      <header class="hero-spellbook__profile">
        <div>
          <strong>{{ character.name }}</strong>
          <span>
            {{ profile.tradition || "Tradycja jeszcze niewybrana" }}
            <template v-if="profile.wind"> · {{ profile.wind }}</template>
            <template v-if="profile.path">
              · Ścieżka {{ profile.path }}</template
            >
          </span>
        </div>
        <dl>
          <div>
            <dt>Mag</dt>
            <dd>{{ character.magic }}</dd>
          </div>
          <div>
            <dt>SW</dt>
            <dd>{{ character.willpower }}</dd>
          </div>
          <div>
            <dt>PD</dt>
            <dd>{{ character.experience }}</dd>
          </div>
          <div>
            <dt>Chaos</dt>
            <dd>{{ profile.chaosDice }}k10</dd>
          </div>
        </dl>
      </header>

      <nav class="hero-spellbook__tabs" aria-label="Widoki księgi czarów">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          :aria-pressed="String(activeTab === tab.id)"
          @click="activeTab = tab.id"
        >
          {{ tab.label }} <span v-if="tab.count !== null">{{ tab.count }}</span>
        </button>
      </nav>

      <p v-if="notice" class="hero-spellbook__notice" role="status">
        {{ notice }}
      </p>
      <p
        v-if="actionError"
        class="hero-spellbook__notice hero-spellbook__notice--error"
        role="alert"
      >
        {{ actionError }}
      </p>

      <div
        v-if="activeTab === 'book'"
        class="spellbook-view spellbook-view--book"
      >
        <aside class="spell-list">
          <div class="spell-list__filters">
            <input
              v-model.trim="search"
              type="search"
              placeholder="Szukaj czaru…"
              aria-label="Szukaj znanego czaru"
            />
            <select v-model="targetFilter" aria-label="Filtr zastosowania">
              <option value="all">Wszystkie zastosowania</option>
              <option value="self">Własna postać</option>
              <option value="single">Pojedynczy cel</option>
              <option value="missiles">Pociski</option>
              <option value="area">Obszar</option>
              <option value="touch">Dotyk</option>
            </select>
            <label
              ><input v-model="favoritesOnly" type="checkbox" /> Tylko
              ulubione</label
            >
            <select v-model="sortBy" aria-label="Sortowanie czarów">
              <option value="name">Nazwa</option>
              <option value="castingNumber">WPM</option>
              <option value="castingTime">Czas</option>
            </select>
          </div>
          <div class="spell-list__caption">
            <span>{{ filteredSpells.length }} znanych</span><span>WPM</span>
          </div>
          <div class="spell-list__rows">
            <button
              v-for="spell in filteredSpells"
              :key="spell.id"
              type="button"
              :class="{ 'is-selected': spell.id === selectedSpellId }"
              :aria-pressed="String(spell.id === selectedSpellId)"
              @click="selectSpell(spell.id)"
            >
              <span
                ><i v-if="spell.favorite" aria-label="Ulubiony">★</i
                >{{ spell.name }}</span
              >
              <b>{{ spell.castingNumber }}</b>
            </button>
            <p v-if="!filteredSpells.length" class="magic-empty">
              Brak czarów pasujących do filtrów.
            </p>
          </div>
        </aside>

        <article v-if="selectedSpell" class="magic-parchment spell-details">
          <div class="spell-details__heading">
            <div>
              <span class="magic-eyebrow"
                >{{ selectedSpell.tradition }} ·
                {{ selectedSpell.magicType }}</span
              >
              <h3>{{ selectedSpell.name }}</h3>
            </div>
            <div class="spell-details__actions">
              <button
                type="button"
                :aria-pressed="String(selectedSpell.favorite)"
                @click="togglePreference('favorite')"
              >
                {{ selectedSpell.favorite ? "★" : "☆" }} Ulubiony
              </button>
              <button
                type="button"
                :aria-pressed="String(selectedSpell.pinned)"
                @click="togglePreference('pinned')"
              >
                ⌖ {{ selectedSpell.pinned ? "Odepnij" : "Przypnij do HUD" }}
              </button>
            </div>
          </div>
          <dl class="spell-facts">
            <div>
              <dt>WPM</dt>
              <dd>{{ selectedSpell.castingNumber }}</dd>
            </div>
            <div>
              <dt>Czas</dt>
              <dd>{{ selectedSpell.castingTime }}</dd>
            </div>
            <div>
              <dt>Zasięg</dt>
              <dd>{{ selectedSpell.range || "—" }}</dd>
            </div>
            <div>
              <dt>Trwanie</dt>
              <dd>{{ selectedSpell.duration || "—" }}</dd>
            </div>
          </dl>
          <p class="spell-details__effect">{{ selectedSpell.effect }}</p>
          <p v-if="selectedSpell.defense">
            <strong>Obrona:</strong> {{ selectedSpell.defense }}
          </p>
          <p v-if="selectedSpell.ingredient" class="spell-details__ingredient">
            <strong>Składnik:</strong> {{ selectedSpell.ingredient.name }}
            <b>+{{ selectedSpell.ingredient.bonus }}</b>
          </p>
          <label class="magic-field"
            >Osobista notatka
            <textarea
              v-model="personalNote"
              rows="3"
              maxlength="1000"
              @change="saveNote"
            />
          </label>
          <p class="magic-source">
            {{ selectedSpell.source.title }} · s.
            {{ selectedSpell.source.page || "?" }}
          </p>
          <button
            type="button"
            class="magic-button magic-button--primary"
            @click="activeTab = 'cast'"
          >
            Przejdź do rzucania
          </button>
        </article>
        <article v-else class="magic-parchment magic-empty">
          Ta postać nie zna jeszcze żadnego opublikowanego czaru.
        </article>
      </div>

      <div
        v-else-if="activeTab === 'learn'"
        class="magic-parchment learning-view"
      >
        <div class="magic-section-heading">
          <div>
            <span class="magic-eyebrow">WIEDZA I ZGODA MG</span>
            <h3>Nauka zaklęć</h3>
          </div>
          <b>100 PD / dodatkowy czar</b>
        </div>

        <section v-if="!profile.pathId" class="learning-card">
          <h4>Wybierz Tradycję i jedną ścieżkę</h4>
          <p>
            Pakiet ścieżki daje dokładnie 10 czarów. Zgłoszenie i zatwierdzenie
            pakietu nie nalicza 10 × 100 PD.
          </p>
          <div class="learning-card__selectors">
            <label class="magic-field"
              >Tradycja
              <select v-model.number="selectedTraditionId">
                <option :value="null">Wybierz…</option>
                <option
                  v-for="tradition in learning.traditions"
                  :key="tradition.id"
                  :value="tradition.id"
                >
                  {{ tradition.name }} · {{ tradition.wind }}
                </option>
              </select>
            </label>
            <label class="magic-field"
              >Ścieżka
              <select v-model.number="selectedPathId">
                <option :value="null">Wybierz…</option>
                <option
                  v-for="path in availablePaths"
                  :key="path.id"
                  :value="path.id"
                  :disabled="!path.complete"
                >
                  {{ path.name }} · {{ path.spellCount }}/10<span
                    v-if="!path.complete"
                  >
                    · dane do weryfikacji</span
                  >
                </option>
              </select>
            </label>
          </div>
          <button
            type="button"
            class="magic-button magic-button--primary"
            :disabled="!selectedPath?.complete || busy"
            @click="requestPath"
          >
            Zgłoś wybór ścieżki
          </button>
        </section>

        <section v-else class="learning-card">
          <h4>Dodatkowe zaklęcie</h4>
          <p>
            Widoczne są wyłącznie formuły ujawnione tej postaci przez MG. PD
            zostaną pobrane dopiero przy zatwierdzeniu.
          </p>
          <div v-if="learning.revealedSpells.length" class="learning-spells">
            <button
              v-for="spell in learning.revealedSpells"
              :key="spell.id"
              type="button"
              :class="{ 'is-selected': selectedLearningSpellId === spell.id }"
              :disabled="!spell.eligible"
              @click="selectedLearningSpellId = spell.id"
            >
              <strong>{{ spell.name }}</strong
              ><span
                >WPM {{ spell.castingNumber }} · {{ spell.xpCost }} PD</span
              >
            </button>
          </div>
          <p v-else class="magic-empty">
            MG nie ujawnił jeszcze żadnej dodatkowej formuły.
          </p>
          <label class="magic-field"
            >Źródło nauki<input
              v-model.trim="learningSource"
              maxlength="180"
              placeholder="Księga, nauczyciel lub badania"
          /></label>
          <label class="magic-field"
            >Notatka dla MG<textarea
              v-model.trim="learningNote"
              rows="2"
              maxlength="600"
            />
          </label>
          <button
            type="button"
            class="magic-button magic-button--primary"
            :disabled="!selectedLearningSpellId || busy"
            @click="requestSpell"
          >
            Zgłoś naukę · bez pobierania PD
          </button>
        </section>

        <section class="learning-requests">
          <h4>Zgłoszenia</h4>
          <article v-for="request in learning.requests" :key="request.id">
            <span
              >#{{ request.id }} ·
              {{ request.type === "path" ? "Ścieżka" : "Zaklęcie" }}</span
            >
            <b :class="`status-${request.status}`">{{
              learningStatus(request.status)
            }}</b>
            <small
              >{{ request.xpCost }} PD<span v-if="request.decisionNote">
                · {{ request.decisionNote }}</span
              ></small
            >
            <div
              v-if="
                capabilities.canDecideLearning && request.status === 'pending'
              "
              class="learning-requests__decision"
            >
              <button
                type="button"
                class="magic-button"
                @click="decideLearning(request.id, 'reject')"
              >
                Odrzuć
              </button>
              <button
                type="button"
                class="magic-button magic-button--primary"
                @click="decideLearning(request.id, 'approve')"
              >
                Zatwierdź
              </button>
            </div>
          </article>
        </section>
      </div>

      <div
        v-else-if="activeTab === 'cast'"
        class="spellbook-view spellbook-view--cast"
      >
        <article class="magic-parchment cast-form">
          <div class="magic-section-heading">
            <div>
              <span class="magic-eyebrow">AUTORYTATYWNY RZUT SERWERA</span>
              <h3>Rzucanie</h3>
            </div>
            <b>1 czar na rundę</b>
          </div>
          <label class="magic-field"
            >Czar
            <select v-model.number="selectedSpellId">
              <option
                v-for="spell in knownSpells"
                :key="spell.id"
                :value="spell.id"
              >
                {{ spell.name }} · WPM {{ spell.castingNumber }}
              </option>
            </select>
          </label>
          <template v-if="selectedSpell">
            <div class="cast-pool">
              <span>Pula mocy · Mag {{ character.magic }}</span
              ><button
                v-for="die in diceOptions"
                :key="die"
                type="button"
                :aria-pressed="String(powerDice === die)"
                @click="powerDice = die"
              >
                {{ die }}k10
              </button>
            </div>
            <p class="cast-chaos">
              <strong>Kostki Chaosu:</strong> {{ profile.chaosDice }}k10 · nie
              zwiększają mocy, uczestniczą w sprawdzeniu Przekleństwa.
            </p>
            <label v-if="selectedSpell.ingredient" class="magic-field"
              >Opcjonalny składnik
              <select v-model="selectedIngredientKey">
                <option value="">Bez składnika</option>
                <option
                  v-for="item in matchingIngredients"
                  :key="`${item.kind}:${item.id}`"
                  :value="`${item.kind}:${item.id}`"
                >
                  {{ item.name }} · {{ item.quantity }} szt. · +{{
                    selectedSpell.ingredient.bonus
                  }}
                </option>
              </select>
              <small v-if="!matchingIngredients.length"
                >Brak właściwego składnika nie blokuje czaru.</small
              >
            </label>
            <label class="magic-field"
              >Cele / podział pocisków<textarea
                v-model.trim="castTargets"
                rows="2"
                maxlength="500"
                placeholder="Np. Bandyta: 2, Bestia: 1"
              />
            </label>
            <div class="cast-form__formula">
              <strong
                >{{ powerDice }}k10
                <template v-if="declaredBonus">+ {{ declaredBonus }}</template>
                ≥ {{ selectedSpell.castingNumber }}</strong
              ><span>Składnik zużywa się przy próbie, także nieudanej.</span>
            </div>
            <p v-if="capabilities.castBlockedReason" class="cast-blocked">
              {{ capabilities.castBlockedReason }}
            </p>
            <div class="cast-form__actions">
              <button
                type="button"
                class="magic-button"
                :disabled="!canCast || busy || activeCast?.channel?.attempted"
                @click="channelMagic"
              >
                Spleć magię · test SW
              </button>
              <button
                type="button"
                class="magic-button magic-button--primary"
                :disabled="!canCast || busy"
                @click="castSpell"
              >
                {{ castButtonLabel }}
              </button>
            </div>
            <p v-if="activeCast?.channel?.attempted" class="channel-result">
              Splatanie:
              {{ activeCast.channel.succeeded ? "sukces" : "porażka" }} · rzut
              {{ activeCast.channel.roll }} / SW {{ character.willpower
              }}<template v-if="activeCast.channel.bonus">
                · +{{ activeCast.channel.bonus }} do mocy</template
              >
            </p>
            <p v-if="activeCast?.status === 'casting'" class="channel-result">
              Długie rzucanie: {{ activeCast.actionsCompleted }} /
              {{ activeCast.actionsRequired }} akcji. Przerwanie nie tworzy
              efektu.
            </p>
          </template>
        </article>

        <article class="magic-parchment cast-result" aria-live="polite">
          <template v-if="castResult">
            <div class="magic-section-heading">
              <div>
                <span class="magic-eyebrow">WYNIK SERWERA</span>
                <h3>{{ castResult.spell.name }}</h3>
              </div>
              <b
                >Moc {{ castResult.powerTotal }} /
                {{ castResult.castingNumber }}</b
              >
            </div>
            <section>
              <h4>Czar</h4>
              <p
                :class="
                  castResult.spellSucceeded
                    ? 'result-success'
                    : 'result-failure'
                "
              >
                {{ castResult.spellSucceeded ? "Udany" : "Nieudany"
                }}<template v-if="castResult.automaticFailure">
                  · automatyczna porażka (wszystkie jedynki), wymagany test
                  SW</template
                >
              </p>
            </section>
            <section>
              <h4>Kości mocy</h4>
              <div class="result-dice">
                <b
                  v-for="(die, index) in castResult.powerDice"
                  :key="`p${index}`"
                  >{{ die }}</b
                >
              </div>
              <p v-if="castResult.chaosDice.length">
                Chaos:
                <span class="result-dice"
                  ><b
                    v-for="(die, index) in castResult.chaosDice"
                    :key="`c${index}`"
                    >{{ die }}</b
                  ></span
                >
              </p>
            </section>
            <section>
              <h4>Manifestacje</h4>
              <p v-if="!castResult.manifestations.length">
                Brak Przekleństwa Tzeentcha.
              </p>
              <ul v-else>
                <li
                  v-for="manifestation in castResult.manifestations"
                  :key="`${manifestation.face}:${manifestation.matchingDice}`"
                >
                  {{ manifestationLabel(manifestation.severity) }} ·
                  {{ manifestation.matchingDice }} × {{ manifestation.face }}
                </li>
              </ul>
            </section>
            <section>
              <h4>Obrona celu</h4>
              <p>{{ castResult.targetDefense.message }}</p>
            </section>
            <section>
              <h4>Efekt</h4>
              <p>{{ castResult.effect.message }}</p>
            </section>
            <section v-if="castResult.ingredient">
              <h4>Składnik</h4>
              <p>
                {{ castResult.ingredient.name }} zużyty · premia +{{
                  castResult.ingredient.bonus
                }}
              </p>
            </section>
          </template>
          <p v-else class="magic-empty">
            Tutaj pojawią się osobno wynik czaru, manifestacje, obrona celu i
            efekt.
          </p>
        </article>
      </div>

      <div
        v-else-if="activeTab === 'rituals'"
        class="magic-parchment ritual-view"
      >
        <div class="magic-section-heading">
          <div>
            <span class="magic-eyebrow">RYTUAŁY I BADANIA</span>
            <h3>Koncept nowej Formuły</h3>
          </div>
          <b>Decyzja MG</b>
        </div>
        <ol class="ritual-stages">
          <li class="is-active">Koncept</li>
          <li>Formuła</li>
          <li>Badania</li>
          <li>Próba</li>
          <li>Zapis</li>
        </ol>
        <label class="magic-field"
          >Nazwa robocza<input v-model.trim="ritualName" maxlength="160"
        /></label>
        <label class="magic-field"
          >Zamierzony efekt<textarea
            v-model.trim="ritualEffect"
            rows="4"
            maxlength="4000"
          />
        </label>
        <div class="ritual-fields">
          <label class="magic-field"
            >Wymagania · po jednym wierszu<textarea
              v-model.trim="ritualRequirements"
              rows="3"
              maxlength="3000"
            />
          </label>
          <label class="magic-field"
            >Obowiązkowe składniki · po jednym wierszu<textarea
              v-model.trim="ritualIngredients"
              rows="3"
              maxlength="3000"
            />
          </label>
          <label class="magic-field"
            >Czas odprawiania lub badań<input
              v-model.trim="ritualCastingTime"
              maxlength="160"
              placeholder="Czas świata kampanii"
            />
          </label>
          <label class="magic-field"
            >Znane konsekwencje<textarea
              v-model.trim="ritualConsequences"
              rows="3"
              maxlength="4000"
            />
          </label>
        </div>
        <dl class="ritual-facts">
          <div>
            <dt>Minimalne Mag i WPM</dt>
            <dd>Ustalane w etapie Formuły</dd>
          </div>
          <div>
            <dt>Składniki</dt>
            <dd>Wymagane osobno; nie są premią zwykłego czaru</dd>
          </div>
          <div>
            <dt>Czas</dt>
            <dd>Według zegara świata kampanii</dd>
          </div>
          <div>
            <dt>Konsekwencje</dt>
            <dd>Gracz widzi tylko jawne następstwa</dd>
          </div>
        </dl>
        <button
          type="button"
          class="magic-button magic-button--primary"
          :disabled="ritualName.length < 2 || ritualEffect.length < 10 || busy"
          @click="saveRitual"
        >
          Zapisz koncept
        </button>
        <div v-if="data.rituals.length" class="ritual-list">
          <article v-for="ritual in data.rituals" :key="ritual.id">
            <strong>{{ ritual.name }}</strong
            ><span>{{ ritual.stage }} · {{ ritual.status }}</span>
            <p>{{ ritual.effect }}</p>
            <small v-if="ritual.castingTime"
              >Czas: {{ ritual.castingTime }}</small
            >
            <small v-if="ritual.requirements.length"
              >Wymagania: {{ ritual.requirements.join("; ") }}</small
            >
            <small v-if="ritual.ingredients.length"
              >Składniki: {{ ritual.ingredients.join("; ") }}</small
            >
            <small v-if="ritual.knownConsequences"
              >Konsekwencje: {{ ritual.knownConsequences }}</small
            >
          </article>
        </div>
      </div>

      <div v-else class="magic-parchment history-view">
        <div class="magic-section-heading">
          <div>
            <span class="magic-eyebrow">DZIENNIK MAGII</span>
            <h3>Historia bohatera</h3>
          </div>
          <b>{{ data.history.length }} wpisów</b>
        </div>
        <article v-for="entry in data.history" :key="entry.id">
          <time>{{ formatDate(entry.createdAt) }}</time
          ><strong>{{ historyTitle(entry.type) }}</strong>
          <p>{{ historyMessage(entry) }}</p>
          <small v-if="entry.data.xpSpent"
            >Wydano {{ entry.data.xpSpent }} PD</small
          ><small v-if="entry.data.ingredient?.consumed"
            >Zużyto: {{ entry.data.ingredient.name }}</small
          >
        </article>
        <p v-if="!data.history.length" class="magic-empty">
          Historia pojawi się po zgłoszeniu nauki, rzucie lub zapisaniu
          konceptu.
        </p>
      </div>

      <footer class="hero-spellbook__quick">
        <span>Przypięte do HUD</span
        ><button
          v-for="spell in pinnedSpells"
          :key="spell.id"
          type="button"
          @click="openPinned(spell.id)"
        >
          {{ spell.name }}</button
        ><small v-if="!pinnedSpells.length">Brak skrótów</small>
      </footer>
    </template>
  </section>
</template>

<script>
import { ensureMagicStoreModule } from "@/store/modules/loadMagicModule";
import {
  matchingSpellIngredients,
  newIdempotencyKey,
  spellMatchesFilters,
} from "@/lib/magic/magicUi";

export default {
  name: "HeroSpellbookContent",
  props: {
    campaignId: { type: [Number, String], required: true },
    characterId: { type: [Number, String], required: true },
    initialSpellId: { type: [Number, String], default: null },
  },
  data: () => ({
    moduleReady: false,
    activeTab: "book",
    selectedSpellId: null,
    search: "",
    targetFilter: "all",
    favoritesOnly: false,
    sortBy: "name",
    personalNote: "",
    powerDice: 1,
    selectedIngredientKey: "",
    castTargets: "",
    selectedTraditionId: null,
    selectedPathId: null,
    selectedLearningSpellId: null,
    learningSource: "",
    learningNote: "",
    ritualName: "",
    ritualEffect: "",
    ritualRequirements: "",
    ritualIngredients: "",
    ritualCastingTime: "",
    ritualConsequences: "",
    busy: false,
    notice: "",
    actionError: "",
    initialSpellHandled: false,
  }),
  computed: {
    storeBook() {
      if (!this.moduleReady || !this.$store.hasModule("magic")) return {};
      return this.$store.getters["magic/book"](
        this.campaignId,
        this.characterId,
      );
    },
    data() {
      return this.storeBook.data || null;
    },
    loading() {
      return this.storeBook.phase === "loading";
    },
    error() {
      return this.storeBook.error || null;
    },
    character() {
      return this.data?.character || {};
    },
    profile() {
      return this.data?.profile || {};
    },
    capabilities() {
      return this.data?.capabilities || {};
    },
    learning() {
      return (
        this.data?.learning || {
          traditions: [],
          revealedSpells: [],
          requests: [],
        }
      );
    },
    knownSpells() {
      return this.data?.knownSpells || [];
    },
    filteredSpells() {
      const spells = this.knownSpells.filter((spell) =>
        spellMatchesFilters(spell, {
          search: this.search,
          kind: this.targetFilter,
          favorites: this.favoritesOnly,
        }),
      );
      const sortBy = this.sortBy;
      return spells
        .slice()
        .sort((left, right) =>
          sortBy === "castingNumber"
            ? left.castingNumber - right.castingNumber
            : String(left[sortBy] || "").localeCompare(
                String(right[sortBy] || ""),
                "pl",
              ),
        );
    },
    selectedSpell() {
      return (
        this.knownSpells.find(
          (spell) => spell.id === Number(this.selectedSpellId),
        ) || null
      );
    },
    pinnedSpells() {
      return this.knownSpells
        .filter((spell) => spell.pinned)
        .sort((a, b) => (a.pinOrder || 99) - (b.pinOrder || 99));
    },
    tabs() {
      return [
        { id: "book", label: "Księga", count: this.knownSpells.length },
        {
          id: "learn",
          label: "Nauka",
          count: this.learning.requests.filter(
            (item) => item.status === "pending",
          ).length,
        },
        { id: "cast", label: "Rzucanie", count: null },
        {
          id: "rituals",
          label: "Rytuały",
          count: this.data?.rituals?.length || 0,
        },
        {
          id: "history",
          label: "Historia",
          count: this.data?.history?.length || 0,
        },
      ];
    },
    diceOptions() {
      return Array.from(
        { length: Math.max(0, Number(this.character.magic) || 0) },
        (_value, index) => index + 1,
      );
    },
    matchingIngredients() {
      return matchingSpellIngredients(
        this.selectedSpell,
        this.data?.ingredients || [],
      );
    },
    selectedIngredient() {
      return (
        this.matchingIngredients.find(
          (item) => `${item.kind}:${item.id}` === this.selectedIngredientKey,
        ) || null
      );
    },
    declaredBonus() {
      return (
        (this.selectedIngredient
          ? this.selectedSpell?.ingredient?.bonus || 0
          : 0) + (this.activeCast?.channel?.bonus || 0)
      );
    },
    activeCast() {
      return this.storeBook.activeCast || null;
    },
    castResult() {
      return this.activeCast?.result?.spell ? this.activeCast.result : null;
    },
    canCast() {
      return (
        this.capabilities.canCast === true &&
        Boolean(this.selectedSpell) &&
        this.powerDice >= 1 &&
        this.powerDice <= Number(this.character.magic)
      );
    },
    castButtonLabel() {
      if (
        this.activeCast?.spellId === this.selectedSpell?.id &&
        this.activeCast.status === "casting"
      )
        return `Kontynuuj rzucanie · ${this.activeCast.actionsCompleted}/${this.activeCast.actionsRequired}`;
      return "Rzuć zaklęcie";
    },
    selectedTradition() {
      return (
        this.learning.traditions.find(
          (item) => item.id === Number(this.selectedTraditionId),
        ) || null
      );
    },
    availablePaths() {
      return this.selectedTradition?.paths || [];
    },
    selectedPath() {
      return (
        this.availablePaths.find(
          (item) => item.id === Number(this.selectedPathId),
        ) || null
      );
    },
    errorMessage() {
      return (
        this.error?.payload?.message || this.error?.message || "Nieznany błąd."
      );
    },
  },
  watch: {
    data: {
      immediate: true,
      handler(value) {
        if (!value) return;
        const requestedSpellId = Number(this.initialSpellId);
        if (
          !this.initialSpellHandled &&
          this.knownSpells.some((spell) => spell.id === requestedSpellId)
        ) {
          this.selectedSpellId = requestedSpellId;
          this.activeTab = "cast";
          this.initialSpellHandled = true;
        } else if (
          !this.knownSpells.some(
            (spell) => spell.id === Number(this.selectedSpellId),
          )
        )
          this.selectedSpellId = this.knownSpells[0]?.id || null;
        this.powerDice = Math.max(
          1,
          Math.min(Number(this.character.magic) || 1, this.powerDice),
        );
      },
    },
    selectedSpell(value) {
      this.personalNote = value?.note || "";
      this.selectedIngredientKey = "";
    },
    selectedTraditionId() {
      this.selectedPathId = null;
    },
    initialSpellId(value) {
      this.initialSpellHandled = false;
      const spellId = Number(value);
      if (!this.knownSpells.some((spell) => spell.id === spellId)) return;
      this.selectedSpellId = spellId;
      this.activeTab = "cast";
      this.initialSpellHandled = true;
    },
    characterId: "initialize",
    campaignId: "initialize",
  },
  created() {
    this.initialize();
  },
  methods: {
    async initialize() {
      await ensureMagicStoreModule(this.$store);
      this.moduleReady = true;
      await this.load();
    },
    async load() {
      try {
        await this.$store.dispatch("magic/load", {
          campaignId: this.campaignId,
          characterId: this.characterId,
        });
      } catch (_error) {
        /* visible state */
      }
    },
    selectSpell(id) {
      this.selectedSpellId = id;
    },
    openPinned(id) {
      this.selectedSpellId = id;
      this.activeTab = "cast";
    },
    async perform(action, success = "") {
      this.busy = true;
      this.actionError = "";
      this.notice = "";
      try {
        const result = await action();
        this.notice = success;
        return result;
      } catch (error) {
        this.actionError =
          error?.payload?.message ||
          error?.message ||
          "Operacja nie powiodła się.";
        return null;
      } finally {
        this.busy = false;
      }
    },
    togglePreference(field) {
      if (!this.selectedSpell) return;
      return this.perform(async () => {
        const result = await this.$store.dispatch("magic/preference", {
          campaignId: this.campaignId,
          characterId: this.characterId,
          changes: {
            spellId: this.selectedSpell.id,
            favorite:
              field === "favorite"
                ? !this.selectedSpell.favorite
                : this.selectedSpell.favorite,
            pinned:
              field === "pinned"
                ? !this.selectedSpell.pinned
                : this.selectedSpell.pinned,
            note: this.personalNote,
          },
        });
        if (field === "pinned")
          window.dispatchEvent(
            new CustomEvent("blatyrpg:magic-pins-changed", {
              detail: {
                campaignId: Number(this.campaignId),
                characterId: Number(this.characterId),
              },
            }),
          );
        return result;
      }, "Zapisano ustawienia czaru.");
    },
    saveNote() {
      return this.perform(
        () =>
          this.$store.dispatch("magic/preference", {
            campaignId: this.campaignId,
            characterId: this.characterId,
            changes: {
              spellId: this.selectedSpell.id,
              favorite: this.selectedSpell.favorite,
              pinned: this.selectedSpell.pinned,
              note: this.personalNote,
            },
          }),
        "Zapisano notatkę.",
      );
    },
    requestPath() {
      return this.perform(
        () =>
          this.$store.dispatch("magic/learn", {
            campaignId: this.campaignId,
            characterId: this.characterId,
            request: {
              type: "path",
              pathId: this.selectedPathId,
              note: this.learningNote,
              idempotencyKey: newIdempotencyKey("path"),
            },
          }),
        "Zgłoszono wybór ścieżki. PD nie zostały pobrane.",
      );
    },
    requestSpell() {
      return this.perform(
        () =>
          this.$store.dispatch("magic/learn", {
            campaignId: this.campaignId,
            characterId: this.characterId,
            request: {
              type: "spell",
              spellId: this.selectedLearningSpellId,
              source: this.learningSource,
              note: this.learningNote,
              idempotencyKey: newIdempotencyKey("learn"),
            },
          }),
        "Zgłoszono naukę. 100 PD zostanie pobrane wyłącznie po zgodzie MG.",
      );
    },
    decideLearning(requestId, decision) {
      return this.perform(
        () =>
          this.$store.dispatch("magic/decideLearning", {
            campaignId: this.campaignId,
            characterId: this.characterId,
            requestId,
            decision: {
              decision,
              idempotencyKey: newIdempotencyKey("decision"),
            },
          }),
        decision === "approve"
          ? "Zatwierdzono naukę i rozliczono ją atomowo."
          : "Odrzucono zgłoszenie bez wydawania PD.",
      );
    },
    declaration() {
      return {
        spellId: this.selectedSpell.id,
        powerDice: this.powerDice,
        ingredient: this.selectedIngredient
          ? {
              kind: this.selectedIngredient.kind,
              id: this.selectedIngredient.id,
            }
          : null,
        targets: this.castTargets
          ? [{ type: "descriptive", label: this.castTargets }]
          : [],
        idempotencyKey: newIdempotencyKey("cast"),
      };
    },
    async openCast() {
      if (
        this.activeCast?.spellId === this.selectedSpell.id &&
        ["declared", "casting"].includes(this.activeCast.status)
      )
        return this.activeCast;
      return this.$store.dispatch("magic/createCast", {
        campaignId: this.campaignId,
        characterId: this.characterId,
        declaration: this.declaration(),
      });
    },
    channelMagic() {
      return this.perform(async () => {
        const cast = await this.openCast();
        return this.$store.dispatch("magic/channel", {
          campaignId: this.campaignId,
          characterId: this.characterId,
          castId: cast.id,
        });
      }, "Splatanie rozstrzygnięte przez serwer. Rzucanie musi być następną akcją.");
    },
    castSpell() {
      return this.perform(async () => {
        let cast = await this.openCast();
        if (cast.status === "casting") {
          cast = await this.$store.dispatch("magic/advanceCast", {
            campaignId: this.campaignId,
            characterId: this.characterId,
            castId: cast.id,
          });
          if (cast.status === "casting") return cast;
        }
        return this.$store.dispatch("magic/resolveCast", {
          campaignId: this.campaignId,
          characterId: this.characterId,
          castId: cast.id,
        });
      }, "Serwer rozstrzygnął próbę i zapisał ją w historii.");
    },
    saveRitual() {
      return this.perform(
        () =>
          this.$store.dispatch("magic/createRitual", {
            campaignId: this.campaignId,
            characterId: this.characterId,
            ritual: {
              name: this.ritualName,
              effect: this.ritualEffect,
              requirements: this.ritualLines(this.ritualRequirements),
              ingredients: this.ritualLines(this.ritualIngredients),
              castingTime: this.ritualCastingTime,
              knownConsequences: this.ritualConsequences,
            },
          }),
        "Zapisano koncept. Nie jest jeszcze poznanym rytuałem.",
      );
    },
    ritualLines(value) {
      return String(value || "")
        .split(/\r?\n/u)
        .map((item) => item.trim())
        .filter(Boolean);
    },
    learningStatus(status) {
      return (
        {
          pending: "Oczekuje na MG",
          approved: "Zatwierdzone",
          rejected: "Odrzucone",
        }[status] || status
      );
    },
    manifestationLabel(severity) {
      return (
        {
          minor: "Pomniejsza manifestacja",
          major: "Poważna manifestacja",
          catastrophic: "Katastrofalna manifestacja",
          undefined: "Wymaga decyzji MG",
        }[severity] || severity
      );
    },
    historyTitle(type) {
      return (
        {
          learning_requested: "Zgłoszenie nauki",
          learning_approved: "Nauka zatwierdzona",
          learning_rejected: "Nauka odrzucona",
          channel_resolved: "Splatanie magii",
          cast_advanced: "Długie rzucanie",
          cast_resolved: "Rzut zaklęcia",
          cast_cancelled: "Rzucanie przerwane",
          ritual_concept_created: "Koncept rytuału",
        }[type] || type
      );
    },
    historyMessage(entry) {
      return (
        entry.data.message ||
        entry.data.spell?.name ||
        (entry.data.spellSucceeded === true
          ? "Czar udany."
          : entry.data.spellSucceeded === false
            ? "Czar nieudany."
            : "Zapisano zdarzenie magiczne.")
      );
    },
    formatDate(value) {
      if (!value) return "";
      return new Intl.DateTimeFormat("pl", {
        dateStyle: "short",
        timeStyle: "short",
      }).format(new Date(String(value).replace(" ", "T") + "Z"));
    },
  },
};
</script>

<style src="./hero-spellbook.css"></style>
