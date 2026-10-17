/**
 * Console — cotrim.dev v3 runtime behaviors.
 * Pulse/blink/hover are CSS-only. JS handles live uptime, active nav,
 * blog filter, and the reading-progress bar.
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

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(() => {
		initUptime();
		initActiveNav();
		initBlogFilter();
		initReadingProgress();
	});
})();
