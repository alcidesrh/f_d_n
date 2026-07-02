<template>
	<div style="display:none" />
</template>

<script setup lang="ts">
import { watch, onUnmounted } from 'vue'

const store = useProfilerStore()
let currentToken: string | null = null
let previousContainer: HTMLElement | null = null
let cssInjected = false

const origin = new URL(config.ENTRYPOINT).origin

function injectSfjs() {
	if ((window as any).__SfjsLoaded) return
	;(window as any).__SfjsLoaded = true

	const hasClass = (el: Element, cssClass: string) => el.classList.contains(cssClass)
	const removeClass = (el: Element, cssClass: string) => el.classList.remove(cssClass)
	const addClass = (el: Element, cssClass: string) => el.classList.add(cssClass)
	const toggleClass = (el: Element, cssClass: string) => el.classList.toggle(cssClass)

	const psk = 'symfony/profiler/'
	const getPref = (name: string) => localStorage.getItem(psk + name)
	const setPref = (name: string, value: string) => localStorage.setItem(psk + name, value)
	const addEventListener = (el: any, event: string, cb: any, opts?: any) => el.addEventListener(event, cb, opts || false)

	const handleBlockHover = (block: Element, action: string) => {
		const content = block.querySelector('.sf-toolbar-info') as HTMLElement
		if (content) {
			content.style.display = action === 'show' ? 'block' : 'none'
		}
	}

	const setToolbarClasses = (token: string, opened: boolean) => {
		const el = document.getElementById('sfwdt' + token)
		if (!el) return
		if (opened) {
			addClass(el, 'sf-toolbar-opened')
			removeClass(el, 'sf-toolbar-closed')
		} else {
			addClass(el, 'sf-toolbar-closed')
			removeClass(el, 'sf-toolbar-opened')
		}
	}

	const showToolbar = (token: string) => {
		setToolbarClasses(token, true)
	}

	const hideToolbar = (token: string) => {
		setToolbarClasses(token, false)
	}

	const toggleToolbar = (token: string) => {
		const el = document.getElementById('sfwdt' + token)
		if (!el) return
		const isClosed = hasClass(el, 'sf-toolbar-closed')
		setPref('toolbar/displayState', isClosed ? 'opened' : 'closed')
		setToolbarClasses(token, isClosed)
	}

	const initToolbar = (token: string) => {
		showToolbar(token)

		const toggle = document.getElementById('sfToolbarToggleButton-' + token)
		if (toggle) {
			addEventListener(toggle, 'click', () => toggleToolbar(token))
		}

		const blocks = document.querySelectorAll(`#sfwdt${token} .sf-toolbar-block`)
		for (let i = 0; i < blocks.length; i++) {
			;(function (block) {
				addEventListener(block, 'mouseenter', () => handleBlockHover(block, 'show'))
				addEventListener(block, 'mouseleave', () => handleBlockHover(block, 'hide'))
			})(blocks[i])
		}
	}

	;(window as any).Sfjs = {
		hasClass,
		removeClass,
		addClass,
		toggleClass,
		getPreference: getPref,
		setPreference: setPref,
		addEventListener,
		showToolbar,
		hideToolbar,
		toggleToolbar,
		initToolbar,
		handleBlockHover,
	}
}

async function injectToolbarCss() {
	if (cssInjected) return
	try {
		const res = await fetch(`${origin}/_wdt/styles`)
		if (!res.ok) return
		const css = await res.text()
		const style = document.createElement('style')
		style.textContent = css
		style.id = 'sf-toolbar-styles'
		document.head.appendChild(style)
		cssInjected = true
	} catch {
		// ignore
	}
}

watch(() => store.debugToken, async (token) => {
	if (!token || token === currentToken) return
	currentToken = token

	injectSfjs()
	await injectToolbarCss()

	try {
		const res = await fetch(`${origin}/_wdt/${token}`)
		if (!res.ok) return
		const html = await res.text()

		const wrapper = document.createElement('div')
		wrapper.className = 'sf-toolbar sf-toolbar-opened'
		wrapper.id = 'sfwdt' + token
		wrapper.innerHTML = html

		if (previousContainer) previousContainer.remove()
		previousContainer = wrapper
		document.body.appendChild(wrapper)

		;(window as any).Sfjs.initToolbar(token)
	} catch {
		// ignore
	}
}, { immediate: true })

onUnmounted(() => {
	if (previousContainer) {
		previousContainer.remove()
		previousContainer = null
	}
	const styleEl = document.getElementById('sf-toolbar-styles')
	if (styleEl) {
		styleEl.remove()
		cssInjected = false
	}
	currentToken = null
})
</script>
