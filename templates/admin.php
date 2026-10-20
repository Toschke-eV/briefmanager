<?php

declare(strict_types=1);

/** @var array $_ */
$org = $_['organisation'];
$orgs = $_['organisations'] ?? [];
$activeId = (string)($_['active_id'] ?? ($org['id'] ?? ''));
$designs = $_['designs'];
$board = is_array($org['board'] ?? null) ? $org['board'] : [];
if ($board === []) {
	$board = [['name' => '', 'role' => '']];
}
?>
<div id="briefmanager-admin" class="section">
	<h2>Briefmanager – Vereine</h2>
	<p>Eigenen Verein anlegen, Stammdaten und Logo hinterlegen. Design-Preset ist optional (keine Vorauswahl). Der aktive Verein gilt für Briefkopf und Fußzeile.</p>

	<div class="bm-admin-toolbar">
		<label>Verein
			<select id="bm-org-switcher">
				<?php foreach ($orgs as $row): ?>
					<option value="<?php p($row['id'] ?? ''); ?>"<?php if (($row['id'] ?? '') === $activeId) {
						echo ' selected';
					} ?>><?php p(($row['name'] ?? '') !== '' ? $row['name'] : ('Neuer Verein (' . substr((string)($row['id'] ?? ''), 0, 6) . ')')); ?><?php if (($row['id'] ?? '') === $activeId) {
						echo ' · aktiv';
					} ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="button" class="primary" id="bm-org-new">+ Verein anlegen</button>
		<button type="button" id="bm-org-activate">Als aktiv setzen</button>
		<button type="button" id="bm-org-delete">Löschen</button>
	</div>

	<form id="bm-org-form" class="bm-admin-form">
		<input type="hidden" name="id" id="bm-org-id" value="<?php p($org['id'] ?? ''); ?>">

		<fieldset>
			<legend>Briefdesign</legend>
			<p>
				<label>Design-Preset
					<select name="design" id="bm-org-design">
						<option value="">— Keine Vorauswahl (eigenes Design) —</option>
						<?php foreach ($designs as $slug => $label): ?>
							<option value="<?php p($slug); ?>"<?php if (($org['design'] ?? '') === $slug) {
								echo ' selected';
							} ?>><?php p($label); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<p class="bm-hint">Presets legen Farben und Fallback-Logo fest. Eigene Presets legen Sie unten unter „Design-Presets verwalten“ an.</p>
		</fieldset>

		<fieldset>
			<legend>Stammdaten</legend>
			<p><label>Name <input type="text" name="name" value="<?php p($org['name'] ?? ''); ?>" class="bm-wide"></label></p>
			<p><label>Claim / Tagline <input type="text" name="tagline" value="<?php p($org['tagline'] ?? ''); ?>" class="bm-wide"></label></p>
			<p><label>Motto (Fußbanner) <input type="text" name="slogan" value="<?php p($org['slogan'] ?? ''); ?>" class="bm-wide"></label></p>
			<p><label>Zusatz / c/o <input type="text" name="address_extra" value="<?php p($org['address_extra'] ?? ''); ?>" class="bm-wide" placeholder="z. B. c/o …"></label></p>
			<p><label>Straße <input type="text" name="street" value="<?php p($org['street'] ?? ''); ?>" class="bm-wide"></label></p>
			<p>
				<label>PLZ <input type="text" name="zip" value="<?php p($org['zip'] ?? ''); ?>"></label>
				<label>Ort <input type="text" name="city" value="<?php p($org['city'] ?? ''); ?>"></label>
			</p>
			<p>
				<label>Telefon <input type="text" name="phone" value="<?php p($org['phone'] ?? ''); ?>"></label>
				<label>Fax <input type="text" name="fax" value="<?php p($org['fax'] ?? ''); ?>"></label>
			</p>
			<p><label>E-Mail <input type="email" name="email" value="<?php p($org['email'] ?? ''); ?>" class="bm-wide"></label></p>
			<p><label>Website <input type="text" name="website" value="<?php p($org['website'] ?? ''); ?>" class="bm-wide"></label></p>
		</fieldset>

		<fieldset>
			<legend>Vorstand</legend>
			<div id="bm-board-list" class="bm-board-list">
				<?php foreach ($board as $index => $member): ?>
					<div class="bm-board-row">
						<input type="text" name="board_name[<?php echo (int)$index; ?>]" placeholder="Name" value="<?php p($member['name'] ?? ''); ?>">
						<input type="text" name="board_role[<?php echo (int)$index; ?>]" placeholder="Amt" value="<?php p($member['role'] ?? ''); ?>">
						<button type="button" class="bm-board-remove">Entfernen</button>
					</div>
				<?php endforeach; ?>
			</div>
			<p><button type="button" id="bm-board-add">+ Vorstand hinzufügen</button></p>
			<p class="bm-hint">Name und Amt je Zeile. In der Fußzeile erscheinen bis zu fünf Einträge.</p>
			<template id="bm-board-template">
				<div class="bm-board-row">
					<input type="text" name="board_name[__i__]" placeholder="Name">
					<input type="text" name="board_role[__i__]" placeholder="Amt">
					<button type="button" class="bm-board-remove">Entfernen</button>
				</div>
			</template>
		</fieldset>

		<fieldset>
			<legend>Vereinssitz</legend>
			<p>
				<label>Vereinssitz (Ort) <input type="text" name="seat_city" value="<?php p($org['seat_city'] ?? ''); ?>" placeholder="z. B. Frankfurt am Main"></label>
				<label>Region / Land <input type="text" name="seat_region" value="<?php p($org['seat_region'] ?? ''); ?>" placeholder="z. B. Hessen"></label>
			</p>
		</fieldset>

		<fieldset>
			<legend>Bank &amp; Register</legend>
			<p><label>IBAN <input type="text" name="iban" value="<?php p($org['iban'] ?? ''); ?>" class="bm-wide"></label></p>
			<p>
				<label>BIC <input type="text" name="bic" value="<?php p($org['bic'] ?? ''); ?>"></label>
				<label>Bank <input type="text" name="bank_name" value="<?php p($org['bank_name'] ?? ''); ?>" class="bm-wide"></label>
			</p>
			<p><label>Registergericht <input type="text" name="register_court" value="<?php p($org['register_court'] ?? ''); ?>" class="bm-wide"></label></p>
			<p><label>VR-Nummer <input type="text" name="vr_number" value="<?php p($org['vr_number'] ?? ''); ?>"></label></p>
			<p>
				<label>Finanzamt <input type="text" name="tax_office" value="<?php p($org['tax_office'] ?? ''); ?>" class="bm-wide"></label>
				<label>Steuernummer <input type="text" name="tax_id" value="<?php p($org['tax_id'] ?? ''); ?>"></label>
			</p>
		</fieldset>

		<fieldset>
			<legend>Aktenzeichen</legend>
			<p>
				<label>Prefix <input type="text" name="ref_prefix" value="<?php p($org['ref_prefix'] ?? ''); ?>" placeholder="AAB-"></label>
				<label>Vorab <input type="text" name="ref_vorab" value="<?php p($org['ref_vorab'] ?? ''); ?>" placeholder="GS-"></label>
			</p>
			<p><label>Muster <input type="text" name="ref_pattern" value="<?php p($org['ref_pattern'] ?? ''); ?>" class="bm-wide"></label></p>
			<p>
				<label>Titel-Stellen <input type="number" name="title_pad" min="1" max="8" value="<?php p((string)($org['title_pad'] ?? 5)); ?>"></label>
				<label>AZ-Stellen <input type="number" name="ref_pad" min="1" max="8" value="<?php p((string)($org['ref_pad'] ?? 3)); ?>"></label>
				<label>Nummer
					<select name="ref_reset">
						<option value="daily"<?php if (($org['ref_reset'] ?? '') === 'daily') {
							echo ' selected';
						} ?>>täglich neu</option>
						<option value="global"<?php if (($org['ref_reset'] ?? '') === 'global') {
							echo ' selected';
						} ?>>durchgehend</option>
					</select>
				</label>
			</p>
			<p><label>Anleitung <textarea name="howto" rows="4" class="bm-wide"><?php p($org['howto'] ?? ''); ?></textarea></label></p>
		</fieldset>

		<fieldset>
			<legend>Logo</legend>
			<div class="bm-logo-preview-wrap">
				<img id="bm-logo-preview" class="bm-logo-preview" alt="Logo-Vorschau"
					src="<?php p((string)($_['logo_data_uri'] ?? '')); ?>"
					<?php if (empty($_['logo_data_uri'])): ?>hidden<?php endif; ?>>
				<p id="bm-logo-empty" class="bm-hint"<?php if (!empty($_['logo_data_uri'])): ?> hidden<?php endif; ?>>Noch kein Logo hinterlegt.</p>
			</div>
			<p>
				<label>Logo hochladen (PNG/JPEG/GIF/WebP)
					<input type="file" name="logo_file" id="bm-logo-file" accept="image/png,image/jpeg,image/gif,image/webp">
				</label>
			</p>
			<p><label>Gespeicherter Logo-Schlüssel <input type="text" name="logo_path" id="bm-logo-path" value="<?php p($org['logo_path'] ?? ''); ?>" class="bm-wide" readonly></label></p>
			<p class="bm-hint">Nach dem Speichern der Stammdaten wird ein gewähltes Logo hochgeladen und für das PDF verwendet.</p>
		</fieldset>

		<p><button type="submit" class="primary">Speichern</button> <span id="bm-admin-status"></span></p>
	</form>

	<?php
	$counters = $_['counters'] ?? ['title' => 0, 'ref' => 0, 'title_day' => '', 'ref_day' => ''];
	$letters = $_['letters'] ?? [];
	?>
	<section class="bm-admin-tools" id="bm-admin-tools">
		<h3>Zähler &amp; Briefe</h3>
		<p class="bm-hint">Das Briefpapier-Grundgerüst bleibt. Hier setzen Sie nur Nummern zurück oder räumen generierte Briefe ab.</p>
		<div class="bm-admin-counters">
			<p>
				Titel-Zähler: <strong id="bm-counter-title"><?php p((string)($counters['title'] ?? 0)); ?></strong>
				<?php if (!empty($counters['title_day'])): ?>
					<span class="bm-muted">(Tag <?php p($counters['title_day']); ?>)</span>
				<?php endif; ?>
				· AZ-Zähler: <strong id="bm-counter-ref"><?php p((string)($counters['ref'] ?? 0)); ?></strong>
				<?php if (!empty($counters['ref_day'])): ?>
					<span class="bm-muted">(Tag <?php p($counters['ref_day']); ?>)</span>
				<?php endif; ?>
			</p>
			<p>
				<button type="button" id="bm-counter-reset">Zähler zurücksetzen</button>
				<span id="bm-counter-status"></span>
			</p>
		</div>

		<div class="bm-admin-letters">
			<div class="bm-admin-letters-head">
				<strong>Generierte Briefe (<?php echo count($letters); ?>)</strong>
				<button type="button" id="bm-letters-delete-all">Alle Briefe löschen</button>
			</div>
			<div id="bm-letters-list" class="bm-letters-list">
				<?php if ($letters === []): ?>
					<p class="bm-empty">Keine Briefe vorhanden.</p>
				<?php else: ?>
					<?php foreach ($letters as $row): ?>
						<div class="bm-letter-row" data-id="<?php p((string)$row['id']); ?>">
							<span>
								<strong><?php p($row['title'] ?: ('#' . $row['id'])); ?></strong>
								<em><?php p($row['betreff'] ?: 'Ohne Betreff'); ?></em>
								<?php if (!empty($row['pdf_file'])): ?><span class="bm-pdf-badge">PDF</span><?php endif; ?>
								<?php if (!empty($row['internetmarke_path'])): ?><span class="bm-pdf-badge">Marke</span><?php endif; ?>
							</span>
							<button type="button" class="bm-letter-delete" data-id="<?php p((string)$row['id']); ?>">Löschen</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<span id="bm-letters-status"></span>
		</div>
	</section>

	<?php
	$designDetails = $_['design_details'] ?? [];
	$users = $_['users'] ?? [];
	?>
	<section class="bm-admin-tools" id="bm-design-tools">
		<h3>Design-Presets verwalten</h3>
		<p class="bm-hint">Eingebaute Presets dienen als Vorlage. Eigene Presets können Sie anlegen, ändern und Vereinen zuweisen.</p>
		<div id="bm-design-list" class="bm-design-list">
			<?php foreach ($designDetails as $d): ?>
				<div class="bm-design-row"
					data-slug="<?php p($d['slug']); ?>"
					data-builtin="<?php p(!empty($d['builtin']) ? '1' : '0'); ?>"
					data-label="<?php p($d['label'] ?? ''); ?>"
					data-blue="<?php p($d['blue'] ?? '#0D5E94'); ?>"
					data-gray="<?php p($d['gray'] ?? '#4B5563'); ?>"
					data-accent="<?php p($d['accent'] ?? '#C9A227'); ?>"
					data-line="<?php p($d['line'] ?? '#D7E0EA'); ?>"
					data-title="<?php p((string)($d['title_size'] ?? 12.5)); ?>"
					data-tagline="<?php p((string)($d['tagline_size'] ?? 8.2)); ?>"
					data-logow="<?php p((string)($d['logo_w'] ?? 28)); ?>"
					data-crest="<?php p(!empty($d['crest']) ? '1' : '0'); ?>">
					<span class="bm-design-swatch" style="background:<?php p($d['blue'] ?? '#0D5E94'); ?>"></span>
					<strong><?php p($d['label']); ?></strong>
					<code><?php p($d['slug']); ?></code>
					<?php if (!empty($d['builtin'])): ?>
						<em class="bm-muted">eingebaut</em>
					<?php else: ?>
						<button type="button" class="bm-design-edit">Bearbeiten</button>
						<button type="button" class="bm-design-delete">Löschen</button>
					<?php endif; ?>
					<button type="button" class="bm-design-clone">Als Vorlage übernehmen</button>
				</div>
			<?php endforeach; ?>
		</div>
		<form id="bm-design-form" class="bm-admin-form">
			<input type="hidden" name="slug" id="bm-design-slug" value="">
			<p><label>Name <input type="text" name="label" id="bm-design-label" class="bm-wide" placeholder="z. B. Landesverband Hessen"></label></p>
			<p><label>Technischer Schlüssel (nur bei Neu) <input type="text" name="slug_new" id="bm-design-slug-new" placeholder="lv-hessen"></label></p>
			<p class="bm-color-row">
				<label>Primär <input type="color" name="blue" id="bm-design-blue" value="#0D5E94"></label>
				<label>Grau <input type="color" name="gray" id="bm-design-gray" value="#4B5563"></label>
				<label>Akzent <input type="color" name="accent" id="bm-design-accent" value="#C9A227"></label>
				<label>Linie <input type="color" name="line" id="bm-design-line" value="#D7E0EA"></label>
			</p>
			<p>
				<label>Titel-Größe <input type="number" step="0.1" name="title_size" id="bm-design-title" value="12.5"></label>
				<label>Claim-Größe <input type="number" step="0.1" name="tagline_size" id="bm-design-tagline" value="8.2"></label>
				<label>Logo-Breite mm <input type="number" step="0.1" name="logo_w" id="bm-design-logow" value="28"></label>
				<label><input type="checkbox" name="crest" id="bm-design-crest" value="1"> Wappen-Layout</label>
			</p>
			<p>
				<button type="submit" class="primary">Preset speichern</button>
				<button type="button" id="bm-design-reset">Formular leeren</button>
				<span id="bm-design-status"></span>
			</p>
		</form>
	</section>

	<section class="bm-admin-tools" id="bm-internetmarke-admin">
		<h3>Internetmarke API (Admin)</h3>
		<p class="bm-hint">DHL Developer Portal: App mit INTERNETMARKE (REST) anlegen. SOAP ist abgeschaltet. Secrets werden serverseitig verschlüsselt (ICrypto). Live-Kauf bleibt aus, bis ausdrücklich aktiviert. Keine Testkäufe aus der UI.</p>
		<p class="bm-hint">Dokumentation: <a href="https://developer.dhl.com/api-reference/deutsche-post-internetmarke-post-paket-deutschland" target="_blank" rel="noopener">developer.dhl.com … INTERNETMARKE</a></p>
		<form id="bm-im-app-form" class="bm-admin-form">
			<p><label>client_id (API-Key) <input type="text" name="client_id" id="bm-im-client-id" class="bm-wide" autocomplete="off"></label></p>
			<p><label>client_secret <input type="password" name="client_secret" id="bm-im-client-secret" class="bm-wide" autocomplete="new-password" placeholder="leer = unverändert"></label></p>
			<p><label><input type="checkbox" name="allow_live_purchase" id="bm-im-allow-purchase" value="1"> Live-Kauf erlauben (kostet echtes Porto – nur mit Vertrag/Entwickler-Portokasse)</label></p>
			<p><button type="button" class="primary" id="bm-im-save">API-Zugänge speichern</button> <span id="bm-im-status"></span></p>
		</form>
	</section>

	<section class="bm-admin-tools" id="bm-assign-tools">
		<h3>Vereine Benutzern zuordnen</h3>
		<p class="bm-hint">Ohne Zuordnung sehen normale Nutzer keinen Verein. Administratoren ohne Zuordnung sehen alle. Bei mehreren Zuordnungen wählt der Nutzer in der Brief-App den aktiven Verein.</p>
		<div id="bm-assign-list" class="bm-assign-list">
			<?php foreach ($users as $u): ?>
				<div class="bm-assign-row" data-uid="<?php p($u['uid']); ?>">
					<strong><?php p($u['displayname']); ?></strong>
					<code><?php p($u['uid']); ?></code>
					<div class="bm-assign-checks">
						<?php foreach ($orgs as $o): ?>
							<label>
								<input type="checkbox" class="bm-assign-org"
									value="<?php p($o['id'] ?? ''); ?>"
									<?php if (in_array($o['id'] ?? '', $u['organisation_ids'] ?? [], true)) {
										echo ' checked';
									} ?>>
								<?php p(($o['name'] ?? '') !== '' ? $o['name'] : substr((string)($o['id'] ?? ''), 0, 8)); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<button type="button" class="bm-assign-save primary">Speichern</button>
				</div>
			<?php endforeach; ?>
			<?php if ($users === []): ?>
				<p class="bm-empty">Keine Benutzer gefunden.</p>
			<?php endif; ?>
		</div>
		<span id="bm-assign-status"></span>
	</section>
</div>
