# Briefmanager (Nextcloud)

Universelle Nextcloud-App für Briefe mit Briefpapier-Designs, privaten Benutzer-Layouts und optionaler Deutsche-Post-Internetmarke (REST).

**Version:** 0.9.2 · **Lizenz:** AGPL-3.0-or-later · **App-ID:** `briefmanager`

Maintainer: [Toschke e. V.](https://www.toschke.de) ([GitHub-Org](https://github.com/Toschke-eV))

## Hinweise zu diesem öffentlichen Repository

- Enthält **bereinigten** Quellcode ohne private Vereins-/Familienlogos und ohne Organisations-Stammdaten.
- Design-Presets sind generisch (`muster`, `verein`, `familie`, `buero`). Eigene Logos werden in der App hochgeladen.
- **Keine** privaten Schlüssel, CSR, Zertifikate oder API-Secrets in diesem Repo.
- Getrennt vom privaten Merge-App-Store (`Toschke-eV/nextcloud-app-store`, privat).

## Voraussetzungen

- Nextcloud 30–35
- PHP mit den üblichen Nextcloud-Extensions
- Optional: DHL Developer Portal (INTERNETMARKE REST) für Katalog/Vorschau/Kauf

## Installation (manuell)

1. Release- oder Source-Archiv entpacken nach `custom_apps/briefmanager`
2. `occ app:enable briefmanager`
3. `occ upgrade` falls Migrationen anstehen

Signierte Store-Installation setzt ein von Nextcloud ausgestelltes App-Zertifikat voraus (CSR über [app-certificate-requests](https://github.com/nextcloud/app-certificate-requests)).

## Entwickeln / Tests

```bash
composer install --no-dev --optimize-autoloader
php tests/smoke-ownership-and-im.php
php tests/ownership-two-users.php
php tests/idempotency-binding.php
php tests/public-release-guard.php
./scripts/pack-public.sh
```

## Drittkomponenten

Siehe [`THIRD_PARTY.md`](THIRD_PARTY.md) (TCPDF LGPL, FPDI MIT, DejaVu).

## Internetmarke

Details und Sicherheitsregeln: [`docs/INTERNETMARKE.md`](docs/INTERNETMARKE.md).
Live-Kauf ist standardmäßig ausgeschaltet.
