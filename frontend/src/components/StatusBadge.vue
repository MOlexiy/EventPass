<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const tone = computed(() => {
  switch (props.status) {
    case 'paid':
    case 'published':
    case 'valid':
    case 'ok':
      return 'badge-ok'
    case 'pending':
    case 'draft':
    case 'used':
      return 'badge-warn'
    case 'cancelled':
    case 'expired':
    case 'refunded':
    case 'voided':
      return 'badge-bad'
    default:
      return ''
  }
})
</script>

<template>
  <span class="badge" :class="tone">{{ status }}</span>
</template>
