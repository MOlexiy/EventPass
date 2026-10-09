<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api'
import { errorMessage, fieldErrors } from '@/api/http'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const form = reactive({
  token: String(route.query.token ?? ''),
  email: String(route.query.email ?? ''),
  password: '',
  password_confirmation: '',
})
const errors = ref<Record<string, string>>({})
const error = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  errors.value = {}
  error.value = ''
  try {
    await authApi.resetPassword({ ...form })
    toast.success('Password changed. Sign in with the new one.')
    router.push({ name: 'login' })
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
      <h2>Choose a new password</h2>
      <div v-if="!form.token" class="alert alert-error">
        This link is incomplete. Request a new one.
      </div>
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <form @submit.prevent="submit">
        <div class="field">
          <label for="email">Email</label>
          <input id="email" v-model="form.email" class="input" type="email" required />
          <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
        </div>
        <div class="field">
          <label for="password">New password</label>
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
            required
          />
        </div>
        <button class="btn btn-block" :disabled="busy || !form.token">Save password</button>
      </form>
    </div>
  </div>
</template>
