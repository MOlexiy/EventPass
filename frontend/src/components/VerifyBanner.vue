<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const sending = ref(false)

// The API redirects here with ?verified=1 after the email link is opened.
onMounted(async () => {
  await router.isReady()
  if (route.query.verified === undefined) return
  if (route.query.verified === '1') {
    toast.success('Email confirmed. You can buy tickets now.')
    await auth.refresh()
  } else {
    toast.error('That verification link is invalid or expired.')
  }
  router.replace({ query: { ...route.query, verified: undefined } })
})

async function resend() {
  sending.value = true
  try {
    await authApi.resendVerification()
    toast.success(`We sent a new link to ${auth.user?.email}.`)
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div v-if="auth.isLoggedIn && !auth.isVerified" class="banner">
    <div class="container row">
      <span>Confirm your email to buy tickets. Check your inbox for the link.</span>
      <span class="spacer" />
      <button class="btn btn-sm btn-ghost" :disabled="sending" @click="resend">Resend link</button>
    </div>
  </div>
</template>

<style scoped>
.banner {
  background: var(--warn-bg);
  color: var(--warn);
  padding: 10px 0;
  font-size: 0.92rem;
}
</style>
