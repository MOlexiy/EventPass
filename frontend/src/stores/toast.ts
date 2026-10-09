import { ref } from 'vue'
import { defineStore } from 'pinia'

export interface Toast {
  id: number
  kind: 'success' | 'error' | 'info'
  text: string
}

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  let nextId = 1

  function push(text: string, kind: Toast['kind'] = 'info', timeout = 4500) {
    const id = nextId++
    toasts.value.push({ id, kind, text })
    window.setTimeout(() => dismiss(id), timeout)
  }

  function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return {
    toasts,
    dismiss,
    success: (text: string) => push(text, 'success'),
    error: (text: string) => push(text, 'error', 6500),
    info: (text: string) => push(text, 'info'),
  }
})
