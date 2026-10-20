(function () {
	'use strict';

	const root = document.getElementById('briefmanager-app');
	if (!root) {
		return;
	}

	const primary = root.dataset.primary || '#154A8A';
	root.style.setProperty('--bm-primary', primary);

	const token = document.querySelector('head > meta[name="requesttoken"]')?.getAttribute('content') || '';
	const api = (path) => OC.generateUrl('/apps/briefmanager/api' + path);
	const statusEl = document.getElementById('bm-status');

	function setStatus(msg, isError) {
		if (!statusEl) {
			return;
		}
		statusEl.hidden = !msg;
		statusEl.textContent = msg || '';
		statusEl.classList.toggle('is-error', !!isError);
	}

	function headers(json) {
		const h = { requesttoken: token, Accept: 'application/json' };
		if (json) {
			h['Content-Type'] = 'application/json';
		}
		return h;
	}

	function idemKey(prefix) {
		return (prefix || 'bm') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
	}

	const form = document.getElementById('bm-form');
	if (form) {
		form.addEventListener('submit', () => setStatus('Speichere …'));
	}
	document.querySelector('.bm-inline-form')?.addEventListener('submit', () => setStatus('Lege Brief an …'));

	// —— Persönliche Layouts ——
	const layoutSelectLetter = form?.querySelector('select[name="layout_id"]');
	const layoutPicker = document.getElementById('bm-layout-picker');
	const layoutStatus = document.getElementById('bm-layout-status');
	let layoutsCache = [];

	function setLayoutStatus(msg, isError) {
		if (!layoutStatus) {
			return;
		}
		layoutStatus.textContent = msg || '';
		layoutStatus.classList.toggle('is-error', !!isError);
	}

	function fillLayoutSelects() {
		if (layoutPicker) {
			const cur = layoutPicker.value;
			layoutPicker.innerHTML = '<option value="">— Neues Layout —</option>';
			layoutsCache.forEach((l) => {
				const o = document.createElement('option');
				o.value = String(l.id);
				o.textContent = l.name + (l.is_default ? ' · Standard' : '');
				layoutPicker.appendChild(o);
			});
			if (cur) {
				layoutPicker.value = cur;
			}
		}
		if (layoutSelectLetter) {
			const cur = layoutSelectLetter.value;
			layoutSelectLetter.innerHTML = '<option value="0">— Kein persönliches Layout (Verein/Organisation) —</option>';
			layoutsCache.forEach((l) => {
				const o = document.createElement('option');
				o.value = String(l.id);
				o.textContent = l.name + (l.is_default ? ' · Standard' : '');
				layoutSelectLetter.appendChild(o);
			});
			if (cur) {
				layoutSelectLetter.value = cur;
			}
		}
	}

	function layoutFormToPayload() {
		return {
			name: document.getElementById('bm-layout-name')?.value || '',
			sender_name: document.getElementById('bm-layout-sender')?.value || '',
			design: document.getElementById('bm-layout-design')?.value || 'ft',
			tagline: document.getElementById('bm-layout-tagline')?.value || '',
			address_extra: document.getElementById('bm-layout-extra')?.value || '',
			street: document.getElementById('bm-layout-street')?.value || '',
			zip: document.getElementById('bm-layout-zip')?.value || '',
			city: document.getElementById('bm-layout-city')?.value || '',
			phone: document.getElementById('bm-layout-phone')?.value || '',
			email: document.getElementById('bm-layout-email')?.value || '',
			website: document.getElementById('bm-layout-website')?.value || '',
			color_primary: document.getElementById('bm-layout-cprimary')?.value || '',
			color_gray: document.getElementById('bm-layout-cgray')?.value || '',
			color_accent: document.getElementById('bm-layout-caccent')?.value || '',
			is_default: document.getElementById('bm-layout-default')?.checked ? 1 : 0,
		};
	}

	function applyLayoutToForm(l) {
		document.getElementById('bm-layout-id').value = l ? String(l.id) : '';
		document.getElementById('bm-layout-name').value = l?.name || '';
		document.getElementById('bm-layout-sender').value = l?.sender_name || '';
		document.getElementById('bm-layout-design').value = l?.design || 'ft';
		document.getElementById('bm-layout-tagline').value = l?.tagline || '';
		document.getElementById('bm-layout-extra').value = l?.address_extra || '';
		document.getElementById('bm-layout-street').value = l?.street || '';
		document.getElementById('bm-layout-zip').value = l?.zip || '';
		document.getElementById('bm-layout-city').value = l?.city || '';
		document.getElementById('bm-layout-phone').value = l?.phone || '';
		document.getElementById('bm-layout-email').value = l?.email || '';
		document.getElementById('bm-layout-website').value = l?.website || '';
		if (l?.color_primary) {
			document.getElementById('bm-layout-cprimary').value = l.color_primary;
		}
		if (l?.color_gray) {
			document.getElementById('bm-layout-cgray').value = l.color_gray;
		}
		if (l?.color_accent) {
			document.getElementById('bm-layout-caccent').value = l.color_accent;
		}
		document.getElementById('bm-layout-default').checked = !!l?.is_default;
	}

	function loadLayouts() {
		return fetch(api('/layouts'), { headers: headers(false) })
			.then(async (r) => {
				const data = await r.json().catch(() => []);
				if (!r.ok) {
					throw new Error(data.error || ('HTTP ' + r.status));
				}
				layoutsCache = Array.isArray(data) ? data : [];
				fillLayoutSelects();
				return layoutsCache;
			});
	}

	layoutPicker?.addEventListener('change', () => {
		const id = layoutPicker.value;
		const l = layoutsCache.find((x) => String(x.id) === id);
		applyLayoutToForm(l || null);
	});

	document.getElementById('bm-layout-new')?.addEventListener('click', () => {
		if (layoutPicker) {
			layoutPicker.value = '';
		}
		applyLayoutToForm(null);
		document.getElementById('bm-layout-name').value = 'Mein Briefpapier';
		setLayoutStatus('Neues Layout – bitte speichern.');
	});

	document.getElementById('bm-layout-save')?.addEventListener('click', async () => {
		const id = document.getElementById('bm-layout-id')?.value || '';
		const payload = layoutFormToPayload();
		if (!payload.name.trim()) {
			setLayoutStatus('Name fehlt.', true);
			return;
		}
		setLayoutStatus('Speichere …');
		try {
			const r = await fetch(api(id ? '/layouts/' + encodeURIComponent(id) : '/layouts'), {
				method: id ? 'PUT' : 'POST',
				headers: headers(true),
				body: JSON.stringify(payload),
			});
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			const layoutId = data.id;
			const fileInput = document.getElementById('bm-layout-logo');
			if (fileInput?.files?.length && layoutId) {
				const fd = new FormData();
				fd.append('logo', fileInput.files[0]);
				const ur = await fetch(api('/layouts/' + encodeURIComponent(layoutId) + '/logo'), {
					method: 'POST',
					headers: { requesttoken: token, Accept: 'application/json' },
					body: fd,
				});
				const ud = await ur.json().catch(() => ({}));
				if (!ur.ok) {
					throw new Error(ud.error || 'Logo-Upload fehlgeschlagen');
				}
				fileInput.value = '';
			}
			await loadLayouts();
			if (layoutPicker) {
				layoutPicker.value = String(layoutId);
			}
			const saved = layoutsCache.find((x) => String(x.id) === String(layoutId));
			applyLayoutToForm(saved || data);
			setLayoutStatus('Layout gespeichert. Für PDF im Brief wählen und Brief speichern.');
		} catch (err) {
			setLayoutStatus(err.message || String(err), true);
		}
	});

	document.getElementById('bm-layout-delete')?.addEventListener('click', async () => {
		const id = document.getElementById('bm-layout-id')?.value || '';
		if (!id || !window.confirm('Dieses persönliche Layout wirklich löschen?')) {
			return;
		}
		setLayoutStatus('Lösche …');
		try {
			const r = await fetch(api('/layouts/' + encodeURIComponent(id)), {
				method: 'DELETE',
				headers: headers(false),
			});
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			applyLayoutToForm(null);
			await loadLayouts();
			setLayoutStatus('Gelöscht.');
		} catch (err) {
			setLayoutStatus(err.message || String(err), true);
		}
	});

	loadLayouts().catch((err) => setLayoutStatus(err.message || String(err), true));

	// —— Portokasse ——
	const portoStatus = document.getElementById('bm-porto-status');
	const portoHint = document.getElementById('bm-porto-hint');

	function refreshImStatus() {
		return fetch(api('/internetmarke/status'), { headers: headers(false) })
			.then(async (r) => {
				const data = await r.json().catch(() => ({}));
				if (!r.ok) {
					throw new Error(data.error || ('HTTP ' + r.status));
				}
				if (document.getElementById('bm-porto-user') && data.portokasse_username) {
					document.getElementById('bm-porto-user').value = data.portokasse_username;
				}
				if (portoHint) {
					portoHint.textContent =
						'Portokasse: ' + (data.portokasse_configured ? 'hinterlegt' : 'fehlt') +
						' · App-API: ' + (data.app_configured ? 'ok' : 'fehlt') +
						' · Live-Kauf: ' + (data.allow_live_purchase ? 'an' : 'aus') +
						'. Passwort wird nie zurückgeliefert.';
				}
				const purchaseBtn = document.getElementById('bm-im-purchase');
				if (purchaseBtn) {
					purchaseBtn.disabled = !data.allow_live_purchase;
					purchaseBtn.title = data.allow_live_purchase
						? 'Kostenpflichtig – Preis vorher bestätigen'
						: 'Live-Kauf vom Admin deaktiviert';
				}
				const box = document.getElementById('bm-internetmarke-api');
				if (box) {
					box.dataset.allowPurchase = data.allow_live_purchase ? '1' : '0';
				}
				return data;
			});
	}

	document.getElementById('bm-porto-save')?.addEventListener('click', async () => {
		const username = document.getElementById('bm-porto-user')?.value || '';
		const password = document.getElementById('bm-porto-pass')?.value || '';
		portoStatus.textContent = 'Speichere …';
		try {
			const body = { username };
			if (password !== '') {
				body.password = password;
			}
			const r = await fetch(api('/internetmarke/portokasse'), {
				method: 'PUT',
				headers: headers(true),
				body: JSON.stringify(body),
			});
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			document.getElementById('bm-porto-pass').value = '';
			portoStatus.textContent = 'Gespeichert' + (data.password_set ? ' (Passwort gesetzt)' : ' (Passwort unverändert)');
			await refreshImStatus();
		} catch (err) {
			portoStatus.textContent = err.message || String(err);
			portoStatus.classList.add('is-error');
		}
	});

	document.getElementById('bm-porto-clear')?.addEventListener('click', async () => {
		if (!window.confirm('Portokasse-Zugangsdaten wirklich löschen?')) {
			return;
		}
		const r = await fetch(api('/internetmarke/portokasse'), { method: 'DELETE', headers: headers(false) });
		const data = await r.json().catch(() => ({}));
		portoStatus.textContent = r.ok ? 'Gelöscht.' : (data.error || 'Fehler');
		document.getElementById('bm-porto-user').value = '';
		document.getElementById('bm-porto-pass').value = '';
		await refreshImStatus();
	});

	refreshImStatus().catch(() => {
		if (portoHint) {
			portoHint.textContent = 'Status konnte nicht geladen werden (ohne Schlüssel normal).';
		}
	});

	// —— Internetmarke Katalog / Preview / Kauf ——
	const imBox = document.getElementById('bm-internetmarke-api');
	const imProduct = document.getElementById('bm-im-product');
	const imPrice = document.getElementById('bm-im-price');
	const imStatus = document.getElementById('bm-im-status');
	const imOrders = document.getElementById('bm-im-orders');
	let catalog = [];
	let lastIdempotencyKey = '';

	function selectedProduct() {
		const code = parseInt(imProduct?.value || '0', 10);
		return catalog.find((p) => p.product_code === code) || null;
	}

	function updatePriceLabel() {
		const p = selectedProduct();
		if (!imPrice) {
			return;
		}
		if (!p) {
			imPrice.textContent = 'Preis: –';
			return;
		}
		imPrice.textContent = 'API-Preis: ' + (p.price_eur || (p.price_cents + ' Cent')) +
			(p.is_registered ? ' · Einschreiben' : '');
	}

	imProduct?.addEventListener('change', updatePriceLabel);

	async function loadCatalog() {
		const registeredOnly = document.getElementById('bm-im-registered-only')?.checked ? '1' : '0';
		imStatus.textContent = 'Lade Katalog …';
		const r = await fetch(api('/internetmarke/catalog?registered_only=' + registeredOnly), { headers: headers(false) });
		const data = await r.json().catch(() => ({}));
		if (!r.ok) {
			imStatus.textContent = data.error || 'Katalog fehlgeschlagen (oft fehlende API-Keys).';
			catalog = [];
			imProduct.innerHTML = '<option value="">— keine Produkte —</option>';
			return;
		}
		catalog = data.products || [];
		imProduct.innerHTML = '';
		if (!catalog.length) {
			imProduct.innerHTML = '<option value="">— keine Treffer —</option>';
			imStatus.textContent = 'Keine Produkte. Ohne API-Key oder ohne Einschreiben-Treffer leer.';
			return;
		}
		catalog.forEach((p) => {
			const o = document.createElement('option');
			o.value = String(p.product_code);
			o.textContent = p.label + (p.price_eur ? ' · ' + p.price_eur : '') + (p.is_registered ? ' · Einschreiben' : '');
			imProduct.appendChild(o);
		});
		updatePriceLabel();
		imStatus.textContent = catalog.length + ' Produkte aus Products API.';
	}

	document.getElementById('bm-im-load-catalog')?.addEventListener('click', () => {
		loadCatalog().catch((e) => {
			imStatus.textContent = e.message || String(e);
		});
	});

	async function loadOrders() {
		const letterId = imBox?.dataset.letterId || '0';
		if (!letterId || letterId === '0' || !imOrders) {
			return;
		}
		const r = await fetch(api('/letters/' + encodeURIComponent(letterId) + '/internetmarke/orders'), { headers: headers(false) });
		const data = await r.json().catch(() => []);
		if (!r.ok) {
			return;
		}
		const list = Array.isArray(data) ? data : [];
		if (!list.length) {
			imOrders.innerHTML = '<p class="bm-hint">Noch keine API-Aufträge für diesen Brief.</p>';
			return;
		}
		imOrders.innerHTML = '<ul class="bm-im-order-list">' + list.map((o) => {
			const track = o.tracking_public_url
				? '<a href="' + o.tracking_public_url + '" target="_blank" rel="noopener">Tracking</a>'
				: '';
			return '<li><strong>' + (o.product_label || o.product_code) + '</strong> · ' +
				(o.price_eur || '') + ' · ' + o.status + ' · ' + o.mode +
				(o.tracking_number ? ' · Nr. ' + o.tracking_number + ' ' + track : '') +
				(o.has_stamp ? ' · Marke hinterlegt' : '') +
				(o.error_message ? '<br><span class="bm-hint">' + o.error_message + '</span>' : '') +
				'</li>';
		}).join('') + '</ul>';
	}

	async function runIm(mode) {
		const letterId = imBox?.dataset.letterId || '0';
		if (!letterId || letterId === '0') {
			imStatus.textContent = 'Zuerst Brief speichern/öffnen.';
			return;
		}
		const p = selectedProduct();
		if (!p || !p.price_cents) {
			imStatus.textContent = 'Bitte Produkt mit API-Preis wählen (Katalog laden).';
			return;
		}
		if (mode === 'purchase') {
			if (imBox?.dataset.allowPurchase !== '1') {
				imStatus.textContent = 'Live-Kauf deaktiviert – kein Porto.';
				return;
			}
			const ok = window.confirm(
				'Kostenpflichtiger Kauf bestätigen?\n\n' +
				p.label + '\nAPI-Preis: ' + p.price_eur + ' (' + p.price_cents + ' Cent)\n\n' +
				'Es wird kein zweiter Kauf mit demselben Auftragsschlüssel ausgelöst.'
			);
			if (!ok) {
				return;
			}
		}
		lastIdempotencyKey = lastIdempotencyKey || idemKey(mode);
		const key = mode === 'purchase' ? lastIdempotencyKey : idemKey('preview');
		if (mode === 'purchase') {
			lastIdempotencyKey = key;
		}
		imStatus.textContent = mode === 'purchase' ? 'Sende Kauf …' : 'Vorschau …';
		const body = {
			product_code: p.product_code,
			confirm_price_cents: p.price_cents,
			voucher_layout: document.getElementById('bm-im-voucher')?.value || 'FRANKING_ZONE',
			idempotency_key: key,
			registered_only: !!document.getElementById('bm-im-registered-only')?.checked,
			mode,
		};
		const path = '/letters/' + encodeURIComponent(letterId) + '/internetmarke/' + (mode === 'purchase' ? 'purchase' : 'preview');
		try {
			const r = await fetch(api(path), {
				method: 'POST',
				headers: headers(true),
				body: JSON.stringify(body),
			});
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				imStatus.textContent = (data.error || 'Fehler') +
					(data.hint ? ' – ' + data.hint : '') +
					(data.retry_safe === false ? ' (kein Blind-Retry)' : '');
				await loadOrders();
				return;
			}
			imStatus.textContent = 'Auftrag ' + data.status + ' · ' + (data.price_eur || '') +
				(data.tracking_number ? ' · Sendung ' + data.tracking_number : '');
			if (data.tracking_number) {
				const sn = document.getElementById('bm-sendungsnummer');
				if (sn && !sn.value) {
					sn.value = data.tracking_number;
				}
			}
			if (mode === 'purchase' && data.status === 'purchased') {
				lastIdempotencyKey = '';
			}
			await loadOrders();
		} catch (err) {
			imStatus.textContent = err.message || String(err);
		}
	}

	document.getElementById('bm-im-preview')?.addEventListener('click', () => runIm('preview'));
	document.getElementById('bm-im-purchase')?.addEventListener('click', () => runIm('purchase'));
	loadOrders().catch(() => {});

	if (statusEl && statusEl.hidden) {
		setStatus('');
	}
})();
