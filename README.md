# ai_prof Platform

This repository contains the generic Moodle installation for the **ai_prof** platform. It uses a strictly managed Git workflow with Docker and Infisical for secret management.

---

## 🚀 How to Setup a New Server (Clean Clone Process)

Follow these exact steps when pulling this code onto a new server (e.g., Staging or Production).

### 1. Clone the Repository (with Submodules)
Because we use custom plugins hosted in separate repositories, you **must** use the `--recurse-submodules` flag:
```bash
git clone --recurse-submodules git@github.com:thanisha-nitturu/ai_prof.git /var/www/html/moodle
cd /var/www/html/moodle
```
*(If you already cloned normally, run `git submodule update --init --recursive` to pull them in).*

### 2. Choose Your Branch
Switch to the branch that matches your server environment:
```bash
git checkout staging   # For staging server
# OR
git checkout main      # For production server
```

### 3. Create the Moodledata Directory
Moodle requires a persistent data folder **outside** of the web root. Create it and assign permissions:
```bash
# Example for staging. Change path as needed!
mkdir -p /var/www/moodledata-staging
chown -R www-data:www-data /var/www/moodledata-staging
```

### 4. Setup Environment Variables
Never put real credentials in Git! We use `.env` files and Infisical.
```bash
cp .env.example .env
nano .env
```
Fill in your specific values in the `.env` file (e.g., `INFISICAL_CLIENT_ID`, `INFISICAL_PROJECT_ID`, `MOODLE_ENV`, `COMPOSE_PROJECT_NAME`).

**Important Infisical Variables:**
You must configure the following secrets inside your Infisical dashboard for the environment to work correctly:
* `DB_HOST` - Database host address
* `DB_NAME` - Database name
* `DB_USER` - Database username
* `DB_PASS` - Database password
* `DB_PORT` - Database port (usually 3306 or 5432)
* `MOODLE_URL` - The public URL of your Moodle instance (e.g., https://app.aiprof.com)

### 5. Setup Moodle Config
Copy the template configuration. It automatically reads your `.env` settings, so you don't need to edit it!
```bash
cp config.php.template config.php
```

### 6. Install PHP Dependencies
Install the required packages securely:
```bash
composer install --no-dev --optimize-autoloader
```

### 7. Start Docker Containers
Build and start the web, cron, and redis containers:
```bash
docker compose up -d --build
```

### 8. Finalize Moodle Installation
If this is a **brand new database**, run the install script (replace `<your-project>` with your `COMPOSE_PROJECT_NAME` from `.env`):
```bash
docker exec <your-project>-web php admin/cli/install_database.php --agree-license --fullname="ai_prof" --shortname="aiprof" --adminuser=admin --adminpass=<your-password>
```
If you are **connecting to an existing database**, just run the upgrade script:
```bash
docker exec <your-project>-web php admin/cli/upgrade.php --non-interactive
```

Finally, purge the caches:
```bash
docker exec <your-project>-web php admin/cli/purge_caches.php
```

---

## 🛡️ Git Workflow Rules

* **NEVER code directly on `dev`, `staging`, or `main`.** Always use feature branches (`feature/xxx`).
* **NEVER commit `.env` or `config.php`.**
* To update submodules to a newer version, pull the latest commit in the submodule folder, then commit that change to the parent repository.
