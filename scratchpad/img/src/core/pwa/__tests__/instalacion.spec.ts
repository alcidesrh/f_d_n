import { describe, expect, it } from 'vitest'
import { pideGuiaIos } from '../instalacion'

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'
const IPAD = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15'
const ANDROID = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'

describe('pideGuiaIos', () => {
  it('pide la guía en un iPhone sin la app instalada', () => {
    expect(pideGuiaIos(IPHONE, 5, false)).toBe(true)
  })

  it('reconoce el iPad, que se presenta como Mac, por la pantalla táctil', () => {
    expect(pideGuiaIos(IPAD, 5, false)).toBe(true)
    expect(pideGuiaIos(IPAD, 0, false)).toBe(false)
  })

  it('no la pide si ya está instalada ni fuera de iOS', () => {
    expect(pideGuiaIos(IPHONE, 5, true)).toBe(false)
    expect(pideGuiaIos(ANDROID, 5, false)).toBe(false)
  })
})
