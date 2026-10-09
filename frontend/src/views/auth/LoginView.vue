<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { errorMessage, fieldErrors } from '@/api/http'
import GoogleButton from '@/components/GoogleButton.vue'
import { googleSignInEnabled } from '@/utils/features'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '', remember: true })
const errors = ref<Record<string, string>>({})
const error = ref(
  route.query.error === 'google' ? 'Google sign-in failed. Try again or use email.' : '',
)
const busy = ref(false)

async function submit() {
  busy.value = true
  errors.value = {}
  error.value = ''
  try {
    await auth.login(form.email, form.password, form.remember)
    router.push((route.query.redirect as string) || '/')
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

const demo = [
  { label: 'Buyer', email: 'buyer@eventpass.test' },
  { label: 'Organizer', email: 'organizer@eventpass.test' },
]

function useDemo(email: string) {
  form.email = email
  form.password = 'password'
}
</script>

<template>
  <div class="narrow page">
    <div class="card auth-card">
      <p class="eyebrow">Welcome back</p>
      <h2>Sign in</h2>

      <div v-if="error" class="alert alert-error">{{ error }}</div>

      <form novalidate @submit.prevent="submit">
        <div class="field">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            class="input"
            type="email"
            autocomplete="email"
            required
          />
          <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
        </div>
        <div class="field">
          <div class="row">
            <label for="password">Password</label>
            <span class="spacer" />
            <RouterLink to="/forgot-password" class="muted small">Forgot?</RouterLink>
          </div>
          <input
            id="password"
            v-model="form.password"
            class="input"
            type="password"
            autocomplete="current-password"
            required
          />
          <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
        </div>
        <label class="row remember">
          <input v-model="form.remember" type="checkbox" /> Keep me signed in
        </label>
        <button class="btn btn-block" :disabled="busy">
          {{ busy ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <template v-if="googleSignInEnabled">
        <div class="divider">or</div>
        <GoogleButton />
      </template>

      <p class="muted small switch">
        New here?
        <RouterLink :to="{ name: 'register', query: route.query }">Create an account</RouterLink>
      </p>
    </div>

    <div class="demo">
      <span class="eyebrow">Demo accounts</span>
      <button
        v-for="d in demo"
        :key="d.email"
        class="btn btn-ghost btn-sm"
        type="button"
        @click="useDemo(d.email)"
      >
        {{ d.label }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.small {
  font-size: 0.85rem;
}

.remember {
  gap: 8px;
  margin-bottom: 18px;
  font-size: 0.92rem;
}

.switch {
  margin: 20px 0 0;
  text-align: center;
}

.demo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 20px;
}
</style>
