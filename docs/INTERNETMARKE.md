# Internetmarke / Einschreiben (Briefmanager 0.9.2)

Stand: 2026-10-20

## Offizielle Schnittstellen

| Zweck | URL |
|-------|-----|
| INTERNETMARKE REST | `https://api-eu.dhl.com/post/de/shipping/im/v1` |
| Token | `POST /user` |
| Preview | `POST /app/shoppingcart/pdf?validate=true` |
| Kauf | `POST /app/shoppingcart/pdf?directCheckout=true` |
| Produkte | `…/information/products/v1/products?profile=IM-PARTNER` |
| Doku | https://developer.dhl.com/api-reference/deutsche-post-internetmarke-post-paket-deutschland |

SOAP: nicht verwenden (Sunset 31.12.2025).

## UI (deutsch)

- **Mein Briefpapier:** Layout anlegen/bearbeiten/löschen, Logo, Absender, Farben; Auswahl am Brief → PDF
- **Meine Portokasse:** Benutzername/Passwort; Passwort wird nie zurückgeliefert
- **Admin:** App `client_id`/`client_secret`, Live-Kauf-Schalter (Einstellungen → Briefmanager)
- **Brief:** Katalog laden, Preis aus API, Vorschau, Kaufbestätigung (nur wenn freigeschaltet), Aufträge mit Sendungsnummer und öffentlichem Tracking-Link

## Sicherheit

- Preis **nur** aus Products API (`resolveTrustedProduct`); Client-`price_cents` wird ignoriert
- `confirm_price_cents` muss dem API-Preis entsprechen
- Idempotenz: Unique Key global im Mapper (ohne UID-Filter); vor Rückgabe Bindung `userId+letterId+productCode+mode` (`PostageIdempotencyGuard`); fremde Keys → Ablehnung ohne Metadaten
- Parallel-Race: gleiche Bindungsprüfung
- Nach erfolgreichem `checkoutPdf`: Download/Speicherfehler → `purchased` oder `uncertain` mit Recovery-Hinweis, **nie** `error`, **kein** Re-Kauf
- Live-Kauf Standard **aus**
- Secrets: ICrypto; API-Antworten ohne Passwort/Secret

## Was fertig ist

- Layout-CRUD-UI, Portokasse-UI, Admin-API-UI, Katalog/Vorschau/Kauf-Flow (serverseitig geschützt)
- Manueller Marken-Upload + öffentlicher Tracking-Link
- Manueller Statusabruf (bestehend); **nicht** als „automatisch fertig“ behauptet

## Was nicht fertig / ohne Schlüssel ungetestet

- Live-Aufrufe gegen DHL Products/Checkout **ohne** hinterlegte API-Keys in dieser Session **nicht** ausgeführt
- Kein echtes Porto gekauft
- Kein Nextcloud-PHPUnit-Container: Eigentum als isolierte PHP-Logiktests (zwei User), keine echte DB-Race auf dem Server
- Erweiterte Post-Tracking-APIs (Vertrag) nicht angebunden; nur öffentliche HTML-Suche/Link
