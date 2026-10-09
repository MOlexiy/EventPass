<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppHeader from '@/components/AppHeader.vue'
import ToastHost from '@/components/ToastHost.vue'
import VerifyBanner from '@/components/VerifyBanner.vue'
</script>

<template>
  <AppHeader />
  <VerifyBanner />
  <main>
    <RouterView />
  </main>
  <footer class="footer">
    <div class="container row">
      <span>EventPass · demo project · Laravel + Vue 3</span>
      <span class="spacer" />
      <a href="https://github.com/MOlexiy/EventPass" target="_blank" rel="noopener">GitHub</a>
    </div>
  </footer>
  <ToastHost />
</template>

<style scoped>
main {
  min-height: calc(100vh - 140px);
}

.footer {
  border-top: 1px solid var(--line);
  padding: 20px 0;
  font-size: 0.85rem;
  color: var(--muted);
}
</style>
