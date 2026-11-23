# Deployment Guide

> GitHub Actions & EasyEngine v4

This guide documents the automated continuous deployment (CD) pipeline configured in [`.github/workflows/deploy.yml`](workflows/deploy.yml) for deploying Gymie to an EasyEngine v4 server.

## Architecture Overview

* **Orchestration**: GitHub Actions via SSH (`appleboy/ssh-action`).
* **Triggers**:
  * **Automatic**: On every `push` to the `3.x` branch.
  * **Manual (`workflow_dispatch`)**: On-demand deployment of any branch from the GitHub Actions web UI.
* **Security & Privacy**: Zero hardcoded server domains, paths, or provider names. All server details and credentials are securely injected via GitHub Secrets.
* **Server Stack**: EasyEngine v4 (Docker-based PHP-FPM, Nginx, MariaDB).
  * Host OS executes Git and Vite asset builds (`npm run build`).
  * EasyEngine container (`ee shell`) executes PHP, Composer, migrations, and post-deploy scripts as `www-data`.

## 1. Required GitHub Secrets

Navigate to **Repository Settings** &rarr; **Secrets and variables** &rarr; **Actions** &rarr; **New repository secret** and configure the following:

| Secret Name | Required | Description | Example |
| :--- | :---: | :--- | :--- |
| `SSH_HOST` | **Yes** | Server IP address or hostname | `143.198.xxx.xxx` |
| `SSH_USER` | **Yes** | SSH user with access to the host (usually `root`) | `root` |
| `SSH_PRIVATE_KEY` | **Yes** | Private SSH key matching the server's `authorized_keys` | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `EE_SITE_NAME` | **Yes** | EasyEngine site domain / name | `next.example.com` |
| `SSH_PORT` | No | Custom SSH port (defaults to `22` if omitted) | `22` |
| `EE_SITE_DIR` | No | Custom htdocs path (defaults to `/opt/easyengine/sites/${SITE_NAME}/app/htdocs`) | `/opt/easyengine/sites/next.example.com/app/htdocs` |
| `SSH_PASSPHRASE` | No | Passphrase for private key (if protected) | `my-secret-passphrase` |

## 2. Server Setup & Prerequisites (One-Time)

Before the automated workflow runs, ensure the target server has had an initial manual deployment:

### A. Dedicated SSH Key Pair for Deployments
To allow GitHub Actions to SSH into the server securely, generate a dedicated deploy key pair:

```bash
# On your local machine or server:
ssh-keygen -t ed25519 -C "github-actions-gymie-deploy" -f ~/.ssh/gymie_deploy
```

1. Copy the public key (`~/.ssh/gymie_deploy.pub`) and append it to `/root/.ssh/authorized_keys` on your server.
2. Copy the full contents of the private key (`~/.ssh/gymie_deploy`) into the `SSH_PRIVATE_KEY` secret in GitHub.

### B. Site Working Directory & Git Remote
Ensure the site directory exists and has the Git remote configured:

```bash
cd /opt/easyengine/sites/<site-name>/app/htdocs
git remote -v
```

Ensure the server has permissions to fetch from GitHub (e.g. via a GitHub Deploy Key or credentials).

### C. Host Requirements
* **Node.js & npm** must be installed on the host server to compile frontend assets with Vite:
  ```bash
  node -v
  npm -v
  ```
* **EasyEngine CLI (`ee`)** must be available:
  ```bash
  ee --version
  ```

## 3. Deployment Workflow Lifecycle

Every deployment follows this structured sequence:

```mermaid
flowchart TD
    A["Trigger: Push to 3.x or Manual Dispatch"] --> B["SSH into Host Server"]
    B --> C["Load Environment (PATH, nvm, bashrc)"]
    C --> D["Git Reset & Clean (preserving .env)"]
    D --> E["Git Fetch & Checkout Target Branch"]
    E --> F["Host: npm ci && npm run build"]
    F --> G["Host: chown -R www-data:www-data ."]
    G --> H["Container: ee shell (composer install && composer deploy-demo)"]
    H --> I["Host: Enforce Permissions (chmod 775 storage & bootstrap/cache)"]
    I --> J["Host: Reload EasyEngine Site (PHP-FPM / OPcache)"]
    J --> K["Deployment Complete!"]
```

### Detailed Steps:
1. **Load Environment**: Sources `/usr/local/bin`, NVM, and user profiles so `node`, `npm`, and `ee` binaries are in `$PATH` during non-interactive SSH sessions.
2. **Clean Working Tree**: Runs `git reset --hard HEAD` and `git clean -fd -e .env` to cleanly discard modified/untracked files while strictly preserving `.env`.
3. **Switch Branch**: Runs `git fetch origin <branch>` and `git reset --hard origin/<branch>`.
4. **Compile Assets**: Installs npm dependencies and builds Vite assets on the host.
5. **Pre-Container Permissions**: Runs `chown -R www-data:www-data .` so the container user has write permissions to `vendor/` and `composer.lock`.
6. **Container Post-Deploy (`ee shell`)**: Executes `composer install --no-interaction --prefer-dist --optimize-autoloader && composer deploy-demo` inside the EasyEngine container.
7. **Permission Enforcement**: Re-applies `chown -R www-data:www-data .` and `chmod -R 775 storage bootstrap/cache` to ensure zero file-permission surprises.
8. **Reload OPcache**: Executes `ee site reload <site> --php` to flush PHP-FPM OPcache so updated code is immediately live.

## 4. Composer Deployment Scripts Reference

In [`composer.json`](../composer.json), two deployment scripts are available:

### `composer deploy-demo` (Active in CD workflow)
Designed specifically for **demo environments** where every deploy resets the database:
```json
"deploy-demo": [
    "@php artisan storage:link",
    "@php artisan migrate:fresh --seed --force --ansi",
    "@php artisan shield:generate --all --panel=admin --ansi",
    "@php artisan filament:upgrade",
    "@php artisan livewire:publish --assets --ansi",
    "@php artisan optimize:clear --ansi",
    "@php artisan optimize --ansi"
]
```
* Runs `migrate:fresh --seed` to wipe test clutter and re-seed clean demo data.
* Automatically creates the demo super admin (`test@example.com` / `test`).
* Generates all Filament Shield permissions and assigns them to `super_admin`.
* Publishes Filament & Livewire frontend assets.
* Rebuilds Laravel route, config, and view caches.

### `composer deploy` (Standard / Production)
Designed for **live / persistent environments** where data must be kept:
```json
"deploy": [
    "@php artisan storage:link",
    "@php artisan migrate --force --ansi",
    "@php artisan shield:generate --all --panel=admin --ansi",
    "@php artisan filament:upgrade",
    "@php artisan livewire:publish --assets --ansi",
    "@php artisan optimize:clear --ansi",
    "@php artisan optimize --ansi"
]
```
* Runs `migrate --force` without wiping the database.

## 5. How to Deploy

### Automatic Deployment
Pushing or merging a PR into `3.x` will automatically trigger the deployment workflow.

### Manual Deployment (Any Branch)
1. Go to **Actions** in your GitHub repository.
2. Click **Deploy** in the left sidebar.
3. Click **Run workflow**.
4. Type or select the branch you wish to deploy (defaults to `3.x`).
5. Click **Run workflow**.

## 6. Manual Server Commands (CLI Shortcut)

If you are logged into the server shell via SSH and need to run the deployment or refresh assets manually, run:

```bash
# Run the demo deployment cycle inside the container
ee shell <site-name> --command="composer deploy-demo"

# Fix permissions on host
cd /opt/easyengine/sites/<site-name>/app/htdocs
chown -R www-data:www-data .
chmod -R 775 storage bootstrap/cache

# Reload PHP-FPM container
ee site reload <site-name> --php
```
