import { createStore } from "vuex";
import jukebox from "./modules/jukebox";
import video from "./modules/video";
import voice from "./modules/voice";
import soundEffects from "./modules/soundEffects";
import calendar from "./modules/calendar";
import professions from "./modules/professions";

export default createStore({
  state: {},
  getters: {},
  mutations: {},
  actions: {},
  modules: { calendar, jukebox, professions, soundEffects, video, voice },
});
