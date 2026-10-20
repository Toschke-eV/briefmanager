# AGENTS — briefmanager (öffentlich)

- App-ID: `briefmanager` (CN der CSR)
- Keine privaten Logos, Org-/Familienadressen, Secrets, Keys, CSR, Zertifikate committen
- Packen für öffentliche Archive: `./scripts/pack-public.sh`
- Vor Push: `php tests/public-release-guard.php`
- Signaturprüfung niemals umgehen
