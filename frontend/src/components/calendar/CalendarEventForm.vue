<template>
  <form class="campaign-calendar__dialog" @submit.prevent="submit">
    <header>
      <h3>{{ event?.id ? "Edytuj wydarzenie" : "Nowe wydarzenie" }}</h3>
      <button type="button" aria-label="Zamknij" @click="$emit('cancel')">
        ×
      </button>
    </header>
    <div class="campaign-calendar__form-grid">
      <label class="is-wide"
        >Tytuł<input v-model.trim="draft.title" required maxlength="180"
      /></label>
      <label
        >Rodzaj<select v-model="draft.type">
          <option v-for="item in types" :key="item.key" :value="item.key">
            {{ item.label }}
          </option>
        </select></label
      >
      <label>Kolor<input v-model="draft.color" type="color" /></label>
      <label class="is-wide"
        >Opis<textarea
          v-model.trim="draft.description"
          rows="3"
          maxlength="20000"
        />
      </label>
      <label
        >Rok rozpoczęcia<input
          v-model.number="draft.startYear"
          type="number"
          min="1"
          required
      /></label>
      <label
        >Dzień rozpoczęcia<select v-model.number="draft.startDay" required>
          <option
            v-for="day in days(draft.startYear)"
            :key="day.dayOfYear"
            :value="day.dayOfYear"
          >
            {{ day.formatted }}
          </option>
        </select></label
      >
      <label v-if="!draft.allDay"
        >Godzina rozpoczęcia<input
          v-model="draft.startTime"
          type="time"
          required
      /></label>
      <label class="campaign-calendar__check"
        ><input v-model="draft.hasEnd" type="checkbox" /> Data
        zakończenia</label
      >
      <template v-if="draft.hasEnd">
        <label
          >Rok zakończenia<input
            v-model.number="draft.endYear"
            type="number"
            min="1"
            required
        /></label>
        <label
          >Dzień zakończenia<select v-model.number="draft.endDay" required>
            <option
              v-for="day in days(draft.endYear)"
              :key="day.dayOfYear"
              :value="day.dayOfYear"
            >
              {{ day.formatted }}
            </option>
          </select></label
        >
        <label v-if="!draft.allDay"
          >Godzina zakończenia<input
            v-model="draft.endTime"
            type="time"
            required
        /></label>
      </template>
      <label
        >Widoczność<select v-model="draft.visibility">
          <option
            v-for="item in visibilities"
            :key="item.key"
            :value="item.key"
          >
            {{ item.label }}
          </option>
        </select></label
      >
      <div
        v-if="draft.visibility === 'participants'"
        class="campaign-calendar__participants is-wide"
      >
        <span>Uczestnicy</span>
        <label v-for="member in members" :key="member.userId"
          ><input
            v-model="draft.participantUserIds"
            type="checkbox"
            :value="Number(member.userId)"
          />{{ member.username }}</label
        >
      </div>
      <label class="campaign-calendar__check"
        ><input v-model="draft.allDay" type="checkbox" /> Całodniowe</label
      >
      <label class="campaign-calendar__check"
        ><input v-model="draft.repeatYearly" type="checkbox" /> Powtarzaj
        corocznie</label
      >
    </div>
    <p v-if="error || serverError" class="campaign-calendar__form-error">
      {{ error || serverError }}
    </p>
    <footer>
      <button
        v-if="event?.id"
        type="button"
        class="is-danger"
        @click="$emit('delete', event)"
      >
        Usuń
      </button>
      <span />
      <button type="button" @click="$emit('cancel')">Anuluj</button>
      <button type="submit" class="is-brass">Zapisz</button>
    </footer>
  </form>
</template>

<script>
import {
  calendarDate,
  compareCalendarPosition,
  datesForYear,
} from "@/lib/calendar/calendarDate";
import {
  CALENDAR_EVENT_TYPES,
  CALENDAR_EVENT_VISIBILITIES,
} from "@/lib/calendar/calendarEventTypes";

const minutes = (time) => {
  const [hour, minute] = String(time || "00:00")
    .split(":")
    .map(Number);
  return hour * 60 + minute;
};

export default {
  name: "CalendarEventForm",
  props: {
    definition: { type: Object, required: true },
    selectedDate: { type: Object, required: true },
    event: { type: Object, default: null },
    members: { type: Array, default: () => [] },
    serverError: { type: String, default: "" },
  },
  emits: ["save", "delete", "cancel"],
  data() {
    const source = this.event;
    return {
      types: CALENDAR_EVENT_TYPES,
      visibilities: CALENDAR_EVENT_VISIBILITIES,
      error: "",
      draft: {
        title: source?.title || "",
        description: source?.description || "",
        type: source?.type || "story",
        color: source?.color || "#7b5b38",
        startYear: Number(source?.start?.year || this.selectedDate.year),
        startDay: Number(
          source?.start?.dayOfYear || this.selectedDate.dayOfYear,
        ),
        startTime: source?.start?.time || "08:00",
        hasEnd: Boolean(source?.end),
        endYear: Number(
          source?.end?.year || source?.start?.year || this.selectedDate.year,
        ),
        endDay: Number(
          source?.end?.dayOfYear ||
            source?.start?.dayOfYear ||
            this.selectedDate.dayOfYear,
        ),
        endTime: source?.end?.time || source?.start?.time || "08:00",
        visibility: source?.visibility || "all",
        participantUserIds: [...(source?.participantUserIds || [])].map(Number),
        allDay: source?.allDay ?? true,
        repeatYearly: source?.repeatYearly ?? false,
      },
    };
  },
  methods: {
    days(year) {
      return datesForYear(
        this.definition,
        Number(year) || this.selectedDate.year,
      );
    },
    submit() {
      this.error = "";
      const start = {
        year: Number(this.draft.startYear),
        dayOfYear: Number(this.draft.startDay),
        minute: this.draft.allDay ? 0 : minutes(this.draft.startTime),
      };
      const end = this.draft.hasEnd
        ? {
            year: Number(this.draft.endYear),
            dayOfYear: Number(this.draft.endDay),
            minute: this.draft.allDay ? 0 : minutes(this.draft.endTime),
          }
        : null;
      if (end && compareCalendarPosition(start, end) > 0) {
        this.error = "Zakończenie nie może być wcześniejsze niż rozpoczęcie.";
        return;
      }
      if (
        this.draft.visibility === "participants" &&
        !this.draft.participantUserIds.length
      ) {
        this.error = "Wybierz przynajmniej jednego uczestnika.";
        return;
      }
      this.$emit("save", {
        ...(this.event?.id
          ? { id: this.event.id, revision: this.event.revision }
          : {}),
        title: this.draft.title,
        description: this.draft.description,
        type: this.draft.type,
        start,
        end,
        color: this.draft.color,
        visibility: this.draft.visibility,
        participantUserIds: this.draft.participantUserIds,
        allDay: this.draft.allDay,
        repeatYearly: this.draft.repeatYearly,
      });
    },
    dateLabel(year, day) {
      return calendarDate(this.definition, year, day)?.formatted || "";
    },
  },
};
</script>
