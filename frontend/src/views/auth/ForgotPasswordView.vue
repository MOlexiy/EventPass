<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { authApi } from '@/api'
import { errorMessage, fieldErrors } from '@/api/http'

const email = ref('')
const sent = ref('')
const error = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  error.value = ''
  try {
    sent.value = (await authApi.forgotPassword(email.value)).message
  } catch (e) {
    error.value = fieldErrors(e).email ?? errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="narrow page">
    <div class="card auth-card">
      <h2>Reset password</h2>
      <p class="muted">Enter your email and we will send a link to choose a new password.</p>
      <div v-if="sent" class="alert alert-ok">{{ sent }}</div>
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <form v-if="!sent" @submit.prevent="submit">
        <div class="field">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="email"
            class="input"
            type="email"
            autocomplete="email"
            required
          />
        </div>
        <button class="btn btn-block" :disabled="busy">Send link</button>
      </form>
      <p class="muted" style="margin: 20px 0 0; text-align: center">
        <RouterLink to="/login">Back to sign in</RouterLink>
      </p>
    </div>
  </div>
</template>
