const moneyFormatters = new Map<string, Intl.NumberFormat>()

/** Amounts come from the API in minor units (kopecks / cents). */
export function money(minor: number | null | undefined, currency = 'UAH'): string {
  if (minor == null) return '—'
  // Whole amounts read better without ",00"; keep kopecks when there are some.
  const digits = minor % 100 === 0 ? 0 : 2
  const key = `${currency}:${digits}`
  let formatter = moneyFormatters.get(key)
  if (!formatter) {
    formatter = new Intl.NumberFormat('uk-UA', {
      style: 'currency',
      currency,
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
    })
    moneyFormatters.set(key, formatter)
  }
  return formatter.format(minor / 100)
}

export function dateLong(iso: string): string {
  return new Date(iso).toLocaleDateString('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

export function time(iso: string): string {
  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

export function dateParts(iso: string): { day: string; month: string } {
  const d = new Date(iso)
  return {
    day: String(d.getDate()).padStart(2, '0'),
    month: d.toLocaleDateString('en-GB', { month: 'short' }).toUpperCase(),
  }
}

/** ISO string -> value for <input type="datetime-local"> in the browser's zone. */
export function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export function fromLocalInput(value: string): string | null {
  return value ? new Date(value).toISOString() : null
}

export const providerLabels: Record<string, string> = {
  liqpay: 'LiqPay',
  stripe: 'Stripe',
  fake: 'Test payment',
}
