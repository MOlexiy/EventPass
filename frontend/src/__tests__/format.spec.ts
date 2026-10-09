import { describe, expect, it } from 'vitest'
import { money } from '@/utils/format'

// The currency sign differs between ICU builds (₴ or грн), so only the number is checked.
const digits = (s: string) => s.replace(/[^\d,]/g, '')

describe('money', () => {
  it('formats kopecks as hryvnias', () => {
    expect(digits(money(50000))).toBe('500')
    expect(digits(money(12345))).toBe('123,45')
  })

  it('shows a dash for missing values', () => {
    expect(money(null)).toBe('—')
  })
})
