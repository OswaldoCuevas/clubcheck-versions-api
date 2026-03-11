# ClubCheck - Gestor de Versiones

Sistema web para gestionar versiones de aplicaciones ejecutables (.exe) con interfaz moderna y API REST.

## 🚀 Características

- **Interfaz moderna**: Diseño responsivo con Bootstrap 5
- **Subida de archivos**: Drag & drop para archivos .exe
- **Validación automática**: Verificación de formato de versión
- **Hash SHA256**: Generación automática para verificación de integridad
- **API REST**: Endpoint para consultar versiones programáticamente
- **Gestión de releases**: Notas de versión y marcado de actualizaciones obligatorias

## 📁 Estructura del Proyecto

```
clubcheck/
├── index.php          # Formulario principal de gestión
├── api.php            # API REST para consultar versiones
├── .htaccess          # Configuración de seguridad y rendimiento
├── version.json       # Archivo JSON con información de versión actual
├── uploads/           # Directorio para archivos .exe subidos
└── README.md          # Este archivo
```

## 🔧 Instalación

1. **Clonar/Copiar** los archivos a tu servidor web
2. **Configurar permisos** de escritura para los directorios:
   ```bash
   chmod 755 uploads/
   chmod 644 version.json
   ```
3. **Verificar configuración PHP** (extensiones requeridas):
   - `json`
   - `hash`
   - `fileinfo`

## ⚙️ Configuración de variables de entorno

La aplicación carga automáticamente un archivo `.env` ubicado en la raíz del proyecto. Para configurarlo:

1. Duplica el archivo `.env.example` y renómbralo a `.env`.
2. Edita los valores según tu entorno (secreto para `ACCESS_KEY_SECRET` y credenciales de base de datos `DB_*`).
3. Asegúrate de que el archivo no se incluya en el control de versiones.

> El secreto `ACCESS_KEY_SECRET` se usa para generar y hashear las AccessKey entregadas a los clientes; genera un valor largo y aleatorio. Si las variables ya existen en el entorno del servidor, prevalecerán sobre las definidas en `.env`.

## 📖 Uso

### Interfaz Web
1. Accede a `http://tu-servidor/clubcheck/`
2. Completa el formulario:
   - **Versión**: Formato X.X.X.X (ej: 1.2.3.0)
   - **Archivo .exe**: Selecciona o arrastra el archivo
   - **Obligatoria**: Marca si la actualización es mandatoria
   - **Notas**: Describe los cambios de la versión
3. Haz clic en "Subir Nueva Versión"

### API REST

#### 1. Consultar información completa de versión
```http
GET /clubcheck/api.php
```

**Respuesta de ejemplo:**
```json
{
    "latestVersion": "1.2.3.0",
    "url": "http://tu-servidor/clubcheck/uploads/MyApp-1.2.3.0.exe",
    "sha256": "a1b2c3d4e5f6...",
    "mandatory": false,
    "releaseNotes": "Correcciones y mejoras de rendimiento.",
    "lastUpdated": 1695123456,
    "timestamp": "2023-09-19 14:30:56",
    "hasUpdate": true,
    "downloadUrl": "http://tu-servidor/clubcheck/download.php",
    "directUrl": "http://tu-servidor/clubcheck/uploads/MyApp-1.2.3.0.exe",
    "fileExists": true,
    "fileSize": 15728640,
    "fileSizeFormatted": "15.00 MB",
    "integrityCheck": true
}
```

#### 2. Verificar si hay actualizaciones disponibles
```http
GET /clubcheck/check-update.php?version=1.2.2.0
```

O usando POST:
```http
POST /clubcheck/check-update.php
Content-Type: application/json

{
    "currentVersion": "1.2.2.0"
}
```

**Respuesta de ejemplo:**
```json
{
    "hasUpdate": true,
    "serverVersion": "1.2.3.0",
    "clientVersion": "1.2.2.0",
    "mandatory": false,
    "releaseNotes": "Correcciones y mejoras de rendimiento.",
    "lastUpdated": 1695123456,
    "timestamp": "2023-09-19 14:30:56",
    "downloadUrl": "http://tu-servidor/clubcheck/download.php",
    "sha256": "a1b2c3d4e5f6...",
    "fileAvailable": true,
    "fileSize": 15728640,
    "versionComparison": 1
}
```

#### 3. Descargar la última versión
```http
GET /clubcheck/download.php
```

Este endpoint:
- ✅ Descarga directamente el archivo .exe
- ✅ Verifica la integridad del archivo
- ✅ Soporta descargas parciales (resume)
- ✅ Maneja archivos grandes eficientemente
- ✅ Incluye headers apropiados para descarga

### Registro de clientes y tokens (aplicación de escritorio)

El controlador `CustomersController` expone una API enfocada en registrar clientes, administrar sus tokens y coordinar el flujo de "esperar nuevo token" con la aplicación de escritorio.

> **Importante:** Envía siempre encabezados `Content-Type: application/json` y un `User-Agent` válido para evitar bloqueos de Cloudflare.

#### 1. Consultar un cliente por ID
```http
GET /clubcheck/api/customers/CLUB-001
```

Reemplaza `CLUB-001` por el ID real del cliente. También se admite la variante `GET /clubcheck/api/customers?customerId=CLUB-001` para compatibilidad con clientes anteriores.

Si el cliente no existe se devuelve `404 Not Found`.

**Respuesta:**
```json
{
    "customer": {
        "customerId": "CLUB-001",
        "name": "Club House",
        "email": "admin@clubhouse.mx",
        "phone": "+52 55 1234 5678",
        "deviceName": "POS-01",
        "token": "abc123",
        "isActive": true,
        "waitingForToken": false,
        "waitingSince": null,
        "tokenUpdatedAt": 1699900000,
        "lastSeen": 1699900500
    }
}
```

> **Nota:** Por motivos de seguridad no existe un endpoint público que devuelva todos los clientes. Usa el panel administrativo si necesitas una lista completa.

#### 2. Registrar un nuevo cliente (solo si no existe)
```http
POST /clubcheck/api/customers/register
Content-Type: application/json

{
    "customerId": "CLUB-001",
    "name": "Club House",
    "token": "abc123",
    "email": "admin@clubhouse.mx",
    "phone": "+52 55 1234 5678",
    "deviceName": "POS-01"
}
```

- Campos obligatorios: `customerId`, `name` y `token`.
- Los campos `email`, `phone` y `deviceName` son opcionales; si se omiten se almacenan como `null`.
- Si el `customerId` ya está registrado, la respuesta devolverá `found: true` y **no** modificará los datos existentes.
- Cuando el registro es nuevo, responde con `201 Created`, `found: false` y los datos almacenados.

**Respuesta cuando ya existe:**
```json
{
    "found": true,
    "registered": false,
    "customer": {
        "customerId": "CLUB-001",
        "name": "Club House",
        "email": "admin@clubhouse.mx",
        "phone": "+52 55 1234 5678",
        "deviceName": "POS-01",
        "token": "abc123",
        "isActive": true,
        "waitingForToken": false
    }
}
```

#### 3. Crear o actualizar un cliente
```http
POST /clubcheck/api/customers/save
Content-Type: application/json

{
    "customerId": "CLUB-001",
    "name": "Club House",
    "token": "abc123",
    "deviceName": "POS-01",
    "isActive": true
}
```

El campo `customerId` es obligatorio. Puedes omitir `token` y `name` si solo deseas activar/desactivar un cliente (`isActive`). Los atributos no incluidos permanecen sin cambios.

#### 4. Marcar que un cliente espera un nuevo token
```http
POST /clubcheck/api/customers/token/await
Content-Type: application/json

{
    "customerId": "CLUB-001",
    "waiting": true
}
```

Cuando `waiting` es `true`, el registro guarda la marca `waitingForToken` junto con `waitingSince`. Usa `waiting: false` para cancelar la solicitud.

**Respuesta:**
```json
{
    "customerId": "CLUB-001",
    "waitingForToken": true,
    "waitingSince": 1699900600
}
```

#### 5. Consultar el token actual de un cliente
```http
GET /clubcheck/api/customers/token?customerId=CLUB-001
```

**Respuesta:**
```json
{
    "customerId": "CLUB-001",
    "name": "Club House",
    "token": "abc123",
    "deviceName": "POS-01",
    "isActive": true,
    "waitingForToken": true,
    "waitingSince": 1699900600,
    "tokenUpdatedAt": 1699900000
}
```

#### 6. Registrar un nuevo token (cliente de escritorio)
```http
POST /clubcheck/api/customers/token/register
Content-Type: application/json

{
    "customerId": "CLUB-001",
    "token": "new-token-from-app"
}
```

El backend solo acepta la actualización si `waitingForToken` está activo. Si el administrador no marcó al cliente como "en espera", se devolverá `409 Conflict`:

```json
{
    "error": "Customer is not waiting for a new token",
    "waitingForToken": false
}
```

Tras registrar el token exitosamente, el servicio limpia la marca `waitingForToken` y actualiza `tokenUpdatedAt`.

#### Seguimiento de sesiones (opcional)

Los endpoints bajo `/api/customers/sessions/*` siguen disponibles para que la app de escritorio envíe heartbeats (`start`, `heartbeat`, `end`, `active`). Aunque ya no se muestran en el panel principal, continúan actualizando los campos `lastSeen` y `metadata` del registro de clientes.

### API de WhatsApp

Los endpoints de WhatsApp permiten enviar mensajes de templates desde el backend. Todos los mensajes se registran automáticamente en la tabla `SentMessagesDesktop`.

> **Configuración requerida:** Define las siguientes variables de entorno:
> - `WHATSAPP_API_URL` - URL de la API de WhatsApp (default: `https://graph.facebook.com/v18.0`)
> - `WHATSAPP_PHONE_NUMBER_ID` - ID del número de teléfono de WhatsApp Business
> - `WHATSAPP_ACCESS_TOKEN` - Token de acceso de la API

#### 1. Estado del servicio
```http
GET /clubcheck/api/customers/whatsapp/status
```

**Respuesta:**
```json
{
    "configured": true,
    "timestamp": 1699900000
}
```

#### 2. Conteo mensual de mensajes
```http
GET /clubcheck/api/customers/whatsapp/monthly-count/CUSTOMER-ID
```

**Respuesta:**
```json
{
    "customerApiId": "CUSTOMER-ID",
    "count": 150,
    "month": "2026-03"
}
```

#### 3. Listar mensajes enviados con filtros

Este endpoint permite obtener el historial de mensajes con filtros avanzados, paginación y búsqueda.

```http
GET /clubcheck/api/customers/whatsapp/messages/CUSTOMER-ID?page=1&perPage=20&status=failed&search=5512345678&startDate=2026-03-01&endDate=2026-03-10
```

**Query Parameters:**

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `page` | number | No | Número de página (default: 1) |
| `perPage` | number | No | Registros por página (default: 50, max: 500) |
| `startDate` | string | No | Fecha inicio en formato YYYY-MM-DD |
| `endDate` | string | No | Fecha fin en formato YYYY-MM-DD |
| `status` | string | No | Filtrar por estatus: `success` o `failed` |
| `search` | string | No | Buscar en teléfono, mensaje o error |

**Ejemplo de uso desde JavaScript/TypeScript:**
```javascript
async function getWhatsAppMessages(customerApiId, filters = {}) {
    const params = new URLSearchParams({
        page: filters.page || 1,
        perPage: filters.perPage || 20,
        ...(filters.startDate && { startDate: filters.startDate }),
        ...(filters.endDate && { endDate: filters.endDate }),
        ...(filters.status && { status: filters.status }),
        ...(filters.search && { search: filters.search })
    });

    const response = await fetch(
        `https://tu-servidor.com/clubcheck/api/customers/whatsapp/messages/${customerApiId}?${params}`,
        {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        }
    );

    return await response.json();
}

// Ejemplo de uso
const messages = await getWhatsAppMessages('CLUB-001', {
    page: 1,
    perPage: 20,
    status: 'failed',
    startDate: '2026-03-01',
    endDate: '2026-03-10',
    search: '5512345678'
});

console.log(messages);
```

**Respuesta:**
```json
{
    "customerApiId": "CLUB-001",
    "messages": [
        {
            "Id": "uuid-123",
            "UserId": "USER-001",
            "CustomerApiId": "CLUB-001",
            "PhoneNumber": "525512345678",
            "Message": "Aviso de membresía: vence en 3 días. Club: Mi Club",
            "SentDay": "2026-03-10",
            "SentHour": "14:30:00",
            "Successful": 1,
            "ErrorMessage": null
        }
    ],
    "pagination": {
        "total": 150,
        "page": 1,
        "perPage": 20,
        "totalPages": 8,
        "hasNextPage": true,
        "hasPrevPage": false
    },
    "filters": {
        "startDate": "2026-03-01",
        "endDate": "2026-03-10",
        "status": "failed",
        "search": "5512345678"
    }
}
```

#### 4. Enviar mensaje de nueva suscripción
```http
POST /clubcheck/api/customers/whatsapp/send/subscription
Content-Type: application/json

{
    "customerApiId": "CLUB-001",
    "subscriptionId": "SUB-123",
    "phone": "5512345678",
    "userId": "USER-001",
    "firstName": "Juan",
    "clubName": "Mi Club",
    "startDate": "10/03/2026",
    "endDate": "10/04/2026"
}
```

**Campos obligatorios:** `customerApiId`, `subscriptionId`, `phone`

**Respuesta exitosa:**
```json
{
    "success": true,
    "subscriptionId": "SUB-123",
    "messageId": "wamid.xxx",
    "error": null
}
```

#### 5. Enviar mensaje de advertencia (vencimiento próximo)
```http
POST /clubcheck/api/customers/whatsapp/send/warning
Content-Type: application/json

{
    "customerApiId": "CLUB-001",
    "subscriptionId": "SUB-123",
    "phone": "5512345678",
    "userId": "USER-001",
    "clubName": "Mi Club",
    "days": 3
}
```

#### 6. Enviar mensaje de membresía finalizada
```http
POST /clubcheck/api/customers/whatsapp/send/finalized
Content-Type: application/json

{
    "customerApiId": "CLUB-001",
    "subscriptionId": "SUB-123",
    "phone": "5512345678",
    "userId": "USER-001",
    "clubName": "Mi Club"
}
```

#### 7. Enviar mensaje de último día
```http
POST /clubcheck/api/customers/whatsapp/send/last-day
Content-Type: application/json

{
    "customerApiId": "CLUB-001",
    "subscriptionId": "SUB-123",
    "phone": "5512345678",
    "userId": "USER-001",
    "clubName": "Mi Club"
}
```

#### 8. Envío en bulk (múltiples mensajes)

Este endpoint permite enviar múltiples mensajes en una sola petición y devuelve los `subscriptionId` de los mensajes exitosos para que el frontend pueda actualizar el estado de las suscripciones.

```http
POST /clubcheck/api/customers/whatsapp/send/bulk
Content-Type: application/json

{
    "customerApiId": "CLUB-001",
    "clubName": "Mi Club",
    "items": [
        {
            "template": "subscription",
            "subscriptionId": "SUB-001",
            "phone": "5512345678",
            "userId": "USER-001",
            "parameters": {
                "firstName": "Juan",
                "startDate": "10/03/2026",
                "endDate": "10/04/2026"
            }
        },
        {
            "template": "warning",
            "subscriptionId": "SUB-002",
            "phone": "5522334455",
            "userId": "USER-002",
            "parameters": {
                "days": 2
            }
        },
        {
            "template": "finalized",
            "subscriptionId": "SUB-003",
            "phone": "5533445566",
            "parameters": {}
        }
    ]
}
```

**Templates disponibles:**
- `subscription` / `new_subscription` - Bienvenida de membresía
- `warning` / `warning_subscription` - Advertencia de vencimiento próximo
- `finalized` / `finalized_subscription` - Membresía finalizada
- `last_day` / `warning_last_day` - Último día de membresía

**Parámetros por template:**
| Template | Parámetros |
|----------|------------|
| `subscription` | `firstName`, `startDate`, `endDate` |
| `warning` | `days` (número de días restantes) |
| `finalized` | (ninguno adicional) |
| `last_day` | (ninguno adicional) |

**Respuesta:**
```json
{
    "success": [
        { "subscriptionId": "SUB-001", "messageId": "wamid.xxx" },
        { "subscriptionId": "SUB-002", "messageId": "wamid.yyy" }
    ],
    "failed": [
        { "subscriptionId": "SUB-003", "error": "Número de teléfono inválido" }
    ],
    "total": 3,
    "successCount": 2,
    "failedCount": 1
}
```

El frontend puede usar el array `success` para actualizar las suscripciones que ya tienen su mensaje enviado.

### Panel administrativo y monitoreo

- Después de iniciar sesión accedes al panel `/admin`, con accesos directos a gestión de versiones, herramientas y clientes.
- La tarjeta **Clientes y tokens** abre `/admin/customers`, que muestra una tabla con nombre, ID, token actual, estado y marca de "esperando nuevo token".
- Desde la tabla puedes:
  - Crear o editar clientes (nombre, token opcional, estado activo).
  - Marcar o cancelar la espera de un token con un clic, lo que habilita a la app para registrarlo.
  - Registrar manualmente un token (útil para pruebas o cuando el cliente no puede enviarlo automáticamente).
  - Copiar el token vigente al portapapeles y activar/desactivar clientes rápidamente.
- El listado se puede refrescar bajo demanda con el botón **Actualizar**, y cada fila muestra metadatos útiles como la última vez que se actualizó el token o se recibió actividad desde la app de escritorio.

## 🔒 Seguridad

- **Validación de archivos**: Solo permite archivos .exe
- **Verificación SHA256**: Hash automático para integridad
- **Límites de subida**: Configurados en .htaccess
- **Sanitización**: Escape de datos para prevenir XSS

## ⚙️ Configuración

### Modificar URL base
Edita `index.php` línea ~45 para cambiar la URL base:
```php
$baseUrl = 'https://tu-dominio.com/ruta';
```

### Cambiar límites de subida
Modifica `.htaccess` para ajustar límites:
```apache
php_value upload_max_filesize 500M
php_value post_max_size 500M
```

### Personalizar nombre de archivo
Cambia el patrón de nomenclatura en `index.php` línea ~50:
```php
$fileName = "TuApp-{$version}.exe";
```

## 📱 Integración con Aplicaciones

### Verificar y descargar actualizaciones (C#)
```csharp
using System;
using System.IO;
using System.Net.Http;
using System.Threading.Tasks;
using Newtonsoft.Json;

public class UpdateManager
{
    private readonly string baseUrl = "http://tu-servidor/clubcheck";
    private readonly string currentVersion = "1.2.2.0";
    
    public async Task<UpdateInfo> CheckForUpdates()
    {
        using var client = new HttpClient();
        
        var payload = new { currentVersion = this.currentVersion };
        var json = JsonConvert.SerializeObject(payload);
        var content = new StringContent(json, System.Text.Encoding.UTF8, "application/json");
        
        var response = await client.PostAsync($"{baseUrl}/check-update.php", content);
        var responseContent = await response.Content.ReadAsStringAsync();
        
        return JsonConvert.DeserializeObject<UpdateInfo>(responseContent);
    }
    
    public async Task<bool> DownloadUpdate(string downloadPath)
    {
        try
        {
            using var client = new HttpClient();
            var response = await client.GetAsync($"{baseUrl}/download.php");
            
            if (response.IsSuccessStatusCode)
            {
                var content = await response.Content.ReadAsByteArrayAsync();
                await File.WriteAllBytesAsync(downloadPath, content);
                return true;
            }
            
            return false;
        }
        catch
        {
            return false;
        }
    }
}

public class UpdateInfo
{
    public bool HasUpdate { get; set; }
    public string ServerVersion { get; set; }
    public string ClientVersion { get; set; }
    public bool Mandatory { get; set; }
    public string ReleaseNotes { get; set; }
    public string DownloadUrl { get; set; }
    public string Sha256 { get; set; }
    public bool FileAvailable { get; set; }
    public long FileSize { get; set; }
}
```

### Verificar actualizaciones (JavaScript/Electron)
```javascript
class UpdateManager {
    constructor(baseUrl, currentVersion) {
        this.baseUrl = baseUrl;
        this.currentVersion = currentVersion;
    }
    
    async checkForUpdates() {
        try {
            const response = await fetch(`${this.baseUrl}/check-update.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    currentVersion: this.currentVersion
                })
            });
            
            return await response.json();
        } catch (error) {
            console.error('Error checking for updates:', error);
            return { hasUpdate: false, error: error.message };
        }
    }
    
    async downloadUpdate(progressCallback) {
        try {
            const response = await fetch(`${this.baseUrl}/download.php`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentLength = response.headers.get('content-length');
            const total = parseInt(contentLength, 10);
            let loaded = 0;
            
            const reader = response.body.getReader();
            const chunks = [];
            
            while (true) {
                const { done, value } = await reader.read();
                
                if (done) break;
                
                chunks.push(value);
                loaded += value.length;
                
                if (progressCallback && total) {
                    progressCallback(Math.round((loaded / total) * 100));
                }
            }
            
            // Combinar chunks en un solo array
            const chunksAll = new Uint8Array(loaded);
            let position = 0;
            for (const chunk of chunks) {
                chunksAll.set(chunk, position);
                position += chunk.length;
            }
            
            return chunksAll;
        } catch (error) {
            console.error('Error downloading update:', error);
            throw error;
        }
    }
}

// Uso
const updateManager = new UpdateManager('http://tu-servidor/clubcheck', '1.2.2.0');

// Verificar actualizaciones
updateManager.checkForUpdates().then(updateInfo => {
    if (updateInfo.hasUpdate) {
        console.log(`Nueva versión disponible: ${updateInfo.serverVersion}`);
        console.log(`Notas: ${updateInfo.releaseNotes}`);
        
        // Descargar si es necesario
        if (updateInfo.mandatory || confirm('¿Descargar actualización?')) {
            updateManager.downloadUpdate(progress => {
                console.log(`Progreso: ${progress}%`);
            }).then(fileData => {
                console.log('Actualización descargada exitosamente');
                // Guardar archivo y reiniciar aplicación
            });
        }
    }
});
```

### Verificar actualizaciones (Python)
```python
import requests
import hashlib
import os
from typing import Optional, Dict, Any

class UpdateManager:
    def __init__(self, base_url: str, current_version: str):
        self.base_url = base_url
        self.current_version = current_version
    
    def check_for_updates(self) -> Dict[str, Any]:
        """Verifica si hay actualizaciones disponibles"""
        try:
            response = requests.post(
                f"{self.base_url}/check-update.php",
                json={"currentVersion": self.current_version},
                timeout=10
            )
            response.raise_for_status()
            return response.json()
        except requests.RequestException as e:
            return {"hasUpdate": False, "error": str(e)}
    
    def download_update(self, download_path: str, progress_callback=None) -> bool:
        """Descarga la actualización"""
        try:
            response = requests.get(
                f"{self.base_url}/download.php",
                stream=True,
                timeout=30
            )
            response.raise_for_status()
            
            total_size = int(response.headers.get('content-length', 0))
            downloaded = 0
            
            with open(download_path, 'wb') as file:
                for chunk in response.iter_content(chunk_size=8192):
                    if chunk:
                        file.write(chunk)
                        downloaded += len(chunk)
                        
                        if progress_callback and total_size > 0:
                            progress = int((downloaded / total_size) * 100)
                            progress_callback(progress)
            
            return True
            
        except requests.RequestException as e:
            print(f"Error downloading update: {e}")
            return False
    
    def verify_file_integrity(self, file_path: str, expected_hash: str) -> bool:
        """Verifica la integridad del archivo descargado"""
        try:
            sha256_hash = hashlib.sha256()
            with open(file_path, 'rb') as file:
                for chunk in iter(lambda: file.read(4096), b""):
                    sha256_hash.update(chunk)
            
            return sha256_hash.hexdigest().lower() == expected_hash.lower()
        except Exception as e:
            print(f"Error verifying file integrity: {e}")
            return False

# Uso
update_manager = UpdateManager("http://tu-servidor/clubcheck", "1.2.2.0")

# Verificar actualizaciones
update_info = update_manager.check_for_updates()

if update_info.get("hasUpdate"):
    print(f"Nueva versión disponible: {update_info['serverVersion']}")
    print(f"Notas: {update_info['releaseNotes']}")
    
    # Descargar actualización
    download_path = "MyApp_update.exe"
    
    def progress_callback(percent):
        print(f"Descargando: {percent}%")
    
    if update_manager.download_update(download_path, progress_callback):
        # Verificar integridad
        if update_manager.verify_file_integrity(download_path, update_info['sha256']):
            print("Actualización descargada y verificada exitosamente")
        else:
            print("Error: La integridad del archivo no es válida")
            os.remove(download_path)
    else:
        print("Error descargando la actualización")
else:
    print("No hay actualizaciones disponibles")
```

## 🔍 Estructura del JSON

El archivo `version.json` mantiene la siguiente estructura:

```json
{
    "latestVersion": "1.2.3.0",
    "url": "http://tu-servidor/clubcheck/uploads/MyApp-1.2.3.0.exe",
    "sha256": "hash_sha256_del_archivo",
    "mandatory": false,
    "releaseNotes": "Descripción de cambios"
}
```

## 🚨 Troubleshooting

### Error de permisos
```bash
chmod 755 uploads/
chown www-data:www-data uploads/
```

### Archivo muy grande
Aumenta los límites en `.htaccess` y `php.ini`:
```ini
upload_max_filesize = 1G
post_max_size = 1G
max_execution_time = 600
```

### JSON no se actualiza
Verifica permisos de escritura:
```bash
chmod 644 version.json
```

## 📞 Soporte

Para problemas o mejoras, revisa:
1. Logs del servidor web
2. Configuración de PHP
3. Permisos de archivos y directorios
