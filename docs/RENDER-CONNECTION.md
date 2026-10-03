# Render frontend/backend connection

Backend: https://backend-znst.onrender.com
Frontend: https://frontend-hpo6.onrender.com

The deployed frontend originally used the WAMP-only `/lab6/public/api` URL,
which sends requests to the frontend server instead of the backend. The live
backend also omitted `Access-Control-Allow-Origin` for the deployed frontend.
Its `/api/health` endpoint returned 200, so the observed problem was the browser
connection, rather than a stopped backend or an unreachable database.

## Correct settings

In the **frontend** Render service's Environment settings:

```text
VITE_API_URL=https://backend-znst.onrender.com/api
```

Save and rebuild/redeploy the frontend. Vite inserts this value during the build;
restarting an old build does not change its API URL. The updated frontend code
also defaults to this backend when served on a remote host, while keeping the
WAMP URL for localhost builds and the Vite proxy for development.

In the **backend** Render service's Environment settings:

```text
FRONTEND_ORIGIN=https://frontend-hpo6.onrender.com
APP_ENV=production
```

The backend code explicitly trusts this application's deployed frontend, and
also supports additional comma-separated origins through FRONTEND_ORIGIN.
Secrets and Aiven database values belong in the backend's Render environment.
The Docker build excludes local `.env` files and certificates.

Deploy the updated backend and frontend repositories. Changing files locally
does not change either live service until the commits have been pushed and
Render has deployed them.

## Verify

```powershell
curl.exe -i https://backend-znst.onrender.com/api/health
curl.exe -i -X OPTIONS -H "Origin: https://frontend-hpo6.onrender.com" -H "Access-Control-Request-Method: POST" -H "Access-Control-Request-Headers: content-type" https://backend-znst.onrender.com/api/auth/login
```

Expected: health returns 200 with `{"status":"ok"}`; preflight returns 204 with
`Access-Control-Allow-Origin: https://frontend-hpo6.onrender.com`.
An unauthenticated GET to `/api/products` should return 401.
In browser Network tools, login requests must go to
`https://backend-znst.onrender.com/api/auth/login`, and must not go to
`https://frontend-hpo6.onrender.com/lab6/public/api/auth/login`.

References: [Vite environment variables](https://vite.dev/guide/env-and-mode),
[Render environment variables](https://render.com/docs/configure-environment-variables).
