# Symfony Reproducible Environment — Init Guide

> **Stack:** Symfony 7.4 LTS · PHP 8.4 · MariaDB 11.3 · Vite 5 · DaisyUI 4 · Tailwind 3  
> **Runtime:** Podman (rootless) · podman-compose · Docker-compatible  
> **Target deployment:** Debian server (Docker)  
> **Last validated:** April 2026

---

## Table of Contents

1. [Philosophy & Stack Rationale](#1-philosophy--stack-rationale)
2. [Prerequisites](#2-prerequisites)
3. [Project Structure](#3-project-structure)
4. [Containerfile (PHP-FPM)](#4-containerfile-php-fpm)
5. [Nginx Container](#5-nginx-container)
6. [docker-compose.yml](#6-docker-composeyml)
7. [Environment Variables](#7-environment-variables)
8. [Symfony Bootstrap](#8-symfony-bootstrap)
9. [Frontend: Vite + Tailwind + DaisyUI](#9-frontend-vite--tailwind--daisyui)
10. [Vite ↔ Symfony Bridge](#10-vite--symfony-bridge)
11. [First Controller & Template](#11-first-controller--template)
12. [Known Pitfalls & Fixes](#12-known-pitfalls--fixes)
13. [Git Snapshot Strategy](#13-git-snapshot-strategy)
14. [Next Steps: SSO + Applets](#14-next-steps-sso--applets)

---

## 1. Philosophy & Stack Rationale

### Python vs PHP reproducibility analogy

| Python | Symfony/PHP Equivalent |
|---|---|
| `uv` / `python -m venv` | Docker/Podman container |
| `requirements.txt` | `composer.json` |
| `uv.lock` / `poetry.lock` | `composer.lock` |
| `pip install` | `composer install` |
| `npm` / `bun` | `npm` / `bun` |

PHP has no native virtualenv. **The container IS the environment.** Reproducibility is enforced by:
- Pinning the PHP image tag (`php:8.4-fpm-alpine`)
- Committing `composer.lock` and `package-lock.json`
- Pinning MariaDB image tag (`mariadb:11.3`)
- Never using `:latest` tags in production

### Why Symfony 7.4 LTS

- End of maintenance: **11/2028**
- End of life: **11/2029**
- PHP 8.4 required — enforced by doctrine packages
- Avoid 7.1, 7.2, 7.3 — all expired as of 2026

---

## 2. Prerequisites

### On Fedora (dev machine)

```bash
# Podman (usually pre-installed)
sudo dnf install podman

# podman-compose
sudo dnf install podman-compose

# Composer — NEVER use dnf version (outdated)
cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig')) { echo 'Verified'; }"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
composer --version   # must be 2.7+
```

### On Debian (production server)

```bash
sudo apt install docker.io docker-compose-plugin
```

> `docker-compose.yml` is fully compatible with both `podman-compose` and Docker Compose.

---

## 3. Project Structure

```
digital-portal/
├── Containerfile                  # PHP-FPM image (Podman-native name)
├── docker-compose.yml
├── docker/
│   └── nginx/
│       ├── Containerfile          # Nginx image with baked config
│       └── default.conf           # Nginx site config
├── assets/
│   ├── app.js                     # Vite entry point
│   └── app.css                    # Tailwind directives
├── src/
│   └── Controller/
│       └── HomeController.php
├── templates/
│   ├── base.html.twig
│   └── home/
│       └── index.html.twig
├── config/
│   └── packages/
│       └── pentatrion_vite.yaml
├── composer.json                  # commit this
├── composer.lock                  # commit this — reproducibility anchor
├── package.json
├── package-lock.json              # commit this
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
└── .env                           # commit with placeholder values only
```

---

## 4. Containerfile (PHP-FPM)

```dockerfile
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git unzip curl bash zsh sudo \
    icu-dev oniguruma-dev libzip-dev \
    nodejs npm

RUN docker-php-ext-install \
    intl pdo pdo_mysql zip opcache mbstring

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Remap user to uid 1000 to match host user (Podman rootless requirement)
RUN deluser www-data 2>/dev/null || true \
    && addgroup -g 1000 appuser \
    && adduser -u 1000 -G appuser -s /bin/zsh -D appuser \
    && echo "appuser ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

# Configure php-fpm pool to run as appuser
RUN sed -i 's/user = www-data/user = appuser/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = appuser/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/portal

# Layer cache: reinstall only when lock files change
COPY --chown=appuser:appuser composer.json composer.lock* ./
RUN composer install --no-scripts --prefer-dist

COPY --chown=appuser:appuser package.json package-lock.json* ./
RUN npm install

COPY --chown=appuser:appuser . .

# Ensure correct ownership after full copy
RUN chown -R appuser:appuser /var/www/portal

USER appuser

EXPOSE 9000
CMD ["php-fpm"]
```

### Key decisions

- **No Symfony CLI** — installer path changes between versions, breaks rootless builds. Use `php bin/console` instead.
- **`--chown` on every COPY** — files land with correct owner immediately, no post-copy chown needed.
- **`composer install` before `COPY . .`** — Docker layer cache means deps only reinstall when `composer.lock` changes.

---

## 5. Nginx Container

### `docker/nginx/Containerfile`

```dockerfile
FROM nginx:1.25-alpine

# Bake config into image — avoids Podman rootless mount permission issues
RUN rm /etc/nginx/conf.d/default.conf
COPY default.conf /etc/nginx/conf.d/default.conf
RUN chown -R nginx:nginx /etc/nginx/conf.d
```

### `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    root /var/www/portal/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass portal:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $document_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

> **Why baked config?** Podman rootless cannot bind-mount files into nginx due to SELinux and user namespace mapping. Building the config into the image is the portable solution that works on both Podman (Fedora) and Docker (Debian).

---

## 6. docker-compose.yml

```yaml
version: "3.9"

networks:
  portal_net:
    driver: bridge

volumes:
  mariadb_data:

services:

  portal:
    build:
      context: .
      dockerfile: Containerfile
    security_opt:
      - label=disable        # disables SELinux labeling — portable across all distros
    userns_mode: "keep-id"   # Podman rootless: host uid 1000 = container uid 1000
    volumes:
      - .:/var/www/portal    # no :Z flag — SELinux disabled above
    networks:
      - portal_net
    ports:
      - "5173:5173"          # Vite HMR
      - "9000:9000"          # PHP-FPM
    env_file: .env

  nginx:
    build:
      context: ./docker/nginx
      dockerfile: Containerfile
    security_opt:
      - label=disable
    ports:
      - "8080:80"            # app accessible at localhost:8080
    volumes:
      - .:/var/www/portal
    networks:
      - portal_net

  db:
    image: mariadb:11.3
    security_opt:
      - label=disable
    env_file: .env
    environment:
      MARIADB_ROOT_PASSWORD: ${MARIADB_ROOT_PASSWORD}
      MARIADB_DATABASE: ${MARIADB_DATABASE}
      MARIADB_USER: ${MARIADB_USER}
      MARIADB_PASSWORD: ${MARIADB_PASSWORD}
    volumes:
      - mariadb_data:/var/lib/mysql
    networks:
      - portal_net
```

### Startup command

```bash
# Never use depends_on — causes podman-compose 1.5.0 to hang
podman-compose up -d
sleep 10
podman ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

---

## 7. Environment Variables

### `.env` (commit with placeholder values)

```bash
APP_ENV=dev
APP_SECRET=changeme_generate_with_openssl_rand_hex_32

MARIADB_ROOT_PASSWORD=rootsecret
MARIADB_DATABASE=portal
MARIADB_USER=portal_user
MARIADB_PASSWORD=portalsecret

DATABASE_URL="mysql://portal_user:portalsecret@db:3306/portal?serverVersion=mariadb-11.3.0"
```

> Use `MARIADB_*` prefix — not `DB_*`. The MariaDB image reads `MARIADB_*` variables natively.

### `.env.local` (never commit — real secrets)

```bash
APP_SECRET=<openssl rand -hex 32>
MARIADB_ROOT_PASSWORD=<real_password>
MARIADB_PASSWORD=<real_password>
```

---

## 8. Symfony Bootstrap

```bash
# Create project (run on host with correct composer version)
composer create-project symfony/skeleton:"7.4.*" digital-portal
cd digital-portal

# Install core packages
composer require \
  symfony/framework-bundle \
  symfony/dotenv \
  symfony/twig-bundle \
  symfony/security-bundle \
  symfony/orm-pack \
  symfony/yaml

# Install dev tools
composer require --dev symfony/maker-bundle

# Install Vite bridge
composer require pentatrion/vite-bundle
```

### Upgrade Symfony version (if needed)

1. Edit `composer.json` → find `extra.symfony.require` → change version string
2. Update all symfony/* packages in the same command:

```bash
composer require \
  symfony/console:"7.4.*" \
  symfony/framework-bundle:"7.4.*" \
  symfony/dotenv:"7.4.*" \
  symfony/runtime:"7.4.*" \
  symfony/security-bundle:"7.4.*" \
  symfony/twig-bundle:"7.4.*" \
  symfony/yaml:"7.4.*" \
  --update-with-all-dependencies
```

---

## 9. Frontend: Vite + Tailwind + DaisyUI

### `package.json`

```json
{
  "name": "digital-portal",
  "private": true,
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  },
  "devDependencies": {
    "vite": "^5.0.0",
    "vite-plugin-symfony": "^8.0.0",
    "tailwindcss": "^3.4.0",
    "autoprefixer": "^10.4.0",
    "postcss": "^8.4.0",
    "daisyui": "^4.0.0"
  }
}
```

### `vite.config.js`

```js
import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'

export default defineConfig({
  plugins: [
    symfonyPlugin({ stimulus: false }),
  ],
  server: {
    host: '0.0.0.0',
    port: 5173,
    origin: 'http://localhost:5173',   // critical for CORS inside container
    watch: {
      usePolling: true,                // required for Podman volume mounts
    },
  },
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.js',
      },
    },
  },
})
```

### `tailwind.config.js`

```js
export default {
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
  ],
  plugins: [
    (await import('daisyui')).default,
  ],
  daisyui: {
    themes: ['light', 'dark', 'corporate'],
    defaultTheme: 'corporate',
  },
}
```

### `postcss.config.js`

```js
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

### `assets/app.js`

```js
import './app.css'
console.log('Portal loaded')
```

### `assets/app.css`

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

---

## 10. Vite ↔ Symfony Bridge

### `config/packages/pentatrion_vite.yaml`

```yaml
pentatrion_vite:
    dev_server:
        url: 'http://localhost:5173'
```

### Usage in Twig templates

```twig
{{ vite_entry_link_tags('app') }}   {# in <head> #}
{{ vite_entry_script_tags('app') }} {# before </body> #}
```

### Start Vite dev server

```bash
# Inside portal container
podman exec -it digital-portal_portal_1 zsh
npm run dev
```

---

## 11. First Controller & Template

### `src/Controller/HomeController.php`

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
```

### `templates/base.html.twig`

```twig
<!DOCTYPE html>
<html lang="en" data-theme="corporate">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{% block title %}Digital Portal{% endblock %}</title>
  {{ vite_entry_link_tags('app') }}
</head>
<body class="min-h-screen bg-base-200">

  <div class="navbar bg-base-100 shadow-md px-4">
    <div class="navbar-start">
      <span class="text-xl font-bold text-primary">Digital Portal</span>
    </div>
    <div class="navbar-end">
      <div class="avatar placeholder">
        <div class="bg-neutral text-neutral-content rounded-full w-8">
          <span class="text-xs">{{ app.user ? app.user.email[0]|upper : '?' }}</span>
        </div>
      </div>
    </div>
  </div>

  <div class="flex min-h-[calc(100vh-4rem)]">
    <aside class="w-64 bg-base-100 shadow-inner p-4 hidden md:block">
      <ul class="menu menu-vertical gap-1">
        <li><a href="/" class="active">🏠 Dashboard</a></li>
        <li><a href="#">📦 Applet CRM</a></li>
        <li><a href="#">👥 Applet HR</a></li>
      </ul>
    </aside>
    <main class="flex-1 p-6">
      {% block body %}{% endblock %}
    </main>
  </div>

  {{ vite_entry_script_tags('app') }}
</body>
</html>
```

---

## 12. Known Pitfalls & Fixes

### A — Composer from dnf is outdated
Always install Composer from getcomposer.org, not from the distro package manager.

### B — Symfony version pinned in extra.symfony.require
When upgrading versions, edit `extra.symfony.require` in `composer.json` first, then run the upgrade command. Forgetting this causes Flex to restrict all packages to the old version.

### C — podman-compose 1.5.0 hangs with depends_on
Remove all `depends_on` entries. Start containers individually or all at once with `podman-compose up -d`.

### D — Nginx permission denied on config mount
Never bind-mount nginx config files in Podman rootless. Bake config into the nginx image using a dedicated `Containerfile`.

### E — Volume files owned by root inside container
Podman rootless maps host uid to root inside the container namespace. Fix with `userns_mode: keep-id` + `security_opt: label=disable` in compose.

### F — CORS error for Vite assets
Set `server.origin: 'http://localhost:5173'` in `vite.config.js` and match it in `pentatrion_vite.yaml`. The `0.0.0.0` bind address is for the container network — the browser must use `localhost`.

### G — vite-plugin-symfony version mismatch
When pentatrion/vite-bundle v8 is installed, run:
```bash
npm install vite-plugin-symfony@^8
```

### H — PHP version too low for doctrine
Doctrine 3.x and related bundles require PHP 8.4+. Use `FROM php:8.4-fpm-alpine` in the Containerfile.

### I — @symfony/vite-dev-server does not exist
The correct npm package name is `vite-plugin-symfony`. There is no `@symfony/vite-dev-server` on npm.

### J — package.json contains JS instead of JSON
If `npm run dev` gives `JSONParseError: Unexpected token "i"... "import { defineConfig }"`, the contents of `vite.config.js` were accidentally pasted into `package.json`. Replace with valid JSON.

---

## 13. Git Snapshot Strategy

```bash
# Initial snapshot after environment is working
git add -A
git commit -m "feat: stable base — Symfony 7.4 LTS + PHP 8.4 + MariaDB 11.3 + Vite + DaisyUI

- Containerfile: PHP 8.4, appuser uid 1000, composer + npm layer cache
- docker-compose: portal + nginx + mariadb, SELinux disabled, portable
- Symfony 7.4 LTS (EOL 2029)
- Vite 5 + vite-plugin-symfony 8 + DaisyUI 4 wired and rendering
- Base layout verified at localhost:8080"

git tag v0.1.0-base
git push origin main --tags
```

### Restore from snapshot on a new machine

```bash
git clone <repo>
cd digital-portal
podman-compose build
podman-compose up -d
# Enter container and start Vite
podman exec -it digital-portal_portal_1 zsh
npm run dev
```

---

## 14. Next Steps: SSO + Applets

### Architecture target

```
Browser
  │
  ▼
nginx:8080  (only public entry point)
  │
  ├── /              → Symfony portal (HomeController)
  ├── /auth/*        → Keycloak (proxied by portal)
  └── /applet/crm    → portal proxies to applet_crm container
  └── /applet/hr     → portal proxies to applet_hr container

Internal network only (no ports exposed):
  - keycloak
  - applet_crm
  - applet_hr
  - db
```

### Remaining implementation steps

1. **Keycloak SSO** — add Keycloak container, configure realm, install `knpuniversity/oauth2-client-bundle`
2. **Symfony Security** — configure firewall, OAuth2 authenticator, user provider
3. **MariaDB + Doctrine** — User entity, migrations, session storage
4. **Reverse proxy controller** — Symfony HttpClient proxying requests to applet containers
5. **Per-applet access control** — roles from Keycloak token mapped to applet visibility
6. **Production Containerfile** — multi-stage build, `composer install --no-dev`, `npm run build`
