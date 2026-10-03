# Stockroom — Laboratory Exercise No. 6

Product management using **React + LavaLust API + MySQL**. The current setup runs on **WAMP**, as requested. No cloud accounts are needed to use it locally.

## Open the app

Start WAMP's Apache and MySQL services, then open:

**http://localhost/lab6/**

Local demo login:

- Username: `admin`
- Password: `Stockroom123!`

You can also create your own account from the login page. Admins can create, view, edit, and delete products. Registered users have view-only access to the shared product inventory. The demo includes six sample products; the normal CRUD interface lets you change or delete them.

## Set up another local copy

Use PHP 8.2 or newer (WAMP's PHP 8.5 is supported), with `pdo_mysql` and `mbstring`. Put the project in `C:\wamp64\www\lab6`, start WAMP, and run these commands from the project root:

```powershell
php scripts/setup-local.php
php lava migration run
php scripts/seed-demo.php
npm.cmd --prefix frontend ci
npm.cmd --prefix frontend run build
```

`setup-local.php` creates a `.env` with random authentication secrets and the project's `lab6_stockroom` database. It uses local MySQL on port 3306 with the WAMP `root` user and an empty password. If your MySQL configuration differs, update `.env` before running the setup script. The script preserves an existing `.env` and does not modify other databases.

The demo seeder is optional. It only runs against local development databases, preserves an existing demo user's password, and only adds products if the inventory is empty.

The built React app is served by Apache from `frontend/dist`. Rebuild after changing the frontend. The root URL redirects to that folder. If you rename the project folder, update the redirect in `.htaccess`, the proxy in `frontend/vite.config.js`, and the production local API fallback in `frontend/src/api.js`.

## Frontend development

```powershell
npm.cmd --prefix frontend run dev
```

Open **http://localhost:5173**. Keep WAMP running; Vite proxies `/api` to `http://localhost/lab6/public/api`. The standalone `php lava serve` server is optional. If you use it on port 3000, change the proxy target to `http://127.0.0.1:3000`.

## Features

- Registration, username/email login, logout, and session restoration after reload.
- JWT access tokens with refresh token rotation through the LavaLust API library.
- ProductMiddleware protects every product route: authenticated users can read; only active admins can create, update, or delete.
- ProductModel handles product database operations with an allowlist of writable fields.
- Product name, description, price, quantity, and creation timestamp.
- Add and edit forms with frontend and backend validation.
- Delete confirmation with an option to cancel.
- Product search, sorting, stock filters, and inventory summaries.
- Responsive desktop and mobile interface, loading states, errors, and success messages.
- Prepared SQL statements, bcrypt passwords, login rate limiting, and environment-based secrets.

Tokens are stored in the current tab's `sessionStorage`, and are sent in the `Authorization: Bearer` header. Logout revokes the refresh token and clears the frontend session. An already issued access token lasts up to 15 minutes. Users, refresh tokens, and products are stored in MySQL; React never connects directly to the database.

## Database and migrations

Open **http://localhost/phpmyadmin/** and select `lab6_stockroom`.

Tables: `migrations`, `users`, `refresh_tokens`, `products`.

The product schema follows the laboratory document: `id INT AUTO_INCREMENT PRIMARY KEY`, `product_name VARCHAR(100)`, `description TEXT`, `price DECIMAL(10,2)`, `quantity INT`, and `created_at TIMESTAMP`. Prices and quantities cannot be negative. Schema setup uses versioned LavaLust migrations, with InnoDB selected explicitly for compatibility with WAMP's MyISAM default.

```powershell
php lava migration run
php lava migration status
php lava migration create-migration your_migration_name
php lava migration rollback
php lava migration rollback-all
php lava migration refresh
```

**Rollback and refresh remove table data.** Use them only when you intend to reset your development schema. This framework's `rollback` removes the latest migration, rather than an entire batch.

The custom CLI command is in `app/commands/Migration.php`. The handout uses `app/command`, but this installed LavaLust version discovers commands in `app/commands`.

The migration controller and all six handout routes are implemented. Browser migration routes are disabled by default. To use them locally, set `MIGRATION_ENABLED=true` in `.env`: `/create-migration/{name}`, `/migrate`, `/rollback`, `/rollback-all`, `/refresh`, `/status`, under `http://localhost/lab6/public`. These routes remain disabled when `APP_ENV=production`; CLI migrations still work.

## API

Local base: `http://localhost/lab6/public/api`.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/health` | Database health check |
| POST | `/auth/register` | Create account (`username`, `email`, `password`) |
| POST | `/auth/login` | Sign in (`identity`, `password`) |
| GET | `/auth/me` | Current authenticated user |
| POST | `/auth/refresh` | Rotate token pair (`refresh_token`) |
| POST | `/auth/logout` | Revoke refresh token (`refresh_token`) |
| GET | `/products` | List products |
| GET | `/products/{id}` | Retrieve one product |
| POST | `/products` | Create product |
| PUT | `/products/{id}` | Replace product fields |
| PATCH | `/products/{id}` | Update selected fields |
| DELETE | `/products/{id}` | Delete product |

Create/PUT body example:

```json
{
  "product_name": "Canvas Tote Bag",
  "description": "Natural cotton canvas",
  "price": "349.00",
  "quantity": 48
}
```

Success and error responses use LavaLust's `Api::respond` / `Api::respond_error`. Successful refresh responses wrap the token pair in `tokens`, which the frontend handles. Validation returns HTTP 422 with field errors, missing products return 404, unauthenticated product access returns 401, and non-admin writes return 403. Roles are read from MySQL on each product request, so changing a stored role takes effect immediately. Public registration always creates a user account and ignores supplied roles.

## Verification

```powershell
node scripts/test-api.mjs
php scripts/test-product-roles.php
npm.cmd --prefix frontend run lint
npm.cmd --prefix frontend run build
php lava migration status
```

The API integration script uses the local demo login (override with `TEST_API_URL`, `TEST_USERNAME`, and `TEST_PASSWORD`). It creates and removes its own test product. It checks unauthenticated CRUD rejection, invalid login, registration validation/duplicates, login, profile, product creation, exact text preservation, list, PUT, PATCH, invalid inputs, deletion, missing products, refresh rotation, logout, and CORS.

The role integration test creates temporary admin/user accounts and removes them afterward. It checks admin CRUD, user list/detail access, HTTP 403 for every non-admin mutation, registration role escalation rejection, forged tokens, and protected product fields. Browser role checks also passed: user mutation controls remain hidden after reload and admin controls are available.

A browser verification also passed login, add/edit/delete, cancel deletion, search, stock filtering, session restoration, mobile sizing, logout, and JavaScript error checks. Captured screenshots:

- [Login](docs/screenshots/01-login.png)
- [Product list](docs/screenshots/02-products.png)
- [Add product](docs/screenshots/03-add-product.png)
- [Edit product](docs/screenshots/04-edit-product.png)
- [Delete confirmation](docs/screenshots/05-delete-product.png)
- [Mobile view](docs/screenshots/06-mobile.png)
- [User view-only interface](docs/screenshots/07-user-view.png)

## Aiven and Render later

Cloud deployment has **not** been performed. The current app uses WAMP MySQL, following the updated request.

The backend reads database credentials from environment variables and supports a verified MySQL TLS connection through `DB_SSL_CA` and `DB_SSL_REQUIRED=true`. For Aiven, use your own host, port, username, password, database, and downloaded CA certificate. On Render, a secret file can hold the CA certificate. Set `APP_ENV=production`, strong distinct `JWT_SECRET` and `REFRESH_TOKEN_KEY`, and `FRONTEND_ORIGIN` to your deployed frontend's origin. Do not upload the local `.env` or use the local demo account on a public service.

For a separately hosted React frontend, set `VITE_API_URL=https://your-api.onrender.com/api` **before** building. The frontend only uses this public API URL; database secrets belong in the backend environment. Render can host PHP through Docker and the React build as a static site (`frontend/dist`). Repository URLs, cloud URLs, and the Aiven database screenshot still need to be produced when cloud deployment is requested.

References: [LavaLust API](https://lavalust.netlify.app/docs/libraries/api.html), [Aiven PHP/MySQL connection](https://aiven.io/docs/products/mysql/howto/connect-with-php), [Render Docker](https://render.com/docs/docker), [Render environment variables and secret files](https://render.com/docs/configure-environment-variables).
