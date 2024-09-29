<template>
  <div
    :class="{ 'active': loading }"
    class="loader"
  >
    <img
      src="/img/loading/spinning-banana.gif"
      alt="Loading..."
    >
  </div>
</template>
<script setup>
import { ref } from 'vue'

const loading = ref(false)

window.showLoading = () => {
  loading.value = true
}

window.hideLoading = () => {
  loading.value = false
}
</script>
<style scoped>
.loader {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  opacity: 0;
  transition: opacity 0.5s;
  pointer-events: none;
}

.loader.active {
  opacity: 1;
}

.loader img {
  width: 50px;
  transform: translateY(100vh);
  transition: transform 0.3s ease-in-out;
}

.loader.active img {
  transform: translateX(0);
}
</style>
