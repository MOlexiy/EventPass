import axios, { AxiosError } from 'axios'

export const http = axios.create({
  baseURL: '/',
  withCredentials: true,
  // Laravel puts the CSRF token into the XSRF-TOKEN cookie; axios echoes it
  // back in the X-XSRF-TOKEN header.
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

let csrfReady: Promise<unknown> | null = null

/** Sanctum SPA flow: get the CSRF cookie once before the first state-changing call. */
export function ensureCsrfCookie(force = false): Promise<unknown> {
  if (!csrfReady || force) {
    csrfReady = http.get('/sanctum/csrf-cookie')
  }
  return csrfReady
}

http.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()
  if (!['get', 'head', 'options'].includes(method) && !config.url?.includes('csrf-cookie')) {
    await ensureCsrfCookie()
  }
  return config
})

http.interceptors.response.use(undefined, async (error: AxiosError) => {
  // 419 = CSRF token expired (e.g. after a long idle tab): refresh and retry once.
  const config = error.config as (typeof error.config & { _retried?: boolean }) | undefined
  if (error.response?.status === 419 && config && !config._retried) {
    config._retried = true
    await ensureCsrfCookie(true)
    return http.request(config)
  }
  return Promise.reject(error)
})

export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/** Field errors from a 422 response, flattened to the first message per field. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) return {}
  const errors = (error.response.data as ApiErrorBody).errors ?? {}
  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages[0] ?? '']),
  )
}

export function errorMessage(
  error: unknown,
  fallback = 'Something went wrong. Please try again.',
): string {
  if (axios.isAxiosError(error)) {
    const body = error.response?.data as ApiErrorBody | undefined
    if (body?.message) return body.message
    if (!error.response) return 'Cannot reach the server. Is the API running?'
  }
  return fallback
}

export function statusOf(error: unknown): number | undefined {
  return axios.isAxiosError(error) ? error.response?.status : undefined
}
