# Prono F1

Application de pronostics F1 entre amis : pronostic de classement général en début de
saison, pronostic par Grand Prix chaque week-end, pronos de risque, et classement des
joueurs.

## Stack

- **Backend** : Symfony 8.1 + API Platform (REST/JSON-LD) + Doctrine ORM + PostgreSQL,
  auth par JWT (LexikJWTAuthenticationBundle).
- **Frontend** : Next.js 16 (App Router) + TypeScript + Tailwind CSS + SWR.
- **Données F1** : synchronisées depuis [jolpica-f1](https://github.com/jolpica/jolpica-f1)
  (l'héritier communautaire de l'API Ergast, désormais figée).

## Règles du jeu

- **Prono classement général** (une fois par saison, avant les EL1 du 1er Grand Prix) :
  classement complet prédit des pilotes et des constructeurs. Chaque pilote/écurie
  rapporte les points de sa tranche si sa position réelle en fin de saison tombe dans la
  même tranche que la position prédite :
  - Pilotes : 1er = 20 pts, 2-3e = 10 pts, 4-5e = 6 pts, 6-10e = 4 pts, 11e+ = 2 pts.
  - Constructeurs : 1er = 10 pts, 2-3e = 5 pts, 4-10e = 2 pts.
- **Prono par Grand Prix** (avant les EL1 du week-end) : classement complet prédit de la
  course, + pole, + meilleur tour, + podium sprint (si sprint). Même mécanique de
  tranche pour le classement course :
  - Vainqueur = +5, 2-3e = +3, 4-5e = +2, 6-10e = +1.
  - Pole = +1, meilleur tour = +1, chaque position du podium sprint correcte = +1.
- **Fair-play** : les pronos d'un joueur restent masqués aux autres tant que le délai
  n'est pas passé.
- **Prono de risque** : pari libre proposé par un joueur à tout moment de la saison,
  activé seulement si tous les autres joueurs l'approuvent à l'unanimité, puis résolu
  gagné/perdu (gain ou perte de points), le proposant ne votant ni ne résolvant son
  propre pari.

## Démarrage rapide

### 1. Base de données

```bash
docker compose up -d database
```

Postgres écoute sur `localhost:5433` (le `5432` local n'était pas libre sur cette
machine — adapte `docker-compose.yml` et `backend/.env` si besoin).

### 2. Backend

```bash
cd backend
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Code d'invitation partagé, requis pour l'inscription — remplace la valeur par défaut
echo "REGISTRATION_INVITE_CODE=$(php -r 'echo bin2hex(random_bytes(6));')" >> .env.local

# Récupère le calendrier, pilotes, écuries, classements et résultats de la saison
php bin/console app:f1:sync 2026

symfony server:start -d --port=8000 --no-tls
# ou : php -S 127.0.0.1:8000 -t public
```

L'API est servie sur `http://127.0.0.1:8000/api`. Lance les tests avec :

```bash
php bin/console --env=test doctrine:database:create --if-not-exists
php bin/console --env=test doctrine:migrations:migrate --no-interaction
php vendor/bin/phpunit
```

### 3. Frontend

```bash
cd frontend
cp .env.example .env.local   # ajuste NEXT_PUBLIC_API_URL si besoin
npm install
npm run dev
```

Ouvre `http://localhost:3000`, crée un compte avec le code d'invitation généré
ci-dessus, et c'est parti.

## Synchronisation des données F1

`php bin/console app:f1:sync <année>` peut être relancée à tout moment (elle
upsert tout par référence externe stable) : c'est ce qui met à jour le calendrier, les
alignements pilote/écurie, les classements, les résultats de course/qualifs/sprint, et
qui **recalcule automatiquement les scores** de tous les joueurs. À planifier en cron
quelques heures après chaque séance (l'API publique jolpica-f1 est limitée en débit, la
commande synchronise donc son propre rythme d'appel — une saison complète prend 1 à 2
minutes).

## Points d'API notables

- `POST /api/register`, `POST /api/login_check`, `GET /api/me`
- `PUT /api/predictions/season/{seasonId}` — pose/modifie le prono classement général
- `PUT /api/predictions/gp/{grandPrixId}` — pose/modifie le prono d'un Grand Prix
- `GET /api/leaderboard/{seasonId}` — classement agrégé (GP + saison + risque)
- `POST /api/risk-bets`, `.../{id}/vote`, `.../{id}/resolve`, `.../{id}/cancel`
- Le reste (pilotes, écuries, résultats, classements, pronos en lecture…) suit les
  conventions REST standard d'API Platform, explorables sur `/api` (Swagger UI).

## Décisions prises faute de précision, à ajuster si besoin

- **Inscription** : ouverte à quiconque connaît un code d'invitation partagé
  (`REGISTRATION_INVITE_CODE`), pas de validation admin — cohérent avec un groupe
  d'amis fermé mais informel.
- **Résolution des pronos de risque** : n'importe quel joueur autre que le proposant
  peut la faire (pas de rôle "arbitre" dédié) — repose sur la confiance du groupe, comme
  la règle de fair-play.
- **Score du classement général** : recalculé à chaque synchronisation à partir du
  classement *courant*, donc lisible comme un « si la saison s'arrêtait aujourd'hui »
  tout au long de l'année ; le score final se fige naturellement à la dernière sync de
  la saison.
