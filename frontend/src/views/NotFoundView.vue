<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="container page">
    <div class="empty">
      <p class="eyebrow">404</p>
      <h2>This page has no ticket</h2>
      <RouterLink to="/" class="btn">Back to events</RouterLink>
    </div>
  </div>
</template>
