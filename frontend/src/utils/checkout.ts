import type { Checkout } from '@/api/types'

/**
 * Send the browser to the payment page. Stripe and the test gateway give a
 * URL; LiqPay expects a POST form with "data" and "signature".
 */
export function goToCheckout(checkout: Checkout): void {
  if (checkout.type === 'redirect') {
    window.location.assign(checkout.url)
    return
  }

  const form = document.createElement('form')
  form.method = 'POST'
  form.action = checkout.url
  form.acceptCharset = 'utf-8'
  for (const [name, value] of Object.entries(checkout.fields)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    form.appendChild(input)
  }
  document.body.appendChild(form)
  form.submit()
}
