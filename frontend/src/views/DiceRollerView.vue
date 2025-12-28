<template>
  <div class="dice-view">
    <DiceRoller 
      class="dice-roller"
      :autoStart="true"
      :chatEnabled="false"
      :diceBoxDimensions="{ width: 1878, height: 923 }"
      :diceSelectorDimensions="{ w: 1878, h: 923 }"
      :diceScaleThrow="2"
      :diceScaleSelector="1.2"
      :diceDisplayList="['dc','d4','d6','d8','d10','D10','d100','d12','d20']"
    />
  </div>
</template>

<script>
import DiceRoller from "@/components/dice/DiceRoller.vue";

export default {
  name: "DiceRollerView",
  components: {
    DiceRoller,
  },
};
</script>

<style scoped>
.dice-view {
  height: 100vh;
  width: 100%;
}

.dice-roller {
  height: 100%;
  width: 100%;
}
</style>
