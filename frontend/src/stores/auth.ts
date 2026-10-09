import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '@/api'
import { statusOf } from '@/api/http'
import type { User } from '@/api/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  let loading: Promise<void> | null = null

  const isLoggedIn = computed(() => user.value !== null)
  const isOrganizer = computed(
    () => user.value?.role === 'organizer' || user.value?.role === 'admin',
  )
  const isVerified = computed(() => user.value?.email_verified ?? false)

  /** Restores the session on page load; the router waits for it. */
  function init(): Promise<void> {
    loading ??= fetchUser().catch((error) => {
      loading = null // API unreachable: try again on the next navigation
      throw error
    })
    return loading
  }

  async function fetchUser(): Promise<void> {
    try {
      user.value = await authApi.me()
    } catch (error) {
      user.value = null
      if (statusOf(error) !== 401) throw error
    }
  }

  async function login(email: string, password: string, remember = true) {
    user.value = await authApi.login({ email, password, remember })
  }

  async function register(payload: Parameters<typeof authApi.register>[0]) {
    user.value = await authApi.register(payload)
  }

  async function logout() {
    await authApi.logout()
    user.value = null
  }

  return {
    user,
    isLoggedIn,
    isOrganizer,
    isVerified,
    init,
    refresh: fetchUser,
    login,
    register,
    logout,
  }
})
