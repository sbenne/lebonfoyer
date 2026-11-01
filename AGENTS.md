# AGENTS.md - LeBonFoyer

Plateforme française d'annonces d'adoption de chats et de chiens. Priorités : **bien-être animal**, **sécurité des adoptions**, **traçabilité**. Ce fichier fixe les règles métier ; le code et la configuration priment sur la prose.

## État actuel du dépôt
- **Starter partiel, pas encore une app Symfony complète** : pas de `bin/`, `config/`, `public/`, `templates/`, `vendor/`, `composer.lock` ni `.env.local`.
- Seul code existant : `src/Animal/Species.php`, `src/Welfare/*`, `tests/Welfare/WelfareCheckerTest.php`, et le composant React `assets/react/controllers/AnimalFilters.jsx`.
- `docs/AUDIT.md` résume les corrections ; les contraintes de versions n'y ont pas été vérifiées en ligne.
- Les deux `.zip` sont des snapshots de livraison, pas la source de vérité. `lebonfoyer-corrige.zip` contient une copie de l'arbre (avec l'ancien `AGENTS.md`) ; `lebonfoyer-starter-files.zip` contient un ancien `AGENTS.md`.
- `/init`, `welfare-checker`, `security-auditor`, `perf-optimizer` sont des **playbooks projet**, pas des skills OpenCode installés.
- Aucune CI ni config PHPStan/PHP-CS-Fixer versionnée pour l'instant.

## Amorçage (ordre impératif)
1. `composer install` : sans lockfile, Composer résout les versions. Flex génère `bin/console`, `config/`, `public/` et `src/Kernel.php`. **Ne pas lancer `composer create-project ... .`** dans ce dossier non vide.
2. `npm install` **après** `composer install` : `@symfony/ux-react` est un `file:vendor/symfony/ux-react/assets`.
3. `cp .env.local.example .env.local`, puis renseigner `APP_SECRET`, `DATABASE_URL`, les clés OAuth et `DRIVE_TOKEN_ENCRYPTION_KEY`.
4. `php bin/console doctrine:database:create`, puis `doctrine:migrations:diff`, `doctrine:migrations:migrate`, puis `messenger:setup-transports`.
5. Vérifier `npm run build`.

## Commandes utiles
- Tests : `composer test` (PHPUnit 11, `phpunit.dist.xml`, `APP_ENV=test` forcé, warnings et dépréciations bloquants).
- Un seul test : `vendor/bin/phpunit tests/Welfare/WelfareCheckerTest.php --filter testCatBetweenEightAndTwelveWeeksNeedsConfirmation`. Les tests Welfare sont unitaires (`MockClock`), sans BDD ni kernel.
- Analyse : `composer stan` (PHPStan niveau 6 sur `src tests`).
- Style : `composer cs` (PHP-CS-Fixer en dry-run).
- Front : `npm run dev`, `npm run watch`, `npm run build` ; serveur de dev : `npm run dev-server`.
- Worker Messenger : `php bin/console messenger:consume async` (transport Doctrine, `auto_setup=0`, donc `messenger:setup-transports` après les migrations).

## WelfareChecker : contrat à ne pas casser
- `App\Welfare\WelfareChecker` reçoit `ClockInterface` et ramène toutes les dates à minuit.
- `Species::minWeaningWeeks()` = **8** pour chat et chien. `recommendedWeaningWeeks()` = **12** pour le chat, **8** pour le chien.
- Remise < 8 semaines : erreur bloquante. Chat à exactement 8 semaines : autorisé avec **avertissement** à confirmer. Chat >= 12 semaines : aucun avertissement. Chien >= 8 semaines : autorisé sans avertissement.
- Erreurs également si naissance dans le futur ou remise dans le passé. `WelfareReport::isAllowed()` / `requiresConfirmation()` distinguent bloquant et avertissement ; `WelfareIssue::isBlocking()`.
- Une réservation peut précéder le sevrage ; seule la **date de remise** est contrôlée. Il n'existe encore ni entité réservation ni contrainte Symfony : ne pas présenter ces contrôles comme implémentés.
- Pour l'heure courante, ne jamais utiliser `new \DateTime()` ni `new \DateTimeImmutable('now')` : injecter `ClockInterface` (`MockClock` dans les tests).
- Toute modification de règle doit être couverte dans `tests/Welfare`.

## Règles métier à implémenter
- Date de naissance obligatoire, non future ; `birthDateEstimated` coché et affiché si la date est estimée.
- Identification obligatoire (puce ou tatouage) pour chiens et chats, numéro saisi dans l'annonce.
- Cédant : `particulier` = don uniquement, **aucun prix, paiement ou expédition** ; `association` = RNA/SIREN vérifié ; `professionnel` = SIREN + numéro de portée/élevage + mentions légales.
- Adoptant : logement (appartement/maison, jardin/terrasse), autres animaux, enfants. Le cédant peut ajouter au max 10 questions de 300 caractères, assainies.
- Réservation confirmable seulement après `WelfareChecker` **et** validation du questionnaire par le cédant.
- Remise **en mains propres uniquement**, au domicile du cédant ou dans un rayon configurable autour de sa ville ; pas de livraison ni de paiement sur la plateforme.

## OAuth, Drive et médias (cibles)
- Cédants : Google ou Microsoft OAuth uniquement. Adoptants : OAuth ou email/mot de passe avec email vérifié (`verify-email-bundle`) et reset password.
- Scopes Drive minimaux : Google `drive.file` ; Microsoft `Files.ReadWrite.AppFolder`. Jamais un accès Drive complet.
- Le Drive n'est que la **source** : le cédant dépose dans le dossier dédié `lebonfoyer.com`, puis `ImportAnimalPhoto` (Messenger) copie l'image, la convertit en WebP et génère les miniatures (LiipImagine). Ne jamais afficher une URL Drive.
- Tokens : refresh token uniquement, chiffré avec libsodium `secretbox` et `DRIVE_TOKEN_ENCRYPTION_KEY` (jamais `APP_SECRET`). Une révocation ne supprime pas les copies avant suppression de l'annonce ou du compte.

## Sécurité et RGPD (cibles)
- CSRF sur tous les formulaires, échappement Twig, `html-sanitizer` pour messages et questions, CSP stricte.
- Rate limiting sur login, inscription, messages et réservations ; email vérifié avant réservation.
- Journal d'audit : création/validation/annulation de réservation, remise, confirmation d'avertissement de sevrage.
- RGPD : registre des traitements, durées de conservation, export/suppression de compte, accès Drive limité au dossier applicatif.

## Frontend et perf (cibles non implémentées)
- **Encore uniquement** ; AssetMapper est interdit (incompatible JSX). `assets/app.js` charge `bootstrap.js`, qui enregistre Stimulus et les composants React de `assets/react/controllers`.
- Nouveau composant React : fichier `.jsx` dans `assets/react/controllers`, puis `react_component('Nom', { ... })` en Twig. Exemple : `AnimalFilters.jsx`.
- Index `(species, status, city)` et `(birth_date)`, pagination par curseur.
- Recherche par rayon : latitude/longitude + `ST_Distance_Sphere`, géocodage via `geo.api.gouv.fr`.
- Cache `cache.app` invalidé par tags sur les listes ; n'hydrater que les composants React interactifs.

## Stack et qualité
- Symfony **7.4 LTS** (`composer.json` pin `7.4.*`, PHP >= 8.2) ; ne pas rétrograder vers 7.2.
- MariaDB 10.11 + Doctrine ORM 3, `serverVersion=mariadb-10.11.19` ; Twig + React via `symfony/ux-react`.
- `declare(strict_types=1)`, classes `final`/`readonly`, enums pour les valeurs métier (voir `Species`, `WelfareIssue`).
- PHPUnit 11, PHPStan niveau 6, PHP-CS-Fixer ; `npm run build` doit passer.
