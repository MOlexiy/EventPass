<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { errorMessage, fieldErrors } from '@/api/http'
import GoogleButton from '@/components/GoogleButton.vue'
import { googleSignInEnabled } from '@/utils/features'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'customer' as 'customer' | 'organizer',
})
const errors = ref<Record<string, string>>({})
const error = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  errors.value = {}
  error.value = ''
  try {
    await auth.register({ ...form })
    toast.success('Account created. We sent you a confirmation link.')
    router.push(
      (route.query.redirect as string) || (form.role === 'organizer' ? '/organizer' : '/'),
    )
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="narrow page">
    <div class="card auth-card">
      <p class="eyebrow">Join EventPass</p>
      <h2>Create account</h2>

      <div v-if="error" class="alert alert-error">{{ error }}</div>

      <form novalidate @submit.prevent="submit">
        <div class="roles" role="radiogroup" aria-label="Account type">
          <label :class="{ active: form.role === 'customer' }">
            <input v-model="form.role" type="radio" value="customer" />
            <strong>I buy tickets</strong>
            <span class="muted">Find events and pay online</span>
          </label>
          <label :class="{ active: form.role === 'organizer' }">
            <input v-model="form.role" type="radio" value="organizer" />
            <strong>I organize events</strong>
            <span class="muted">Sell tickets, scan guests</span>
          </label>
        </div>

        <div class="field">
          <label for="name">Name</label>
          <input id="name" v-model="form.name" class="input" autocomplete="name" required />
          <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
        </div>
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
          <label for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            class="input"
            type="password"
            autocomplete="new-password"
            required
          />
          <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
        </div>
        <div class="field">
          <label for="password2">Repeat password</label>
          <input
            id="password2"
            v-model="form.password_confirmation"
            class="input"
            type="password"
            autocomplete="new-password"
            required
          />
        </div>
        <button class="btn btn-block" :disabled="busy">
          {{ busy ? 'Creating…' : 'Create account' }}
        </button>
      </form>

      <template v-if="googleSignInEnabled">
        <div class="divider">or</div>
        <GoogleButton />
      </template>

      <p class="muted switch">
        Have an account?
        <RouterLink :to="{ name: 'login', query: route.query }">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.roles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 20px;
}

.roles label {
  display: grid;
  gap: 2px;
  padding: 14px;
  border: 1.5px solid var(--line);
  border-radius: 12px;
  cursor: pointer;
  font-size: 0.9rem;
}

.roles label.active {
  border-color: var(--ink);
  box-shadow: inset 0 0 0 1px var(--ink);
}

.roles input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.roles label:focus-within {
  outline: 3px solid color-mix(in srgb, var(--accent) 45%, transparent);
}

.switch {
  margin: 20px 0 0;
  text-align: center;
  font-size: 0.9rem;
}
</style>
