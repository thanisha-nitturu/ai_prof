# Infisical Secret Management Setup Guide

This document describes how to deploy the Infisical self-hosted instance using Docker Compose, set up secret keys inside the Infisical dashboard, and integrate it with your Moodle environments.

---

## Part 1: Infisical Self-Hosted Docker Setup

To host your own Infisical instance, you will need a host with **Docker** and **Docker Compose** installed.

### 1.1 Create the Directory and Config Files
Create a new directory on your host server (e.g., `/opt/services/infisical`) and create the following two files:

#### File 1: `docker-compose.yml`
```yaml
version: "3"

services:
  backend:
    container_name: infisical-backend
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_started
    image: infisical/infisical:latest
    pull_policy: always
    env_file: .env
    ports:
      - 127.0.0.1:8082:8080
    environment:
      - NODE_ENV=production
    networks:
      - infisical

  redis:
    image: redis
    container_name: infisical-dev-redis
    env_file: .env
    restart: always
    environment:
      - ALLOW_EMPTY_PASSWORD=yes
    networks:
      - infisical
    volumes:
      - redis_data:/data

  db:
    container_name: infisical-db
    image: postgres:14-alpine
    restart: always
    env_file: .env
    volumes:
      - pg_data:/var/lib/postgresql/data
    networks:
      - infisical
    healthcheck:
      test: "pg_isready --username=${POSTGRES_USER} && psql --username=${POSTGRES_USER} --list"
      interval: 5s
      timeout: 10s
      retries: 10

volumes:
  pg_data:
    driver: local
  redis_data:
    driver: local

networks:
  infisical:
```

#### File 2: `.env` (Infisical Config)
Create the configuration file to initialize the security keys and database connections:
```env
# Encryption keys
# Generate a random 32-byte hex key for ENCRYPTION_KEY (e.g. using openssl rand -hex 16)
ENCRYPTION_KEY=f13dbc92aaaf86fa7cb0ed8ac3265f47

# JWT sign secret (e.g., openssl rand -base64 32)
AUTH_SECRET=5lrMXKKWCVocS/uerPsl7V+TX/aaUaI7iDkgl3tSmLE=

# Postgres credentials
POSTGRES_PASSWORD=infisical
POSTGRES_USER=infisical
POSTGRES_DB=infisical

# DB & Redis connection strings
DB_CONNECTION_URI=postgres://${POSTGRES_USER}:${POSTGRES_PASSWORD}@db:5432/${POSTGRES_DB}
REDIS_URL=redis://redis:6379

# Site access URL
SITE_URL=http://localhost:8082
```

> [!IMPORTANT]
> Change the default `ENCRYPTION_KEY` and `AUTH_SECRET` before deploying to a staging/production server. You can generate secure secrets using:
> * `openssl rand -hex 16` for `ENCRYPTION_KEY`
> * `openssl rand -base64 32` for `AUTH_SECRET`

### 1.2 Run Infisical
Start the containers using Docker Compose:
```bash
docker compose up -d
```

Verify that the containers are running and healthy:
```bash
docker ps
```

---

## Part 2: Creating Projects and Secrets in the Dashboard

Once the containers are running, the web UI will be accessible at `http://localhost:8082` (or your configured `SITE_URL`).

### 2.1 First-Time Setup
1. Open the Infisical Dashboard in your browser: `http://localhost:8082`.
2. Sign up and register the primary administrator account.
3. Create an **Organization** (e.g. `My Company`).

### 2.2 Create a Project
1. From the dashboard sidebar, select **Projects** > **Create New Project**.
2. Name the project (e.g., `Moodle Core`).
3. You will see default environments pre-created for you: `Development` (dev), `Staging` (staging), and `Production` (prod).

### 2.3 Setting Secret Keys
1. Click on the project you created.
2. In the sidebar, go to **Secrets** and select your target environment (e.g. `dev`).
3. Click the **Add Secret** button at the top-right.
4. Input the keys and values required for Moodle:
   * **Key**: `MOODLE_DB_HOST` | **Value**: `<db-host>`
   * **Key**: `MOODLE_DB_NAME` | **Value**: `<db-name>`
   * **Key**: `MOODLE_DB_USER` | **Value**: `<db-user>`
   * **Key**: `MOODLE_DB_PASS` | **Value**: `<db-password>`
   * **Key**: `MOODLE_GENERATOR_PASS` | **Value**: `<user-generator-password>`
   * **Key**: `MOODLE_REDIS_HOST` | **Value**: `<redis-host-ip>`
   * **Key**: `MOODLE_REDIS_PORT` | **Value**: `6379`
   * **Key**: `MOODLE_VEDA_API_URL` | **Value**: `http://localhost:8066`
   * **Key**: `MOODLE_VEDA_NEXTJS_URL`| **Value**: `http://localhost:3002`
5. Click **Save Changes** or **Commit Changes** at the bottom/top-right of the screen to persist the secrets.

---

## Part 3: Creating Universal Auth (Machine Credentials)

To allow the Moodle server (which acts as a machine client) to fetch secrets without human intervention:

1. In the project dashboard sidebar, navigate to **Access Control** (or Organization Settings > Access Control).
2. Go to the **Universal Auth** tab.
3. Click **Create client / identity**. Name it (e.g. `moodle-m2m-loader`).
4. **Configure permissions**: Add a policy scope that permits the client to **Read** secrets inside your project, for the path `/`, and select the environment(s) (e.g., `dev`).
5. **Get Credentials**:
   * Generate and copy the **Client ID**.
   * Generate and copy the **Client Secret** (keep this secret!).
6. Locate your **Project ID** from the URL or project settings dashboard.

---

## Part 4: Moodle Code Integration

Now that Infisical is running and holds the secrets, integrate it into the target Moodle instance:

### 4.1 Install PHP Dependencies on Target
```bash
composer require infisical/php-sdk:^0.0.2 vlucas/phpdotenv:^5.6
```

### 4.2 Configure the `.env` Credentials in Moodle Root
Create a `.env` file at the root of the Moodle folder:
```env
INFISICAL_CLIENT_ID=<Your-Universal-Auth-Client-ID>
INFISICAL_CLIENT_SECRET=<Your-Universal-Auth-Client-Secret>
INFISICAL_PROJECT_ID=<Your-Infisical-Project-ID>
```

### 4.3 Configure `config.php`
Paste this block at the very top of `config.php` to fetch secrets dynamically:

```php
// ═══════════════════════════════════════════════════════════════════
// INFISICAL SECRET LOADER
// All secrets are fetched exclusively from Infisical.
// ═══════════════════════════════════════════════════════════════════
require_once __DIR__ . '/vendor/autoload.php';

use Infisical\SDK\InfisicalSDK;
use Infisical\SDK\Models\GetSecretParameters;

// Load client keys from .env file
$dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__);
$dotenv->load();

$_inf_client_id     = $_ENV['INFISICAL_CLIENT_ID']     ?? getenv('INFISICAL_CLIENT_ID');
$_inf_client_secret = $_ENV['INFISICAL_CLIENT_SECRET'] ?? getenv('INFISICAL_CLIENT_SECRET');
$_inf_project_id    = $_ENV['INFISICAL_PROJECT_ID']    ?? getenv('INFISICAL_PROJECT_ID');

// Initialize the SDK and authenticate
$_inf_sdk = new InfisicalSDK('http://localhost:8082');
$_inf_sdk->auth()->universalAuth()->login($_inf_client_id, $_inf_client_secret);

// Closure to retrieve individual keys
$_inf_get = static function (string $name) use ($_inf_sdk, $_inf_project_id): string {
    $params = new GetSecretParameters(
        secretKey:   $name,
        environment: 'dev',          // Set this matching environment: e.g. 'dev', 'staging', 'prod'
        projectId:   $_inf_project_id,
        secretPath:  '/'
    );
    return $_inf_sdk->secrets()->get($params)->secretValue;
};

// Retrieve environment-specific configurations
$_s = [
    'MOODLE_DB_HOST'         => $_inf_get('MOODLE_DB_HOST'),
    'MOODLE_DB_NAME'         => $_inf_get('MOODLE_DB_NAME'),
    'MOODLE_DB_USER'         => $_inf_get('MOODLE_DB_USER'),
    'MOODLE_DB_PASS'         => $_inf_get('MOODLE_DB_PASS'),
    'MOODLE_GENERATOR_PASS'  => $_inf_get('MOODLE_GENERATOR_PASS'),
    'MOODLE_REDIS_HOST'      => $_inf_get('MOODLE_REDIS_HOST'),
    'MOODLE_REDIS_PORT'      => $_inf_get('MOODLE_REDIS_PORT'),
    'MOODLE_VEDA_API_URL'    => $_inf_get('MOODLE_VEDA_API_URL'),
    'MOODLE_VEDA_NEXTJS_URL' => $_inf_get('MOODLE_VEDA_NEXTJS_URL'),
];
// ═══════════════════════════════════════════════════════════════════
```

Map those variables directly to your `$CFG` global variables:
```php
global $CFG;
$CFG = new stdClass();

$CFG->dbhost    = $_s['MOODLE_DB_HOST'];
$CFG->dbname    = $_s['MOODLE_DB_NAME'];
$CFG->dbuser    = $_s['MOODLE_DB_USER'];
$CFG->dbpass    = $_s['MOODLE_DB_PASS'];
// ... (continue matching all fields from $_s)
```
