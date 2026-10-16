# Guía de Despliegue en Azure — Backend + Frontend Local

Guía paso a paso probada para desplegar el backend de asistencia en una VM de Azure
y conectar el frontend desde tu máquina local para pruebas.

> **Requisitos previos:**
> - Cuenta de Azure (la cuenta gratuita incluye 750 h/mes de VM B1s por 12 meses)
> - Git Bash o terminal con soporte SSH en tu PC

---

## 1. Crear la Máquina Virtual en Azure

1. En el portal (https://portal.azure.com) busca **Virtual machines** → **+ Create**.
2. Configuración recomendada:

   | Campo | Valor |
   |-------|-------|
   | Resource group | `rg-asistencia` (crear nuevo) |
   | VM name | `server-backend` |
   | Region | La más cercana disponible |
   | Image | **Ubuntu Server 24.04 LTS** |
   | Size | **Standard_B1s** (Free services eligible) |
   | Authentication | **SSH public key** |
   | Username | `andresreds` (o el que prefieras) |

3. **Review + create** → **Create**.
4. **Descargar la clave privada** (`.pem`) y guardarla en `C:\Users\<tu-usuario>\.ssh\`.

---

## 2. Conectarse por SSH

Desde **PowerShell** o **Git Bash**:

```bash
ssh -i ~/.ssh/tu-clave.pem andresreds@IP-PUBLICA
```

> La IP pública la encuentras en el portal de Azure → tu VM → **Overview** → "Public IP address".

Si SSH rechaza la clave por permisos (solo PowerShell):
```powershell
icacls tu-clave.pem /inheritance:r /grant:r "$($env:USERNAME):R"
```

---

## 3. Instalar Node.js 22 en la VM

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v   # Debe mostrar v22.x.x
npm -v    # Debe mostrar 10.x.x
```

---

## 4. Abrir puertos en el Network Security Group (NSG)

Desde el **portal de Azure** (no por SSH):

1. Ve a tu VM → **Networking** → **Network settings**.
2. Clic en **+ Crear ACL del puerto** (o "Add inbound port rule").
3. Crear las siguientes reglas:

   | Prioridad | Nombre | Puerto | Protocolo | Acción |
   |-----------|--------|--------|-----------|--------|
   | 300 | HTTP | 80 | TCP | Allow |
   | 320 | SSH | 22 | TCP | Allow |
   | 330 | Port_3000 | 3000 | TCP | Allow |

> **Nota:** Los puertos 22 y 80 normalmente se crean al configurar la VM.
> El puerto **3000** hay que agregarlo manualmente para pruebas directas del backend.

---

## 5. Crear la carpeta destino en la VM

Desde la terminal SSH:

```bash
sudo mkdir -p /opt/asistencia-backend
sudo chown andresreds:andresreds /opt/asistencia-backend
```

---

## 6. Subir el código del backend

### ⚠️ Importante: No subir `node_modules` ni `asistencia.db`

Desde **otra terminal** en tu PC (Git Bash), primero elimina `node_modules` si existe:

```bash
rm -rf backend/node_modules
```

Luego sube el código:

```bash
cd C:/Users/andre/Desktop/SOE/Repositorios/proyecto-asistencia
scp -r backend/* andresreds@IP-PUBLICA:/opt/asistencia-backend/
```

> **Nota para Git Bash:** Usa `/` en las rutas, no `\`. La sintaxis `.\backend\*`
> de Windows **no funciona** en Git Bash.

---

## 7. Instalar dependencias en la VM

De vuelta en la terminal SSH:

```bash
cd /opt/asistencia-backend
npm ci --omit=dev
```

---

## 8. Crear el archivo `.env`

> **Nota:** Los archivos que empiezan con `.` (punto) pueden no subirse con `scp`.
> Si el `.env.example` no se subió, créalo manualmente.

```bash
nano /opt/asistencia-backend/.env
```

Pega este contenido:

```ini
PORT=3000
HOST=0.0.0.0
DB_PATH=./asistencia.db
CORS_ORIGINS=*
```

Guardar: **`Ctrl + O`** → Enter → **`Ctrl + X`**

> `CORS_ORIGINS=*` es solo para pruebas. Cuando despliegues el frontend en producción,
> cámbialo por la URL real, ejemplo: `CORS_ORIGINS=https://tu-frontend.azurestaticapps.net`

---

## 9. Ejecutar el backend con PM2

PM2 mantiene el proceso vivo y lo reinicia si la VM se reinicia.

```bash
sudo npm install -g pm2
pm2 start server.js --name "asistencia-backend"
pm2 save
pm2 startup
```

> `pm2 startup` imprime un comando que empieza con `sudo env PATH=...`:
> **cópialo y ejecútalo** para que el backend arranque automáticamente.

---

## 10. Verificar que el backend funciona

### Desde la VM:
```bash
pm2 status
curl http://localhost:3000/api/health
```

### Desde tu navegador:
```
http://IP-PUBLICA:3000/api/health
```

Debe responder:
```json
{"estado":"ok","fecha":"2026-10-16T..."}
```

> **"Cannot GET /"** en la raíz `/` es **normal** — la API no tiene ruta en `/`.
> Usa `/api/health` para verificar.

---

## 11. Probar el frontend localmente

### Paso 1: Configurar la URL del backend

Editar `frontend/config.js` y cambiar `localhost` por la IP de Azure:

```js
window.APP_CONFIG = {
    API_URL: 'http://IP-PUBLICA:3000'
};
```

### Paso 2: Levantar un servidor local para el frontend

> ⚠️ **NO abrir los archivos HTML con doble clic** (`file:///...`).
> El `localStorage` no funciona correctamente entre páginas con el protocolo `file://`.

Desde **Git Bash**:

```bash
cd C:/Users/andre/Desktop/SOE/Repositorios/proyecto-asistencia/frontend
npx http-server -p 8080
```

Abrir en el navegador: **`http://localhost:8080`**

---

## Rutas disponibles de la API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/health` | Health check |
| POST | `/api/login` | Login de estudiante |
| POST | `/api/marcar` | Registrar asistencia |
| GET | `/api/historial/:registro` | Historial de un estudiante |
| GET | `/api/modulo-activo` | Ver módulo activo |
| POST | `/api/importar-estudiantes` | Importar Excel (alumnos + fechas) |
| GET | `/api/admin/asistencias` | Matriz cruzada de asistencias |
| POST | `/api/admin/cambiar-asistencia` | Modificar/forzar asistencia |
| POST | `/api/admin/reiniciar-modulo` | Reiniciar módulo académico |
| GET | `/api/admin/auditoria` | Log de auditoría |
| GET | `/api/admin/estudiantes` | Listar estudiantes del módulo activo |

---

## Mantenimiento

| Tarea | Comando (en la VM por SSH) |
|-------|---------------------------|
| Ver logs | `pm2 logs asistencia-backend` |
| Reiniciar | `pm2 restart asistencia-backend` |
| Ver estado | `pm2 status` |
| Actualizar código | Repetir pasos 6-7, luego `pm2 restart asistencia-backend` |
| Respaldar la BD | `cp /opt/asistencia-backend/asistencia.db ~/respaldo-$(date +%F).db` |

---

## Problemas comunes

| Problema | Solución |
|----------|----------|
| `Permission denied (publickey)` al conectar SSH | Revisar permisos de la clave: `icacls clave.pem /inheritance:r /grant:r "$($env:USERNAME):R"` |
| `scp: stat local ".backend*": No such file or directory` | Usar `/` en vez de `\` en Git Bash |
| `Cannot GET /` en el navegador | Es normal — usar `/api/health` para verificar |
| `estudiante is null` en dashboard | No abrir HTML con `file://`, usar `http-server` |
| El frontend no conecta al backend | Verificar que `config.js` tiene la IP correcta de Azure |
| La página no carga en el puerto 3000 | Verificar que el puerto 3000 está abierto en el NSG de Azure |
| `CORS` bloqueado en consola | Revisar `CORS_ORIGINS` en el `.env` de la VM |
| `.env.example` no se subió | Los archivos con `.` al inicio pueden ocultarse; crearlos manualmente con `nano` |
