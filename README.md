# LeBonFoyer

Plateforme française d'annonces d'adoption de chats et de chiens, orientée bien-être animal, sécurité des adoptions et traçabilité.

Le site est servi en local sur **http://lebonfoyer.local/** (nginx + PHP-FPM 8.4 + MariaDB 10.11).

## Démarrage

```bash
composer install
npm install          # après composer install (@symfony/ux-react pointe vers vendor/)
cp .env.local.example .env.local
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console messenger:setup-transports
php bin/console doctrine:fixtures:load
npm run build
```

Renseigner dans `.env.local` : `APP_SECRET`, `DATABASE_URL`, `DEFAULT_URI`, `DRIVE_TOKEN_ENCRYPTION_KEY`. En développement, `MAILER_DSN=null://null` désactive l'envoi de mails : les comptes sont vérifiés automatiquement et les liens (vérification email, réinitialisation) sont écrits dans les logs.

Les répertoires `var/` et `public/uploads/` doivent être accessibles en écriture par `www-data` :

```bash
sudo chown -R "$USER":www-data var public/uploads public/build
sudo chmod -R g+w var public/uploads public/build
sudo find var public/uploads public/build -type d -exec chmod 2775 {} +
```

## Comptes de démonstration

| Rôle | Email | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@lebonfoyer.local` | `Admin12345678!` |
| Cédant (particulier) | `cedant@lebonfoyer.local` | `Cedant12345678!` |
| Cédant (association) | `association@lebonfoyer.local` | `Asso12345678!` |
| Adoptant | `adoptant@lebonfoyer.local` | `Adoptant12345678!` |

L'administration est protégée par le rôle `ROLE_ADMIN` de l'application (`/admin`).

## Fonctionnalités

- Inscription/connexion email + mot de passe, vérification d'email, réinitialisation de mot de passe, remember me, limitation des tentatives.
- Annonces : création, modification, photos converties en WebP, publication, suspension, suppression, géocodage ville → latitude/longitude.
- Recherche : espèce, ville, mot-clé, tri, pagination ; requête par rayon via `ST_Distance_Sphere`.
- Adoption : questionnaire du cédant, demande de réservation, contrôle du sevrage (`WelfareChecker`), confirmation d'avertissement, acceptation/refus, messagerie, remise en mains propres.
- Administration : tableau de bord, modération des annonces, gestion des utilisateurs, demandes, journal d'audit.
- Compte : profil, mot de passe, export RGPD (JSON), suppression de compte.
- Sécurité : CSRF, échappement Twig, assainissement HTML des messages, en-têtes de sécurité + CSP, rate limiting, journal d'audit.

OAuth (Google/Microsoft) et Drive sont préparés dans la configuration mais volontairement non branchés dans cette version locale.

## Commandes

```bash
composer test      # PHPUnit 11
composer stan      # PHPStan niveau 6 (src, tests)
composer cs        # PHP-CS-Fixer en dry-run
npm run dev        # assets en développement
npm run build      # build de production dans public/build/
php bin/console messenger:consume async
```

## Structure

- `src/Animal`, `src/Welfare` : règles de sevrage et rapports.
- `src/Entity`, `src/Repository` : modèle Doctrine (utilisateurs, annonces, photos, demandes, messagerie, audit).
- `src/Controller` : parcours public, cédant, adoptant, compte, administration.
- `src/Service` : upload WebP, journal d'audit, géocodage.
- `src/Validator` : contraintes métier (sevrage, règles de demande).
- `templates/`, `assets/` : Twig + Encore/React/Stimulus.
- `tests/Welfare` : tests unitaires du contrôle de sevrage.

Les règles métier détaillées sont dans `AGENTS.md`.
