<template>
  <div class="dice-view">
    <DiceRoller class="dice-roller" />
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
