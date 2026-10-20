<?php

declare(strict_types=1);

/** @var array $_ */
$letters = $_['letters'] ?? [];
$variants = $_['variants'] ?? [];
$selected = $_['selected'] ?? null;
$urls = $_['urls'] ?? [];
$folderUrl = $_['folder_url'] ?? '/apps/files/?dir=/Briefe';
$requesttoken = \OCP\Util::callRegister();
$hasPdf = is_array($selected) && trim((string)($selected['pdf_file'] ?? '')) !== '';
?>
<div id="briefmanager-app"
	class="bm-app"
	data-primary="<?php p($_['primary']); ?>"
	data-is-admin="<?php p(!empty($_['is_admin']) ? '1' : '0'); ?>"
	data-selected="<?php p((string)($_['selected_id'] ?? 0)); ?>"
	data-initial="<?php echo htmlspecialchars(json_encode($_['initial'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
	<header class="bm-header">
		<div>
			<h2><?php p($_['title']); ?></h2>
			<p class="bm-sub"><?php p($_['org_name']); ?></p>
		</div>
		<div class="bm-actions">
			<?php
			$userOrgs = $_['user_organisations'] ?? [];
			$activeOrgId = (string)($_['active_organisation_id'] ?? '');
			if (count($userOrgs) > 1):
			?>
				<form method="post" action="<?php p($urls['select_org'] ?? '/apps/briefmanager/form/select-org'); ?>" class="bm-inline-form bm-org-select-form">
					<input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>">
					<?php if (!empty($_['selected_id'])): ?>
						<input type="hidden" name="id" value="<?php p((string)$_['selected_id']); ?>">
					<?php endif; ?>
					<label class="bm-org-select-label">Verein
						<select name="organisation_id" onchange="this.form.submit()">
							<?php foreach ($userOrgs as $uo): ?>
								<option value="<?php p($uo['id'] ?? ''); ?>"<?php if (($uo['id'] ?? '') === $activeOrgId) {
									echo ' selected';
								} ?>><?php p(($uo['name'] ?? '') !== '' ? $uo['name'] : ('Verein ' . substr((string)($uo['id'] ?? ''), 0, 6))); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</form>
			<?php endif; ?>
			<a class="button" href="<?php p($folderUrl); ?>">Ordner „Briefe“</a>
			<form method="post" action="<?php p($urls['create'] ?? ''); ?>" class="bm-inline-form">
				<input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>">
				<input type="hidden" name="variant_id" value="<?php p((string)(($variants[0]['id'] ?? 1))); ?>">
				<input type="hidden" name="anrede" value="Sehr geehrte Damen und Herren,">
				<input type="hidden" name="schluss" value="Mit freundlichen Grüßen">
				<input type="hidden" name="visibility" value="private">
				<input type="hidden" name="status" value="entwurf">
				<input type="hidden" name="content_format" value="plain">
				<input type="hidden" name="empfaenger" value="">
				<input type="hidden" name="brieftext" value="">
				<button type="submit" class="primary" id="bm-new">Neuer Brief</button>
			</form>
		</div>
	</header>

	<aside class="bm-help" id="bm-help">
		<strong>Kurzbedienung</strong>
		<ol>
			<li>Persönliches Layout anlegen (Absender, Logo, Farben)</li>
			<li><em>Neuer Brief</em>, Layout wählen, Text ausfüllen</li>
			<li><em>Speichern</em> (PDF unter Dateien/Briefe)</li>
			<li>Optional: Internetmarke (Upload oder API-Vorschau)</li>
			<li>Rechts die <em>PDF-Vorschau</em> prüfen</li>
		</ol>
		<p>PDFs: <strong>Dateien → Briefe → Jahr</strong>. Admin-API-Schlüssel: <strong>Einstellungen → Briefmanager</strong>.</p>
	</aside>

	<details class="bm-panel" id="bm-layout-panel" open>
		<summary><strong>Mein Briefpapier (persönlich)</strong> – nur für Ihr Konto</summary>
		<div class="bm-panel-body">
			<div class="bm-layout-toolbar">
				<select id="bm-layout-picker" aria-label="Layout wählen">
					<option value="">— Neues Layout —</option>
					<?php foreach (($_['layouts'] ?? []) as $lay): ?>
						<option value="<?php p((string)$lay['id']); ?>"><?php p($lay['name'] ?? ('#' . $lay['id'])); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="primary" id="bm-layout-new">Neu</button>
				<button type="button" id="bm-layout-save">Speichern</button>
				<button type="button" id="bm-layout-delete">Löschen</button>
				<span id="bm-layout-status" class="bm-inline-status"></span>
			</div>
			<form id="bm-layout-form" class="bm-layout-form" enctype="multipart/form-data">
				<input type="hidden" id="bm-layout-id" value="">
				<label>Name<input type="text" id="bm-layout-name" name="name" placeholder="z. B. Familie privat" required></label>
				<label>Absendername<input type="text" id="bm-layout-sender" name="sender_name" placeholder="Vor- und Nachname"></label>
				<label>Design-Preset
					<select id="bm-layout-design" name="design">
						<?php foreach (($_['initial']['designs'] ?? []) as $slug => $label): ?>
							<option value="<?php p($slug); ?>"<?php if ($slug === 'ft') {
								echo ' selected';
							} ?>><?php p($label); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Claim / Tagline<input type="text" id="bm-layout-tagline" name="tagline"></label>
				<label>Zusatz / c/o<input type="text" id="bm-layout-extra" name="address_extra"></label>
				<label>Straße<input type="text" id="bm-layout-street" name="street"></label>
				<label>PLZ<input type="text" id="bm-layout-zip" name="zip" maxlength="10"></label>
				<label>Ort<input type="text" id="bm-layout-city" name="city"></label>
				<label>Telefon<input type="text" id="bm-layout-phone" name="phone"></label>
				<label>E-Mail<input type="email" id="bm-layout-email" name="email"></label>
				<label>Website<input type="text" id="bm-layout-website" name="website"></label>
				<label>Primärfarbe<input type="color" id="bm-layout-cprimary" name="color_primary" value="#1A3A6B"></label>
				<label>Grau<input type="color" id="bm-layout-cgray" name="color_gray" value="#5A6570"></label>
				<label>Akzent<input type="color" id="bm-layout-caccent" name="color_accent" value="#C9A227"></label>
				<label class="bm-check"><input type="checkbox" id="bm-layout-default" name="is_default" value="1"> Als Standard für neue Briefe</label>
				<label>Logo (PNG/JPEG)<input type="file" id="bm-layout-logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
			</form>
			<p class="bm-hint">Gewähltes Layout erscheint im Briefformular und steuert Absender/Logo/Farben im PDF. Fremde Layouts sind nicht sichtbar.</p>
		</div>
	</details>

	<details class="bm-panel" id="bm-porto-panel">
		<summary><strong>Meine Portokasse</strong> – Zugangsdaten nur für Sie (Passwort wird nie angezeigt)</summary>
		<div class="bm-panel-body bm-porto-form">
			<label>Benutzername<input type="text" id="bm-porto-user" autocomplete="username"></label>
			<label>Passwort<input type="password" id="bm-porto-pass" autocomplete="new-password" placeholder="leer = unverändert"></label>
			<button type="button" class="primary" id="bm-porto-save">Speichern</button>
			<button type="button" id="bm-porto-clear">Löschen</button>
			<span id="bm-porto-status" class="bm-inline-status"></span>
			<p class="bm-hint" id="bm-porto-hint">Status wird geladen …</p>
		</div>
	</details>

	<?php if (!empty($_['message'])): ?>
		<p class="bm-status<?php p(!empty($_['message_error']) ? ' is-error' : ''); ?>" id="bm-status"><?php p($_['message']); ?></p>
	<?php else: ?>
		<p class="bm-status" id="bm-status" hidden></p>
	<?php endif; ?>

	<div class="bm-layout">
		<section class="bm-list" id="bm-list">
			<?php if (!$letters): ?>
				<p class="bm-empty">Noch keine Briefe.<br>Rechts oben: <strong>Neuer Brief</strong>.</p>
			<?php else: ?>
				<?php foreach ($letters as $letter): ?>
					<a class="bm-item<?php p(((int)$letter['id'] === (int)($_['selected_id'] ?? 0)) ? ' is-active' : ''); ?>"
						href="<?php p($_['letter_links'][(int)$letter['id']] ?? '#'); ?>">
						<strong><?php p($letter['title'] ?: ('#' . $letter['id'])); ?></strong>
						<span><?php p($letter['betreff'] ?: ($letter['variant_name'] ?: 'Ohne Betreff')); ?></span>
						<?php if (!empty($letter['pdf_file'])): ?>
							<em class="bm-pdf-badge">PDF</em>
						<?php endif; ?>
						<?php if (!empty($letter['internetmarke_path'])): ?>
							<em class="bm-pdf-badge">Marke</em>
						<?php endif; ?>
						<?php if (!empty($letter['tracking_status'])): ?>
							<em class="bm-pdf-badge" title="<?php p($letter['tracking_status']); ?>">Einschreiben</em>
						<?php elseif (!empty($letter['sendungsnummer'])): ?>
							<em class="bm-pdf-badge">Sendung</em>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

		<section class="bm-editor" id="bm-editor">
			<?php if ($selected === null): ?>
				<p class="bm-empty">Noch kein Brief geöffnet. Klicken Sie auf <strong>Neuer Brief</strong>.</p>
			<?php else: ?>
				<form class="bm-form" id="bm-form" method="post" action="<?php p($urls['update'] ?? ''); ?>" enctype="multipart/form-data">
					<input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>">
					<input type="hidden" name="content_format" value="plain">
					<p class="bm-hint">Pflicht für ein sinnvolles PDF: Empfänger, Betreff, Brieftext. Speichern legt das PDF unter Dateien/Briefe ab.</p>
					<label>Titel<input name="title" value="<?php p($selected['title'] ?? ''); ?>"></label>
					<label>Variante / Abteilung
						<select name="variant_id">
							<?php foreach ($variants as $v): ?>
								<option value="<?php p((string)$v['id']); ?>"<?php if ((int)($selected['variant_id'] ?? 0) === (int)$v['id']) {
									echo ' selected';
								} ?>><?php p($v['name']); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<?php $layouts = $_['layouts'] ?? []; ?>
					<label>Familienlayout (privat)
						<select name="layout_id">
							<option value="0">— Kein persönliches Layout (Verein/Organisation) —</option>
							<?php foreach ($layouts as $lay): ?>
								<option value="<?php p((string)$lay['id']); ?>"<?php if ((int)($selected['layout_id'] ?? 0) === (int)$lay['id']) {
									echo ' selected';
								} ?>><?php p($lay['name'] ?? ('Layout #' . $lay['id'])); ?><?php if (!empty($lay['is_default'])) {
									echo ' · Standard';
								} ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<p class="bm-hint">Layouts oben unter „Mein Briefpapier“ anlegen. Auswahl hier wirkt beim Speichern auf das PDF.</p>
					<label>Empfänger (mehrzeilig)<textarea name="empfaenger" placeholder="Name&#10;Straße&#10;PLZ Ort"><?php p($selected['empfaenger'] ?? ''); ?></textarea></label>
					<label>Betreff<input name="betreff" value="<?php p($selected['betreff'] ?? ''); ?>"></label>
					<label>Anrede<input name="anrede" value="<?php p($selected['anrede'] ?? ''); ?>"></label>
					<label>Brieftext<textarea name="brieftext"><?php p($selected['brieftext'] ?? ''); ?></textarea></label>
					<label>Schluss<input name="schluss" value="<?php p($selected['schluss'] ?? 'Mit freundlichen Grüßen'); ?>"></label>
					<label>Unser Zeichen<input name="unser_zeichen" value="<?php p($selected['unser_zeichen'] ?? ''); ?>"></label>
					<label>Ihr Schreiben vom<input name="ihr_schreiben_vom" value="<?php p($selected['ihr_schreiben_vom'] ?? ''); ?>"></label>
					<label>Ihr Zeichen<input name="ihr_zeichen" value="<?php p($selected['ihr_zeichen'] ?? '-'); ?>"></label>
					<label>Datum<input name="datum" value="<?php p($selected['datum'] ?? ''); ?>"></label>
					<label>Sachbearbeitung<input name="sachbearbeiter" value="<?php p($selected['sachbearbeiter'] ?? ''); ?>"></label>
					<label>Telefon<input name="telefon" value="<?php p($selected['telefon'] ?? ''); ?>"></label>
					<label>E-Mail<input name="email" value="<?php p($selected['email'] ?? ''); ?>"></label>
					<label>Versand / Sichtfenster
						<select name="window_mode">
							<option value="sichtfenster"<?php if (($selected['window_mode'] ?? 'sichtfenster') === 'sichtfenster') {
								echo ' selected';
							} ?>>Mit Sichtfenster</option>
							<option value="ohne-sichtfenster"<?php if (($selected['window_mode'] ?? '') === 'ohne-sichtfenster') {
								echo ' selected';
							} ?>>Ohne Sichtfenster</option>
						</select>
					</label>
					<label>Internetmarke (PDF)
						<input type="file" name="internetmarke_pdf" accept="application/pdf">
					</label>
					<?php if (!empty($selected['internetmarke_path'])): ?>
						<p class="bm-hint">Internetmarke hinterlegt. Beim Speichern wird sie unter den Brief gelegt; Sendungsnummer und PLZ werden aus der Marke gelesen, sofern leer.</p>
						<label><input type="checkbox" name="internetmarke_remove" value="1"> Hinterlegte Internetmarke entfernen</label>
					<?php else: ?>
						<p class="bm-hint">Optional: PDF der <a href="https://shop.deutschepost.de/dhl-online-frankierung?productCategory=DOCUMENT" target="_blank" rel="noopener">Deutsche-Post-Internetmarke</a> hochladen (z. B. Einschreiben). Die Marke erscheint als Hintergrund auf Seite 1.</p>
					<?php endif; ?>
					<?php
					$im = $_['internetmarke_status'] ?? [];
					$imAllow = !empty($im['allow_live_purchase']);
					$imApp = !empty($im['app_configured']);
					$imPorto = !empty($im['portokasse_configured']);
					$sendungsnummer = (string)($selected['sendungsnummer'] ?? '');
					$publicTrack = $sendungsnummer !== ''
						? ('https://www.deutschepost.de/de/s/sendungsverfolgung.html?piececode=' . rawurlencode($sendungsnummer))
						: '';
					?>
					<div class="bm-tracking" id="bm-internetmarke-api"
						data-letter-id="<?php p((string)($_['selected_id'] ?? 0)); ?>"
						data-allow-purchase="<?php p($imAllow ? '1' : '0'); ?>">
						<strong>Internetmarke (API)</strong>
						<p class="bm-hint">
							Live-Kauf: <?php p($imAllow ? 'freigeschaltet' : 'aus (Standard)'); ?> ·
							App-API: <?php p($imApp ? 'ok' : 'fehlt'); ?> ·
							Portokasse: <?php p($imPorto ? 'ok' : 'fehlt'); ?>.
							Preis kommt serverseitig aus der Products API. Ohne Schlüssel bleibt der Katalog leer.
						</p>
						<label class="bm-check"><input type="checkbox" id="bm-im-registered-only" checked> Nur Einschreiben-Produkte (API-Filter)</label>
						<label>Produkt
							<select id="bm-im-product"><option value="">— Katalog laden —</option></select>
						</label>
						<p id="bm-im-price" class="bm-hint">Preis: –</p>
						<label>Frankierzone
							<select id="bm-im-voucher">
								<option value="FRANKING_ZONE">Frankierzone</option>
								<option value="ADDRESS_ZONE">Adresszone</option>
							</select>
						</label>
						<div class="bm-im-actions">
							<button type="button" id="bm-im-load-catalog">Katalog laden</button>
							<button type="button" id="bm-im-preview">Preis-/Produktvorschau</button>
							<button type="button" class="primary" id="bm-im-purchase" <?php if (!$imAllow) {
								echo 'disabled title="Live-Kauf vom Admin deaktiviert"';
							} ?>>Kauf bestätigen</button>
						</div>
						<p id="bm-im-status" class="bm-hint"></p>
						<div id="bm-im-orders"></div>
					</div>
					<label>Sendungsnummer<input name="sendungsnummer" id="bm-sendungsnummer" value="<?php p($sendungsnummer); ?>" placeholder="aus Marke oder API"></label>
					<label>Empfänger-PLZ (Sendungsverfolgung)
						<input name="empfaenger_plz" value="<?php p($selected['empfaenger_plz'] ?? ''); ?>" maxlength="5" placeholder="12345" style="max-width:6em">
					</label>
					<div class="bm-tracking">
						<strong>Einschreiben – öffentlicher Tracking-Link</strong>
						<?php if ($publicTrack !== ''): ?>
							<p><a href="<?php p($publicTrack); ?>" target="_blank" rel="noopener">Sendung bei der Deutschen Post verfolgen</a></p>
						<?php else: ?>
							<p class="bm-hint" style="margin:0">Noch keine Sendungsnummer. Automatische Statusabfrage ist optional und gilt erst nach manuellem „Status abrufen“ als geprüft.</p>
						<?php endif; ?>
						<?php if (!empty($selected['tracking_status'])): ?>
							<p><strong>Zuletzt manuell abgerufen:</strong> <?php p($selected['tracking_status']); ?></p>
							<?php if (!empty($selected['tracking_checked'])): ?>
								<p class="bm-hint" style="margin:0">Abruf: <?php p($selected['tracking_checked']); ?></p>
							<?php endif; ?>
						<?php endif; ?>
						<?php
						$events = $selected['tracking_events'] ?? [];
						if (is_array($events) && $events !== []):
						?>
							<table class="bm-tracking-events">
								<thead><tr><th>Datum</th><th>Ereignis</th></tr></thead>
								<tbody>
								<?php foreach ($events as $event): ?>
									<tr>
										<td><?php p(substr((string)($event['datum'] ?? ''), 0, 10)); ?></td>
										<td><?php p((string)($event['status'] ?? '')); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
					<label>Sichtbarkeit
						<select name="visibility">
							<option value="private"<?php if (($selected['visibility'] ?? '') === 'private') {
								echo ' selected';
							} ?>>Privat (nur ich)</option>
							<option value="team"<?php if (($selected['visibility'] ?? '') === 'team') {
								echo ' selected';
							} ?>>Team (für alle sichtbar)</option>
						</select>
					</label>
					<div class="bm-form-actions">
						<button type="submit" class="primary">Speichern</button>
						<button type="submit" name="track_after_save" value="1">Speichern und Status abrufen</button>
						<?php if (!empty($urls['pdf'])): ?>
							<a class="button" id="bm-pdf" href="<?php p($urls['pdf']); ?>" target="_blank" rel="noopener">PDF öffnen</a>
						<?php endif; ?>
					</div>
				</form>
				<?php if (!empty($urls['tracking_refresh']) || !empty($urls['tracking_receipt'])): ?>
					<div class="bm-tracking-actions">
						<?php if (!empty($urls['tracking_refresh'])): ?>
							<form method="post" action="<?php p($urls['tracking_refresh']); ?>" class="bm-inline-form">
								<input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>">
								<button type="submit">Status jetzt abrufen</button>
							</form>
						<?php endif; ?>
						<?php if (!empty($urls['tracking_receipt'])): ?>
							<form method="post" action="<?php p($urls['tracking_receipt']); ?>" class="bm-inline-form">
								<input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>">
								<button type="submit">Zustellbeleg holen</button>
							</form>
						<?php endif; ?>
						<?php if (!empty($urls['tracking_receipt_dl'])): ?>
							<a class="button" href="<?php p($urls['tracking_receipt_dl']); ?>" target="_blank" rel="noopener">Beleg öffnen</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if (!empty($urls['pdf_save'])): ?>
					<form method="post" action="<?php p($urls['pdf_save']); ?>" class="bm-inline-form bm-pdf-save-form">
						<input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>">
						<button type="submit">PDF in Dateien speichern</button>
					</form>
				<?php endif; ?>
				<?php if (!empty($urls['delete'])): ?>
					<form method="post" action="<?php p($urls['delete']); ?>" class="bm-delete-form" onsubmit="return confirm('Brief wirklich löschen?');">
						<input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>">
						<button type="submit" id="bm-delete">Löschen</button>
					</form>
				<?php endif; ?>
				<?php if (!empty($selected['pdf_file'])): ?>
					<p class="bm-file-path">Datei: <code><?php p($selected['pdf_file']); ?></code></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="bm-preview" id="bm-preview">
			<div class="bm-preview-head">
				<strong>Dokumentvorschau</strong>
				<div class="bm-preview-actions">
					<?php if (!empty($urls['viewer'])): ?>
						<a class="button primary" href="<?php p($urls['viewer']); ?>" target="_blank" rel="noopener">Nextcloud-Viewer</a>
					<?php endif; ?>
					<?php if (!empty($urls['office'])): ?>
						<a class="button" href="<?php p($urls['office']); ?>" target="_blank" rel="noopener">Collabora öffnen</a>
					<?php endif; ?>
					<?php if (!empty($urls['pdf'])): ?>
						<a class="button" href="<?php p($urls['pdf']); ?>" target="_blank" rel="noopener">Download</a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			$inline = $urls['pdf_inline'] ?? '';
			$viewer = $urls['viewer'] ?? '';
			if ($inline !== '' || $viewer !== ''):
			?>
				<div class="bm-preview-frame-wrap">
					<?php if ($inline !== ''): ?>
						<object class="bm-pdf-frame"
							type="application/pdf"
							data="<?php p($inline); ?>#toolbar=1&navpanes=0&view=FitH">
							<embed class="bm-pdf-frame" type="application/pdf" src="<?php p($inline); ?>#toolbar=1&view=FitH">
							<iframe class="bm-pdf-frame" title="PDF-Vorschau" src="<?php p($inline); ?>#view=FitH"></iframe>
							<p class="bm-empty">
								Vorschau hier nicht möglich.
								<?php if ($viewer !== ''): ?>
									<a href="<?php p($viewer); ?>" target="_blank" rel="noopener">Im Nextcloud-Viewer öffnen</a>
								<?php endif; ?>
							</p>
						</object>
					<?php elseif ($viewer !== ''): ?>
						<iframe class="bm-pdf-frame" title="Nextcloud-Viewer" src="<?php p($viewer); ?>"></iframe>
					<?php endif; ?>
				</div>
			<?php else: ?>
				<p class="bm-empty">Noch kein Dokument. Brief speichern, dann erscheint hier die Vorschau bzw. die Viewer-Links.</p>
			<?php endif; ?>
		</section>
	</div>
</div>
