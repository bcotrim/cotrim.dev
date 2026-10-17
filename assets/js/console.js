/**
 * Console — cotrim.dev v3 runtime behaviors.
 * Pulse/blink/hover are CSS-only. JS handles live uptime, active nav,
 * blog filter, reading-progress bar, the ASCII portrait render, the
 * whoami boot sequence, scroll-in stagger, and the card flashlight.
 */

(function () {
	'use strict';

	const pad = (n, w = 2) => String(n).padStart(w, '0');

	function tickUptime() {
		const nodes = document.querySelectorAll('[data-con-uptime]');
		if (!nodes.length) return;
		nodes.forEach((el) => {
			const since = el.getAttribute('data-since') || '2024-06-01T00:00:00Z';
			const ms = Date.now() - new Date(since).getTime();
			if (ms < 0) { el.textContent = '0d 00:00:00'; return; }
			const d = Math.floor(ms / 86400000);
			const h = Math.floor((ms % 86400000) / 3600000);
			const m = Math.floor((ms % 3600000) / 60000);
			const s = Math.floor((ms % 60000) / 1000);
			el.textContent = `${d}d ${pad(h)}:${pad(m)}:${pad(s)}`;
		});
	}

	function initUptime() {
		tickUptime();
		setInterval(tickUptime, 1000);
	}

	function initActiveNav() {
		const items = document.querySelectorAll('.con-nav .wp-block-navigation-item');
		if (!items.length) return;
		const cur = document.body.classList.contains('single-post') ? '/blog' : (window.location.pathname.replace(/\/+$/, '') || '/');
		items.forEach((li) => {
			const a = li.querySelector('a');
			if (!a) return;
			let href;
			try { href = new URL(a.href, window.location.origin).pathname.replace(/\/+$/, '') || '/'; }
			catch (_) { return; }
			const isExact = href === cur;
			const isRoot  = href === '/' && cur === '/';
			const isHomeSection = href !== '/' && cur.indexOf(href) === 0;
			if (isExact || isRoot || isHomeSection) {
				li.classList.add('is-current', 'current-menu-item');
			}
		});
	}

	function initBlogFilter() {
		const bar = document.querySelector('[data-con-filter]');
		if (!bar) return;
		const chips = bar.querySelectorAll('.con-chip');
		const list = document.querySelector('.con-postlist');
		if (!list) return;
		chips.forEach((chip) => {
			chip.addEventListener('click', () => {
				const tag = chip.getAttribute('data-filter') || 'all';
				chips.forEach((c) => c.classList.toggle('is-active', c === chip));
				const rows = list.querySelectorAll('[data-tag]');
				rows.forEach((r) => {
					const match = tag === 'all' || (r.getAttribute('data-tag') || '').split(' ').includes(tag);
					r.style.display = match ? '' : 'none';
				});
				// Re-evaluate year groups: hide a year header if all its rows are hidden.
				list.querySelectorAll('[data-year-group]').forEach((group) => {
					const visible = Array.from(group.querySelectorAll('[data-tag]')).some((r) => r.style.display !== 'none');
					group.style.display = visible ? '' : 'none';
				});
			});
		});
	}

	function initReadingProgress() {
		const bar = document.querySelector('[data-con-progress] .con-progress-fill');
		if (!bar) return;
		let ticking = false;
		function update() {
			const doc = document.documentElement;
			const max = doc.scrollHeight - window.innerHeight;
			const pct = max > 0 ? Math.min(100, Math.max(0, (window.scrollY / max) * 100)) : 0;
			bar.style.width = pct + '%';
			ticking = false;
		}
		window.addEventListener('scroll', () => {
			if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
		}, { passive: true });
		update();
	}

	const motion = () => document.documentElement.classList.contains('con-js');
	const GLYPHS = '.,:;-~=+*o#%@';
	const glyph = () => GLYPHS[(Math.random() * GLYPHS.length) | 0];

	function createPortrait() {
		const pre = document.querySelector('[data-con-portrait]');
		if (!pre) return null;
		const cells = [];
		let row = 0;
		pre.childNodes.forEach((node) => {
			if (node.nodeType === Node.TEXT_NODE) {
				row += (node.data.match(/\n/g) || []).length;
				return;
			}
			const text = node.firstChild;
			if (text) cells.push({ text, final: text.data, row });
		});
		const rows = row + 1;
		let running = false;

		function play(mode) {
			if (running) return;
			running = true;
			const glitch = mode === 'glitch';
			const duration = glitch ? 380 : 1100;
			cells.forEach((c) => {
				c.at = Array.from(c.final, () => glitch
					? (Math.random() < 0.18 ? Math.random() * duration : 0)
					: (c.row / rows) * 0.7 * duration + Math.random() * 0.3 * duration);
			});
			pre.classList.add('is-live');
			if (!glitch) {
				pre.classList.remove('is-rendering');
				void pre.offsetWidth;
				pre.classList.add('is-rendering');
			}
			const start = performance.now();
			let last = 0;
			function frame(now) {
				const t = now - start;
				if (t < duration) {
					if (now - last > 33) {
						last = now;
						cells.forEach((c) => {
							let out = '';
							for (let i = 0; i < c.final.length; i++) out += t >= c.at[i] ? c.final[i] : glyph();
							if (out !== c.text.data) c.text.data = out;
						});
					}
					requestAnimationFrame(frame);
					return;
				}
				cells.forEach((c) => { c.text.data = c.final; });
				pre.classList.remove('is-rendering');
				running = false;
			}
			requestAnimationFrame(frame);
		}

		pre.addEventListener('pointerenter', () => play('glitch'));
		return { play };
	}

	function initBoot(portrait, done) {
		const root = document.documentElement;
		const hero = document.querySelector('[data-con-hero]');
		if (!hero || !root.classList.contains('con-boot')) {
			if (portrait) portrait.play();
			done();
			return;
		}
		const cmd = hero.querySelector('[data-con-type]');
		const word = cmd.textContent;
		const caret = document.createElement('span');
		caret.className = 'con-caret con-caret--inline';
		caret.setAttribute('aria-hidden', 'true');
		cmd.textContent = '';
		cmd.classList.add('is-typing');
		cmd.after(caret);

		let i = 0;
		(function type() {
			if (i < word.length) {
				cmd.textContent += word[i++];
				setTimeout(type, 55 + Math.random() * 50);
				return;
			}
			setTimeout(() => {
				const items = hero.querySelectorAll('[data-boot]');
				items.forEach((el, n) => setTimeout(() => {
					el.classList.add('is-shown');
					if (el.hasAttribute('data-con-portrait') && portrait) portrait.play();
				}, n * 90));
				setTimeout(() => {
					caret.remove();
					root.classList.remove('con-boot');
					try { sessionStorage.setItem('con-booted', '1'); } catch (_) {}
					done();
				}, items.length * 90 + 200);
			}, 180);
		})();
	}

	function initStagger() {
		const targets = document.querySelectorAll('.con-postlist .con-row, .con-card, .con-project-card, .con-now-card, .con-kv > div');
		if (!targets.length) return;
		if (!('IntersectionObserver' in window)) {
			targets.forEach((el) => el.classList.add('is-in'));
			return;
		}
		const io = new IntersectionObserver((entries) => {
			let n = 0;
			entries.forEach((e) => {
				if (!e.isIntersecting) return;
				e.target.style.setProperty('--d', Math.min(n++, 12) * 35 + 'ms');
				e.target.classList.add('is-in');
				io.unobserve(e.target);
			});
		}, { rootMargin: '0px 0px -8% 0px' });
		targets.forEach((el) => io.observe(el));
	}

	function initFlashlight() {
		if (!window.matchMedia('(hover: hover)').matches) return;
		document.addEventListener('pointermove', (e) => {
			const card = e.target.closest && e.target.closest('.con-card, .con-project-card');
			if (!card) return;
			const r = card.getBoundingClientRect();
			card.style.setProperty('--mx', e.clientX - r.left + 'px');
			card.style.setProperty('--my', e.clientY - r.top + 'px');
		}, { passive: true });
	}

	function initMotion() {
		if (!motion()) return;
		initFlashlight();
		initBoot(createPortrait(), initStagger);
	}

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(() => {
		initUptime();
		initActiveNav();
		initBlogFilter();
		initReadingProgress();
		initMotion();
	});
})();
