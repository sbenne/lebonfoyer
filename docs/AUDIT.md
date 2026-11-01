# Synthèse des corrections

| Sujet | Avant | Après |
|---|---|---|
| Pile front | AssetMapper + stimulus-bundle ET Encore (incompatibles) | Encore seul (`webpack-encore-bundle`), AssetMapper retiré |
| Symfony | 7.2.* (hors support) | 7.4.* LTS |
| npm | Babel core/loader, Stimulus, ux-react absents | Ajoutés, Encore 5, Node >= 20 |
| Messenger | Pas de transport | `symfony/doctrine-messenger` |
| OAuth | Google seul | + Microsoft (`thenetworg/oauth2-azure`) + `google/apiclient` |
| Auth adoptant | Rien | verify-email + reset-password |
| Sécurité | Rien | rate-limiter, html-sanitizer, security-csrf, ext-sodium |
| Dates | Rien | `symfony/clock`, `WelfareChecker` + tests |
| Tests/CI | Rien | PHPUnit 11, PHPStan, PHP-CS-Fixer |
| AGENTS.md | Réservation vs remise ambiguë, `/init` impossible, "recommandation stricte" | Règles clarifiées |

Les contraintes de version n'ont pas pu être vérifiées en ligne : lancer
`composer update` puis `composer outdated`, et `npm install && npm outdated`.
Lancer `npm install` après `composer install` (ux-react est lu depuis `vendor/`).
