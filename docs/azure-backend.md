# Subir el backend a Azure (cuenta gratuita)

Usaremos una **máquina virtual Linux B1s**. La cuenta gratuita incluye 750 horas al mes de esta VM durante 12 meses, que alcanzan para tenerla encendida todo el mes.

Resultado final: la API queda en
`https://asistencia-api.<region>.cloudapp.azure.com/api/health`

> Antes de empezar: la cuenta gratuita trae un crédito que vence a los 30 días. Para seguir usando los servicios gratuitos de 12 meses, Azure pide pasar la suscripción a "pago por uso" al terminar ese periodo. Mientras uses solo lo indicado aquí el costo debería ser $0, pero en el paso 1 crearemos una alerta de presupuesto por seguridad.

---

## 1. Alerta de presupuesto (5 min, recomendado)

1. En el portal (https://portal.azure.com) busca **Cost Management → Budgets** (Presupuestos).
2. **+ Add** → monto: `5` USD mensuales.
3. Alertas al 50 % y 100 % hacia tu correo.

---

## 2. Crear la máquina virtual (10 min)

1. Busca **Virtual machines** → **+ Create** → **Azure virtual machine**.
2. Pestaña **Basics**:
   | Campo | Valor |
   |-------|-------|
   | Subscription | Tu suscripción gratuita |
   | Resource group | **Create new** → `rg-asistencia` |
   | Virtual machine name | `vm-asistencia-api` |
   | Region | La más cercana con B1s disponible (p. ej. `East US 2`, `Mexico Central`, `Brazil South`) |
   | Availability options | No infrastructure redundancy required |
   | Security type | Standard |
   | Image | **Ubuntu Server 24.04 LTS - x64 Gen2** |
   | Size | **Standard_B1s** (debe decir *Free services eligible*; si no aparece, "See all sizes" y búscalo) |
   | Authentication type | **SSH public key** |
   | Username | `azureuser` |
   | SSH public key source | Generate new key pair |
   | Key pair name | `vm-asistencia-api_key` |
   | Public inbound ports | Allow selected ports → **SSH (22), HTTP (80), HTTPS (443)** |
3. Pestaña **Disks**: OS disk type → **Standard SSD** (o Premium SSD de 64 GB o menos; ambos entran en el plan gratuito).
4. Pestaña **Management**: verifica que **Auto-shutdown** esté **desactivado**.
5. **Review + create** → **Create**.
6. Aparece una ventana: **Download private key and create resource**. Guarda el archivo `vm-asistencia-api_key.pem` (solo se puede descargar una vez), por ejemplo en `C:\Users\<tu-usuario>\.ssh\`.

---

## 3. Darle un nombre DNS a la IP (2 min)

Sirve para tener HTTPS sin comprar un dominio.

1. Abre la VM → **Overview** → junto a **DNS name** haz clic en **Not configured** (o entra a la *Public IP address*).
2. **Assignment**: `Static`.
3. **DNS name label**: `asistencia-api` (si está ocupado, agrega algo único, p. ej. `asistencia-api-soe`).
4. **Save**. Anota el nombre completo, p. ej. `asistencia-api.eastus2.cloudapp.azure.com`.

---

## 4. Conectarse por SSH desde Windows (PowerShell)

```powershell
cd $HOME\.ssh
icacls vm-asistencia-api_key.pem /inheritance:r /grant:r "$($env:USERNAME):R"
ssh -i vm-asistencia-api_key.pem azureuser@asistencia-api.eastus2.cloudapp.azure.com
```
El comando `icacls` es necesario porque SSH rechaza la llave si otros usuarios pueden leerla. La primera vez escribe `yes` para aceptar la huella.

---

## 5. Instalar Node.js 22 y herramientas (en la VM)

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs build-essential python3 nginx certbot python3-certbot-nginx
node -v
```
Debe mostrar `v22.x`.

---

## 6. Subir el código del backend (desde tu PC)

En **otra** ventana de PowerShell, en la carpeta del proyecto, empaqueta el backend **sin** `node_modules`, la base de datos ni `.env`:
```powershell
cd C:\Users\andre\Desktop\SOE\Repositorios\proyecto-asistencia
tar -czf backend.tar.gz --exclude=node_modules --exclude=*.db --exclude=.env -C backend .
scp -i $HOME\.ssh\vm-asistencia-api_key.pem backend.tar.gz azureuser@asistencia-api.eastus2.cloudapp.azure.com:~
```

De vuelta en la VM:
```bash
sudo mkdir -p /opt/asistencia-backend /var/lib/asistencia
sudo chown azureuser:azureuser /opt/asistencia-backend /var/lib/asistencia
tar -xzf ~/backend.tar.gz -C /opt/asistencia-backend
cd /opt/asistencia-backend
npm ci --omit=dev
```

---

## 7. Configurar el `.env`

```bash
cp .env.example .env
nano .env
```
Contenido:
```ini
PORT=3000
HOST=127.0.0.1
DB_PATH=/var/lib/asistencia/asistencia.db
# Temporal hasta tener la URL del frontend en AWS; luego reemplazar por esa URL
CORS_ORIGINS=*
```
Guarda con `Ctrl+O`, `Enter`, y sal con `Ctrl+X`.

Prueba rápida:
```bash
npm start
```
Debe decir `Servidor backend corriendo en http://127.0.0.1:3000`. Detén con `Ctrl+C`.

---

## 8. Dejarlo encendido con PM2

```bash
sudo npm install -g pm2
pm2 start npm --name asistencia-backend -- start
pm2 save
pm2 startup
```
`pm2 startup` imprime un comando que empieza con `sudo env PATH=...`: **cópialo y ejecútalo**. Así la API arranca sola si la VM se reinicia.

```bash
curl http://127.0.0.1:3000/api/health
```

---

## 9. Publicar con Nginx + HTTPS

```bash
sudo nano /etc/nginx/sites-available/asistencia-api
```
Pega (cambia el `server_name` por tu nombre DNS):
```nginx
server {
    listen 80;
    server_name asistencia-api.eastus2.cloudapp.azure.com;
    client_max_body_size 10M;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```
Activar y obtener el certificado gratuito:
```bash
sudo ln -s /etc/nginx/sites-available/asistencia-api /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d asistencia-api.eastus2.cloudapp.azure.com
```
Certbot pide un correo y aceptar términos; el certificado se renueva solo.

---

## 10. Verificar desde tu navegador

Abre: `https://asistencia-api.eastus2.cloudapp.azure.com/api/health`
Debe responder `{"estado":"ok", ...}`.

Esa URL (sin `/api/health`) es la que va en `API_URL` de `frontend/config.js`.

---

## 11. Cuando el frontend ya esté en AWS

En la VM:
```bash
nano /opt/asistencia-backend/.env
# CORS_ORIGINS=https://url-del-frontend-en-aws
pm2 restart asistencia-backend
```

---

## Mantenimiento

| Tarea | Comando (en la VM) |
|-------|--------------------|
| Ver logs | `pm2 logs asistencia-backend` |
| Reiniciar | `pm2 restart asistencia-backend` |
| Actualizar código | Repetir el paso 6 (subir y descomprimir), luego `npm ci --omit=dev` y `pm2 restart asistencia-backend` |
| Respaldar la BD | `cp /var/lib/asistencia/asistencia.db ~/respaldo-$(date +%F).db` y descargar con `scp` |

Para no gastar horas gratuitas mientras no la uses, puedes **Stop** la VM desde el portal (estado *Stopped (deallocated)*); la IP estática y los datos se conservan.

## Problemas comunes

- **`ssh: Permission denied (publickey)` o `UNPROTECTED PRIVATE KEY FILE`**: repite el comando `icacls` del paso 4.
- **El navegador no abre la URL**: revisa en la VM → **Networking** que existan reglas de entrada para 80 y 443.
- **Certbot falla**: el nombre DNS del paso 3 debe resolver a la IP de la VM y el puerto 80 debe estar abierto.
- **`502 Bad Gateway`**: la API no está corriendo; revisa `pm2 logs asistencia-backend`.
- **Error de CORS en el navegador**: `CORS_ORIGINS` no coincide exactamente con la URL del frontend (incluye `https://`, sin `/` final).
