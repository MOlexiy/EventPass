<script setup lang="ts">
import { ref, watchEffect } from 'vue'
import QRCode from 'qrcode'

const props = withDefaults(defineProps<{ value: string; size?: number }>(), { size: 168 })
const src = ref('')

watchEffect(async () => {
  src.value = await QRCode.toDataURL(props.value, {
    width: props.size * 2, // crisp on high-DPI screens
    margin: 1,
    color: { dark: '#1c1b19', light: '#fffdf8' },
  })
})
</script>

<template>
  <img :src="src" :width="size" :height="size" :alt="`QR code ${value}`" class="qr" />
</template>

<style scoped>
.qr {
  image-rendering: pixelated;
  border-radius: 6px;
}
</style>
