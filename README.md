# 🪙 Veckopeng

Mobilvänlig webbapp för att hantera barnens veckopeng. Föräldrar skapar konton, lägger till barn och följer upp krav, avdrag och bonusar varje vecka.

## Funktioner

- **Barnprofiler** med profilfärg och individuell veckopeng
- **Veckovy** (Mån–Sön) med dagliga kryssrutor för krav
- **Snabbknappar** för avdrag och bonus (t.ex. –5 kr, +10 kr)
- **Dynamiska krav och avdragstyper** – lägg till och inaktivera fritt
- **Veckosammanställning** med slutlig veckopeng och status (Betald / Skickad / Skyldig)
- **Historik** per barn med totalt utbetalt och utestående
- Mobil-first UI med Tailwind CSS och Alpine.js

## Snabbstart med Docker Compose

```bash
# Klona
git clone https://github.com/GITHUB_USER/veckopeng.git
cd veckopeng

# Starta
docker compose up --build

# Öppna i webbläsaren
open http://localhost:8080
```

Databasen initieras automatiskt via `database/schema.sql` vid uppstart.

## Projektstruktur

```
veckopeng/
├── public/          # Webbsidor (index, dashboard, child, settings, history)
├── api/             # JSON-endpoints och form-handlers
├── src/             # config.php, auth.php, functions.php, layout.php
├── database/
│   └── schema.sql   # PostgreSQL-schema
├── docker/php/      # Dockerfile, nginx.conf, php.ini, entrypoint.sh
├── k8s/             # Kubernetes-manifests
└── docker-compose.yml
```

## Miljövariabler

| Variabel   | Standardvärde      | Beskrivning           |
|------------|--------------------|-----------------------|
| `DB_HOST`  | `postgres`         | PostgreSQL-host       |
| `DB_PORT`  | `5432`             | PostgreSQL-port       |
| `DB_NAME`  | `veckopeng`        | Databasnamn           |
| `DB_USER`  | `veckopeng`        | Databasanvändare      |
| `DB_PASS`  | `veckopeng_secret` | Lösenord (byt i prod) |

## Deployment på Kubernetes

### Förutsättningar
- Kubernetes-kluster med `metrics-server` (för HPA)
- nginx Ingress Controller
- (Valfritt) cert-manager för TLS

### Bygg och pusha Docker-image

```bash
docker build -t ghcr.io/GITHUB_USER/veckopeng:latest .
docker push ghcr.io/GITHUB_USER/veckopeng:latest
```

### Applicera manifests

```bash
# Byt ut GITHUB_USER i k8s/deployment.yaml och domän i k8s/ingress.yaml
# Uppdatera lösenord i k8s/secret.yaml (base64-koda dina värden)

kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/secret.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/deployment.yaml
kubectl apply -f k8s/service.yaml
kubectl apply -f k8s/hpa.yaml
kubectl apply -f k8s/ingress.yaml
```

### HPA – autoskalning

Appen skalas automatiskt mellan **2 och 10 repliker** baserat på:
- CPU-användning > 60 %
- Minnesanvändning > 75 %

```bash
kubectl get hpa -n veckopeng
kubectl get pods -n veckopeng
```

## Databas

### Schema (tabeller)

| Tabell              | Innehåll                                        |
|---------------------|-------------------------------------------------|
| `users`             | Föräldrar – e-post, lösenordshash, namn         |
| `children`          | Barnprofiler med veckopeng och profilfärg       |
| `requirements`      | Dagliga krav per barn                           |
| `deduction_types`   | Avdrags- och bonustyper med belopp              |
| `daily_logs`        | Vilka krav som uppfyllts per dag                |
| `adjustments`       | Avdrag och bonusar per dag                      |
| `weekly_summaries`  | Veckosammanställningar med status               |

## Säkerhet

- Lösenord hashade med `password_hash(PASSWORD_BCRYPT)`
- CSRF-skydd på alla formulär och API-anrop
- PDO prepared statements (SQL-injection-skydd)
- Ägarskapsverifiering (föräldrar ser bara sina egna barn)
- `session.cookie_httponly`, `session.use_strict_mode` aktiverat
- Nginx-säkerhetsheaders (X-Frame-Options, X-Content-Type-Options)

## Teknikstack

- **Backend**: PHP 8.2, PDO + PostgreSQL
- **Frontend**: Tailwind CSS (CDN), Alpine.js (CDN)
- **Server**: Nginx + PHP-FPM (Alpine)
- **Container**: Docker / Kubernetes
- **Autoskalning**: HPA (CPU + Memory)
