import { describe, expect, it, vi } from 'vitest'
import { goToCheckout } from '@/utils/checkout'

describe('goToCheckout', () => {
  it('posts a hidden form for LiqPay', () => {
    const submit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {})

    goToCheckout({
      type: 'form',
      url: 'https://www.liqpay.ua/api/3/checkout',
      fields: { data: 'abc', signature: 'xyz' },
    })

    const form = document.querySelector('form')!
    expect(form.method).toBe('post')
    expect(form.action).toBe('https://www.liqpay.ua/api/3/checkout')
    expect(new FormData(form).get('signature')).toBe('xyz')
    expect(submit).toHaveBeenCalledOnce()
  })
})
