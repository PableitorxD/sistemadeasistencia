# Sistema de Asistencia

El proyecto está dividido en dos servicios independientes que se suben manualmente a servidores distintos:

```
proyecto-asistencia/
├── backend/    API REST (Node.js + Express + SQLite)   → puerto 3000
└── frontend/   Sitio estático (HTML + Bootstrap)
```

El navegador del usuario descarga el frontend y desde ahí llama directamente al backend.
Para que se comuniquen solo hay **dos configuraciones**:

| Servicio | Dónde | Valor |
|----------|-------|-------|
| frontend | `API_URL` en [frontend/config.js](frontend/config.js) | URL pública del backend, p. ej. `https://api-asistencia.midominio.com` |
| backend  | `CORS_ORIGINS` en `backend/.env` | URL pública del frontend, p. ej. `https://asistencia.midominio.com` (varias separadas por coma) |

> **Importante:** si el frontend se sirve por **HTTPS**, el backend también debe tener **HTTPS**; si no, el navegador bloquea las peticiones (mixed content). Las URLs van sin `/` final.

---

## Ejecución local

```bash
cd backend
npm install
cp .env.example .env
npm start
```
En otra terminal, sirve el frontend en el puerto 8080 (cualquier servidor estático sirve):
```bash
npx serve frontend -l 8080
```
Abre `http://localhost:8080`. Por defecto `config.js` apunta a `http://localhost:3000`.

---

## Backend: despliegue manual

Funciona igual en una **EC2 (AWS)** o en una **VM (Azure)** con Linux (Ubuntu / Amazon Linux).
Guía paso a paso para Azure con cuenta gratuita: [docs/azure-backend.md](docs/azure-backend.md).

### 1. Preparar el servidor
- Instalar **Node.js 22** o superior (`node -v`).
- Abrir los puertos en el firewall de la nube:
  - AWS → **Security Group** de la instancia.
  - Azure → **Network Security Group (NSG)** de la VM.
  - Abrir 80 y 443 (o 3000 si se expone directo, solo para pruebas). El 22 (SSH) solo para tu IP.
- Asignar una IP fija: **Elastic IP** (AWS) o **IP pública estática** (Azure), y opcionalmente un dominio.

### 2. Subir el código
Copiar la carpeta `backend/` **sin** `node_modules` ni `asistencia.db` (p. ej. con `scp`, WinSCP o git):
```bash
scp -r backend usuario@IP-DEL-SERVIDOR:/opt/asistencia-backend
```

### 3. Instalar y configurar
```bash
cd /opt/asistencia-backend
npm ci --omit=dev
cp .env.example .env
nano .env
```
Variables de `.env`:

| Variable | Por defecto | Descripción |
|----------|-------------|-------------|
| `PORT` | `3000` | Puerto de la API |
| `HOST` | `0.0.0.0` | Interfaz de escucha |
| `DB_PATH` | `./asistencia.db` | Archivo SQLite; se crea solo la primera vez |
| `CORS_ORIGINS` | `*` | URL del frontend. En producción **no** dejar `*` |

### 4. Mantenerlo encendido (PM2)
```bash
sudo npm install -g pm2
pm2 start npm --name asistencia-backend -- start
pm2 save
pm2 startup
```
`pm2 startup` muestra un comando que hay que copiar y ejecutar para que la API arranque sola tras un reinicio.
Ver logs: `pm2 logs asistencia-backend` · Reiniciar tras cambios: `pm2 restart asistencia-backend`.

### 5. HTTPS con Nginx + Certbot (recomendado)
```bash
sudo apt install nginx certbot python3-certbot-nginx
```
`/etc/nginx/sites-available/asistencia-api`:
```nginx
server {
    listen 80;
    server_name api-asistencia.midominio.com;
    client_max_body_size 10M;   # subida de archivos Excel

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/asistencia-api /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d api-asistencia.midominio.com
```
Con Nginx delante, en `.env` se puede usar `HOST=127.0.0.1` para que el puerto 3000 no quede expuesto.

### 6. Comprobar
```bash
curl https://api-asistencia.midominio.com/api/health
# {"estado":"ok", ...}
```

> SQLite guarda todo en un archivo (`DB_PATH`): usa **un solo servidor** para el backend y haz copias de ese archivo periódicamente.

---

## Frontend: despliegue manual

### 1. Configurar la URL del backend
Editar [frontend/config.js](frontend/config.js):
```js
window.APP_CONFIG = {
    API_URL: 'https://api-asistencia.midominio.com'
};
```

### 2. Subir los archivos
Subir el contenido de `frontend/` (`*.html`, `bootstrap.css`, `config.js`, `app-config.js`) a cualquiera de estas opciones:

- **Azure Static Web Apps** o **Storage Account → Sitio web estático**: subir los archivos desde el portal o con Azure Storage Explorer. `staticwebapp.config.json` ya evita que `config.js` quede en caché.
- **AWS S3 (sitio web estático) + CloudFront** para HTTPS.
- **VM con Nginx** (AWS o Azure): copiar a `/var/www/asistencia` y usar:
  ```nginx
  server {
      listen 80;
      server_name asistencia.midominio.com;
      root /var/www/asistencia;
      index index.html;

      location = /config.js { add_header Cache-Control "no-store"; }
      location / { try_files $uri $uri/ =404; }
  }
  ```
  Luego `sudo certbot --nginx -d asistencia.midominio.com` para HTTPS.
- **Windows Server con IIS**: copiar los archivos a la carpeta del sitio.

---

## Checklist de comunicación entre nubes

- [ ] El backend responde públicamente: `/api/health` devuelve `{"estado":"ok"}`.
- [ ] `API_URL` en `config.js` = URL pública del backend (sin `/` final).
- [ ] `CORS_ORIGINS` en `.env` = URL exacta del frontend (protocolo + dominio + puerto si aplica). Reiniciar con `pm2 restart` tras cambiarlo.
- [ ] Ambos con HTTPS (o ambos HTTP solo en pruebas).
- [ ] Puertos abiertos en Security Group (AWS) / NSG (Azure).
- [ ] Si un origen es rechazado, aparece en `pm2 logs` como `Origen bloqueado por CORS`.
