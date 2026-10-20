(function () {
	'use strict';

	const form = document.getElementById('bm-org-form');
	if (!form) {
		return;
	}

	const status = document.getElementById('bm-admin-status');
	const switcher = document.getElementById('bm-org-switcher');
	const boardList = document.getElementById('bm-board-list');
	const boardTpl = document.getElementById('bm-board-template');
	const token = document.querySelector('head > meta[name="requesttoken"]')?.getAttribute('content') || '';
	const api = (path) => OC.generateUrl('/apps/briefmanager/api' + path);

	function setStatus(msg, isError) {
		status.textContent = msg;
		status.classList.toggle('bm-status-error', !!isError);
	}

	function jsonHeaders() {
		return {
			requesttoken: token,
			'Content-Type': 'application/json',
			Accept: 'application/json',
		};
	}

	function formToPayload(formEl) {
		const fd = new FormData(formEl);
		const payload = {};
		const boardName = [];
		const boardRole = [];
		for (const [key, value] of fd.entries()) {
			if (key === 'logo_file') {
				continue;
			}
			const mName = key.match(/^board_name\[(\d+)\]$/);
			const mRole = key.match(/^board_role\[(\d+)\]$/);
			if (mName) {
				boardName[Number(mName[1])] = String(value);
				continue;
			}
			if (mRole) {
				boardRole[Number(mRole[1])] = String(value);
				continue;
			}
			payload[key] = value;
		}
		payload.board_name = boardName;
		payload.board_role = boardRole;
		return payload;
	}

	function setLogoPreview(dataUri) {
		const img = document.getElementById('bm-logo-preview');
		const empty = document.getElementById('bm-logo-empty');
		if (!img) {
			return;
		}
		if (dataUri) {
			img.src = dataUri;
			img.hidden = false;
			if (empty) {
				empty.hidden = true;
			}
		} else {
			img.removeAttribute('src');
			img.hidden = true;
			if (empty) {
				empty.hidden = false;
			}
		}
	}

	function fillForm(org) {
		form.querySelector('[name="id"]').value = org.id || '';
		const fields = [
			'design', 'name', 'tagline', 'slogan', 'address_extra', 'street', 'zip', 'city',
			'phone', 'fax', 'email', 'website', 'seat_city', 'seat_region', 'iban', 'bic',
			'bank_name', 'register_court', 'vr_number', 'tax_office', 'tax_id', 'ref_prefix',
			'ref_vorab', 'ref_pattern', 'title_pad', 'ref_pad', 'ref_reset', 'howto', 'logo_path',
		];
		fields.forEach((name) => {
			const el = form.querySelector('[name="' + name + '"]');
			if (!el) {
				return;
			}
			el.value = org[name] ?? (name === 'design' ? '' : '');
		});
		const board = Array.isArray(org.board) && org.board.length ? org.board : [{ name: '', role: '' }];
		boardList.innerHTML = '';
		board.forEach((member, i) => addBoardRow(member.name || '', member.role || '', i));
		const logoFile = document.getElementById('bm-logo-file');
		if (logoFile) {
			logoFile.value = '';
		}
	}

	function refreshDesignSelect(labels) {
		const sel = document.getElementById('bm-org-design');
		if (!sel || !labels) {
			return;
		}
		const current = sel.value;
		sel.innerHTML = '<option value="">— Keine Vorauswahl (eigenes Design) —</option>';
		Object.keys(labels).forEach((slug) => {
			const opt = document.createElement('option');
			opt.value = slug;
			opt.textContent = labels[slug];
			if (slug === current) {
				opt.selected = true;
			}
			sel.appendChild(opt);
		});
	}

	function rebuildSwitcher(orgs, activeId) {
		if (!switcher) {
			return;
		}
		switcher.innerHTML = '';
		(orgs || []).forEach((row) => {
			const opt = document.createElement('option');
			opt.value = row.id || '';
			const label = (row.name || '').trim() || ('Neuer Verein (' + String(row.id || '').slice(0, 6) + ')');
			opt.textContent = label + (row.id === activeId ? ' · aktiv' : '');
			if (row.id === activeId) {
				opt.selected = true;
			}
			switcher.appendChild(opt);
		});
	}

	function addBoardRow(name, role, index) {
		const i = typeof index === 'number' ? index : boardList.querySelectorAll('.bm-board-row').length;
		let html;
		if (boardTpl && boardTpl.content) {
			const node = boardTpl.content.cloneNode(true);
			const row = node.querySelector('.bm-board-row');
			row.querySelectorAll('input').forEach((input) => {
				input.name = input.name.replace('__i__', String(i));
				if (input.placeholder === 'Name') {
					input.value = name;
				}
				if (input.placeholder === 'Amt') {
					input.value = role;
				}
			});
			boardList.appendChild(node);
			return;
		}
		html = '<div class="bm-board-row">'
			+ '<input type="text" name="board_name[' + i + ']" placeholder="Name" value="">'
			+ '<input type="text" name="board_role[' + i + ']" placeholder="Amt" value="">'
			+ '<button type="button" class="bm-board-remove">Entfernen</button></div>';
		boardList.insertAdjacentHTML('beforeend', html);
	}

	function reindexBoard() {
		boardList.querySelectorAll('.bm-board-row').forEach((row, i) => {
			const inputs = row.querySelectorAll('input');
			if (inputs[0]) {
				inputs[0].name = 'board_name[' + i + ']';
			}
			if (inputs[1]) {
				inputs[1].name = 'board_role[' + i + ']';
			}
		});
	}

	document.getElementById('bm-board-add')?.addEventListener('click', () => {
		addBoardRow('', '');
		reindexBoard();
	});

	boardList?.addEventListener('click', (ev) => {
		const btn = ev.target.closest('.bm-board-remove');
		if (!btn) {
			return;
		}
		const row = btn.closest('.bm-board-row');
		if (row && boardList.querySelectorAll('.bm-board-row').length > 1) {
			row.remove();
			reindexBoard();
		} else if (row) {
			row.querySelectorAll('input').forEach((input) => {
				input.value = '';
			});
		}
	});

	async function uploadLogoIfNeeded(orgId) {
		const fileInput = document.getElementById('bm-logo-file');
		const file = fileInput?.files?.[0];
		if (!file || !orgId) {
			return null;
		}
		const fd = new FormData();
		fd.append('logo', file);
		const r = await fetch(api('/organisation/' + encodeURIComponent(orgId) + '/logo'), {
			method: 'POST',
			headers: { requesttoken: token, Accept: 'application/json' },
			body: fd,
		});
		const data = await r.json().catch(() => ({}));
		if (!r.ok) {
			throw new Error(data.error || ('Logo-Upload HTTP ' + r.status));
		}
		return data.organisation || null;
	}

	form.addEventListener('submit', (ev) => {
		ev.preventDefault();
		setStatus('Speichern …');
		const payload = formToPayload(form);
		fetch(api('/organisation'), {
			method: 'PUT',
			headers: jsonHeaders(),
			body: JSON.stringify(payload),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			let org = data.organisation;
			const uploaded = await uploadLogoIfNeeded(org.id);
			if (uploaded) {
				org = uploaded;
			}
			fillForm(org);
			rebuildSwitcher(data.organisations || [org], data.active_id || org.id);
			if (Object.prototype.hasOwnProperty.call(data, 'logo_data_uri')) {
				setLogoPreview(data.logo_data_uri || '');
			}
			setStatus('Gespeichert.');
		}).catch((err) => {
			setStatus(err.message, true);
		});
	});

	document.getElementById('bm-org-new')?.addEventListener('click', () => {
		setStatus('Lege Verein an …');
		fetch(api('/organisation'), {
			method: 'POST',
			headers: jsonHeaders(),
			body: JSON.stringify({ name: '', design: '' }),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			fillForm(data.organisation);
			rebuildSwitcher(data.organisations, data.active_id);
			setStatus('Neuer Verein angelegt. Bitte Stammdaten ausfüllen.');
		}).catch((err) => {
			setStatus(err.message, true);
		});
	});

	document.getElementById('bm-org-activate')?.addEventListener('click', () => {
		const id = switcher?.value;
		if (!id) {
			return;
		}
		setStatus('Aktiviere …');
		fetch(api('/organisation/' + encodeURIComponent(id) + '/active'), {
			method: 'PUT',
			headers: jsonHeaders(),
			body: '{}',
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			fillForm(data.organisation);
			rebuildSwitcher(data.organisations, data.active_id);
			if (Object.prototype.hasOwnProperty.call(data, 'logo_data_uri')) {
				setLogoPreview(data.logo_data_uri || '');
			}
			setStatus('Aktiver Verein gesetzt.');
		}).catch((err) => {
			setStatus(err.message, true);
		});
	});

	document.getElementById('bm-org-delete')?.addEventListener('click', () => {
		const id = switcher?.value || form.querySelector('[name="id"]').value;
		if (!id) {
			return;
		}
		if (!window.confirm('Diesen Verein wirklich löschen?')) {
			return;
		}
		setStatus('Lösche …');
		fetch(api('/organisation/' + encodeURIComponent(id)), {
			method: 'DELETE',
			headers: jsonHeaders(),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			fillForm(data.organisation);
			rebuildSwitcher(data.organisations, data.active_id);
			setStatus('Verein gelöscht.');
		}).catch((err) => {
			setStatus(err.message, true);
		});
	});

	switcher?.addEventListener('change', () => {
		const id = switcher.value;
		setStatus('Lade …');
		fetch(api('/organisation/' + encodeURIComponent(id)), {
			headers: { requesttoken: token, Accept: 'application/json' },
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			fillForm(data.organisation || {});
			setLogoPreview(data.logo_data_uri || '');
			setStatus('Verein geladen (noch nicht aktiv – „Als aktiv setzen“ oder speichern).');
		}).catch((err) => {
			setStatus(err.message, true);
		});
	});

	function renderLetters(letters) {
		const list = document.getElementById('bm-letters-list');
		if (!list) {
			return;
		}
		if (!letters || !letters.length) {
			list.innerHTML = '<p class="bm-empty">Keine Briefe vorhanden.</p>';
			return;
		}
		list.innerHTML = letters.map((row) => {
			const title = (row.title || ('#' + row.id));
			const betreff = row.betreff || 'Ohne Betreff';
			const pdf = row.pdf_file ? '<span class="bm-pdf-badge">PDF</span>' : '';
			const marke = row.internetmarke_path ? '<span class="bm-pdf-badge">Marke</span>' : '';
			return '<div class="bm-letter-row" data-id="' + row.id + '">'
				+ '<span><strong>' + escapeHtml(title) + '</strong> <em>' + escapeHtml(betreff) + '</em> '
				+ pdf + ' ' + marke + '</span>'
				+ '<button type="button" class="bm-letter-delete" data-id="' + row.id + '">Löschen</button></div>';
		}).join('');
	}

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	document.getElementById('bm-counter-reset')?.addEventListener('click', () => {
		if (!window.confirm('Titel- und Aktenzeichen-Zähler wirklich auf 0 setzen?')) {
			return;
		}
		const el = document.getElementById('bm-counter-status');
		el.textContent = 'Setze zurück …';
		fetch(api('/admin/counters/reset'), {
			method: 'POST',
			headers: jsonHeaders(),
			body: '{}',
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			document.getElementById('bm-counter-title').textContent = String(data.counters?.title ?? 0);
			document.getElementById('bm-counter-ref').textContent = String(data.counters?.ref ?? 0);
			el.textContent = data.message || 'Zurückgesetzt.';
		}).catch((err) => {
			el.textContent = err.message;
		});
	});

	document.getElementById('bm-letters-delete-all')?.addEventListener('click', () => {
		if (!window.confirm('Wirklich ALLE Briefe inklusive PDFs und Internetmarken löschen?')) {
			return;
		}
		const el = document.getElementById('bm-letters-status');
		el.textContent = 'Lösche …';
		fetch(api('/admin/letters'), {
			method: 'DELETE',
			headers: jsonHeaders(),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			renderLetters(data.letters || []);
			el.textContent = data.message || 'Gelöscht.';
		}).catch((err) => {
			el.textContent = err.message;
		});
	});

	document.getElementById('bm-letters-list')?.addEventListener('click', (ev) => {
		const btn = ev.target.closest('.bm-letter-delete');
		if (!btn) {
			return;
		}
		const id = btn.getAttribute('data-id');
		if (!id || !window.confirm('Diesen Brief löschen?')) {
			return;
		}
		const el = document.getElementById('bm-letters-status');
		el.textContent = 'Lösche …';
		fetch(api('/admin/letters/' + encodeURIComponent(id)), {
			method: 'DELETE',
			headers: jsonHeaders(),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			renderLetters(data.letters || []);
			el.textContent = data.message || 'Gelöscht.';
		}).catch((err) => {
			el.textContent = err.message;
		});
	});

	/* --- Design-Presets --- */
	const designForm = document.getElementById('bm-design-form');
	const designStatus = document.getElementById('bm-design-status');

	function designPayload() {
		const fd = new FormData(designForm);
		const slugExisting = String(fd.get('slug') || '');
		const slugNew = String(fd.get('slug_new') || '').trim();
		return {
			slug: slugExisting || slugNew,
			label: String(fd.get('label') || ''),
			blue: String(fd.get('blue') || '#0D5E94'),
			gray: String(fd.get('gray') || '#4B5563'),
			accent: String(fd.get('accent') || '#C9A227'),
			line: String(fd.get('line') || '#D7E0EA'),
			title_size: Number(fd.get('title_size') || 12.5),
			tagline_size: Number(fd.get('tagline_size') || 8.2),
			logo_w: Number(fd.get('logo_w') || 28),
			crest: designForm.querySelector('#bm-design-crest')?.checked ? 1 : 0,
		};
	}

	function fillDesignForm(row) {
		document.getElementById('bm-design-slug').value = row.builtin ? '' : (row.slug || '');
		document.getElementById('bm-design-slug-new').value = row.builtin ? (row.slug + '-custom') : '';
		document.getElementById('bm-design-slug-new').disabled = !row.builtin && !!row.slug;
		document.getElementById('bm-design-label').value = row.label || '';
		document.getElementById('bm-design-blue').value = row.blue || '#0D5E94';
		document.getElementById('bm-design-gray').value = row.gray || '#4B5563';
		document.getElementById('bm-design-accent').value = row.accent || '#C9A227';
		document.getElementById('bm-design-line').value = row.line || '#D7E0EA';
		document.getElementById('bm-design-title').value = row.title_size || 12.5;
		document.getElementById('bm-design-tagline').value = row.tagline_size || 8.2;
		document.getElementById('bm-design-logow').value = row.logo_w || 28;
		document.getElementById('bm-design-crest').checked = !!row.crest;
	}

	function renderDesignList(designs) {
		const list = document.getElementById('bm-design-list');
		if (!list) {
			return;
		}
		list.innerHTML = (designs || []).map((d) => {
			const builtin = d.builtin ? '1' : '0';
			const actions = d.builtin
				? '<em class="bm-muted">eingebaut</em>'
				: '<button type="button" class="bm-design-edit">Bearbeiten</button>'
					+ '<button type="button" class="bm-design-delete">Löschen</button>';
			return '<div class="bm-design-row" data-slug="' + escapeHtml(d.slug) + '" data-builtin="' + builtin + '"'
				+ ' data-blue="' + escapeHtml(d.blue) + '" data-gray="' + escapeHtml(d.gray) + '"'
				+ ' data-accent="' + escapeHtml(d.accent) + '" data-line="' + escapeHtml(d.line) + '"'
				+ ' data-title="' + escapeHtml(d.title_size) + '" data-tagline="' + escapeHtml(d.tagline_size) + '"'
				+ ' data-logow="' + escapeHtml(d.logo_w) + '" data-crest="' + (d.crest ? '1' : '0') + '"'
				+ ' data-label="' + escapeHtml(d.label) + '">'
				+ '<span class="bm-design-swatch" style="background:' + escapeHtml(d.blue) + '"></span>'
				+ '<strong>' + escapeHtml(d.label) + '</strong> <code>' + escapeHtml(d.slug) + '</code> '
				+ actions
				+ ' <button type="button" class="bm-design-clone">Als Vorlage übernehmen</button></div>';
		}).join('');
	}

	function rowToDesign(rowEl) {
		return {
			slug: rowEl.getAttribute('data-slug'),
			label: rowEl.getAttribute('data-label'),
			builtin: rowEl.getAttribute('data-builtin') === '1',
			blue: rowEl.getAttribute('data-blue'),
			gray: rowEl.getAttribute('data-gray'),
			accent: rowEl.getAttribute('data-accent'),
			line: rowEl.getAttribute('data-line'),
			title_size: Number(rowEl.getAttribute('data-title') || 12.5),
			tagline_size: Number(rowEl.getAttribute('data-tagline') || 8.2),
			logo_w: Number(rowEl.getAttribute('data-logow') || 28),
			crest: rowEl.getAttribute('data-crest') === '1',
		};
	}

	// Seed data attributes on initial server-rendered rows
	document.querySelectorAll('#bm-design-list .bm-design-row').forEach((row) => {
		if (!row.getAttribute('data-label')) {
			row.setAttribute('data-label', row.querySelector('strong')?.textContent || '');
		}
	});

	designForm?.addEventListener('submit', (ev) => {
		ev.preventDefault();
		const payload = designPayload();
		if (!payload.slug || !payload.label) {
			designStatus.textContent = 'Name und Schlüssel nötig.';
			return;
		}
		designStatus.textContent = 'Speichere …';
		const existing = document.getElementById('bm-design-slug').value;
		const method = existing ? 'PUT' : 'POST';
		const url = existing
			? api('/designs/' + encodeURIComponent(existing))
			: api('/designs');
		fetch(url, {
			method,
			headers: jsonHeaders(),
			body: JSON.stringify(payload),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			renderDesignList(data.designs || []);
			refreshDesignSelect(data.labels || {});
			designStatus.textContent = 'Preset gespeichert.';
			document.getElementById('bm-design-reset')?.click();
		}).catch((err) => {
			designStatus.textContent = err.message;
		});
	});

	document.getElementById('bm-design-reset')?.addEventListener('click', () => {
		fillDesignForm({
			slug: '', label: '', builtin: false,
			blue: '#0D5E94', gray: '#4B5563', accent: '#C9A227', line: '#D7E0EA',
			title_size: 12.5, tagline_size: 8.2, logo_w: 28, crest: false,
		});
		document.getElementById('bm-design-slug-new').disabled = false;
		document.getElementById('bm-design-slug').value = '';
	});

	document.getElementById('bm-design-list')?.addEventListener('click', (ev) => {
		const row = ev.target.closest('.bm-design-row');
		if (!row) {
			return;
		}
		const data = rowToDesign(row);
		if (ev.target.classList.contains('bm-design-edit') || ev.target.classList.contains('bm-design-clone')) {
			fillDesignForm(data);
			if (ev.target.classList.contains('bm-design-clone') || data.builtin) {
				document.getElementById('bm-design-slug').value = '';
				document.getElementById('bm-design-slug-new').disabled = false;
				document.getElementById('bm-design-slug-new').value = (data.slug || 'design') + '-custom';
				document.getElementById('bm-design-label').value = (data.label || '') + ' (eigene)';
			}
			return;
		}
		if (ev.target.classList.contains('bm-design-delete')) {
			if (!window.confirm('Preset „' + data.slug + '“ löschen?')) {
				return;
			}
			designStatus.textContent = 'Lösche …';
			fetch(api('/designs/' + encodeURIComponent(data.slug)), {
				method: 'DELETE',
				headers: jsonHeaders(),
			}).then(async (r) => {
				const res = await r.json().catch(() => ({}));
				if (!r.ok) {
					throw new Error(res.error || ('HTTP ' + r.status));
				}
				renderDesignList(res.designs || []);
				refreshDesignSelect(res.labels || {});
				designStatus.textContent = 'Gelöscht.';
			}).catch((err) => {
				designStatus.textContent = err.message;
			});
		}
	});

	/* --- Benutzerzuordnung --- */
	document.getElementById('bm-assign-list')?.addEventListener('click', (ev) => {
		const btn = ev.target.closest('.bm-assign-save');
		if (!btn) {
			return;
		}
		const row = btn.closest('.bm-assign-row');
		const uid = row?.getAttribute('data-uid');
		if (!uid) {
			return;
		}
		const ids = Array.from(row.querySelectorAll('.bm-assign-org:checked')).map((el) => el.value);
		const status = document.getElementById('bm-assign-status');
		status.textContent = 'Speichere …';
		fetch(api('/admin/assignments'), {
			method: 'PUT',
			headers: jsonHeaders(),
			body: JSON.stringify({ uid, organisation_ids: ids }),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			status.textContent = data.message || 'Gespeichert.';
		}).catch((err) => {
			status.textContent = err.message;
		});
	});

	document.getElementById('bm-im-save')?.addEventListener('click', () => {
		const el = document.getElementById('bm-im-status');
		const clientId = document.getElementById('bm-im-client-id')?.value || '';
		const clientSecret = document.getElementById('bm-im-client-secret')?.value || '';
		const allow = !!document.getElementById('bm-im-allow-purchase')?.checked;
		if (allow && !window.confirm('Live-Kauf wirklich erlauben? Das kann echtes Porto kosten. Testkäufe bitte nur mit Entwickler-Portokasse.')) {
			return;
		}
		el.textContent = 'Speichere …';
		const body = {
			client_id: clientId,
			allow_live_purchase: allow,
		};
		if (clientSecret !== '') {
			body.client_secret = clientSecret;
		}
		fetch(api('/internetmarke/app-credentials'), {
			method: 'PUT',
			headers: jsonHeaders(),
			body: JSON.stringify(body),
		}).then(async (r) => {
			const data = await r.json().catch(() => ({}));
			if (!r.ok) {
				throw new Error(data.error || ('HTTP ' + r.status));
			}
			el.textContent = data.ok
				? ('Gespeichert. Live-Kauf: ' + (data.allow_live_purchase ? 'an' : 'aus') + ', API: ' + (data.app_configured ? 'ok' : 'unvollständig'))
				: (data.error || 'Fehler');
			const secret = document.getElementById('bm-im-client-secret');
			if (secret) {
				secret.value = '';
			}
		}).catch((err) => {
			el.textContent = err.message;
		});
	});
})();
