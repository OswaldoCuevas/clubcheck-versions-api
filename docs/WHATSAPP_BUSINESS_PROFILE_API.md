# API de Perfil de Negocio de WhatsApp

Esta documentación describe cómo usar los endpoints para registrar y obtener el perfil del negocio en WhatsApp Business.

## Endpoints Disponibles

### 1. Registrar/Actualizar Perfil del Negocio

**Endpoint:** `POST /api/customers/whatsapp/business-profile/register`

**Descripción:** Registra o actualiza el perfil del negocio de WhatsApp con el nombre del propietario que se mostrará al enviar mensajes y opcionalmente el logo de la empresa.

#### Request Body (JSON)

```json
{
  "businessName": "Mi Gimnasio",
  "logo": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==",
  "logoFilename": "logo.png",
  "address": "Calle Principal 123, Ciudad de México",
  "description": "El mejor gimnasio de la ciudad con equipamiento de última generación",
  "email": "contacto@migimnasio.com",
  "vertical": "HEALTH",
  "websites": ["https://migimnasio.com", "https://www.facebook.com/migimnasio"]
}
```

#### Campos del Request

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `businessName` | string | ✅ Sí | Nombre del negocio que aparecerá en los mensajes (máx. 256 caracteres) |
| `logo` | string | ❌ No | Logo en base64 (puede incluir o no el prefijo `data:image/...;base64,`) |
| `logoFilename` | string | ❌ No | Nombre del archivo del logo (ej: "logo.png") |
| `address` | string | ❌ No | Dirección física del negocio |
| `description` | string | ❌ No | Descripción detallada del negocio (máx. 512 caracteres) |
| `email` | string | ❌ No | Email de contacto |
| `vertical` | string | ❌ No | Industria del negocio (ver lista abajo) |
| `websites` | array | ❌ No | Lista de URLs del negocio |

#### Valores válidos para `vertical`

- `AUTO` - Automotriz
- `BEAUTY` - Belleza, Spa y Salón
- `APPAREL` - Ropa y Accesorios
- `EDU` - Educación
- `ENTERTAIN` - Entretenimiento
- `EVENT_PLAN` - Planificación de Eventos
- `FINANCE` - Finanzas y Banca
- `GROCERY` - Comestibles
- `GOVT` - Gobierno
- `HOTEL` - Hotel y Alojamiento
- `HEALTH` - Salud
- `NONPROFIT` - Sin Fines de Lucro
- `PROF_SERVICES` - Servicios Profesionales
- `RETAIL` - Comercio Minorista
- `TRAVEL` - Viajes y Transporte
- `RESTAURANT` - Restaurante
- `NOT_A_BIZ` - No es un Negocio

#### Response Exitoso (200 OK)

```json
{
  "success": true,
  "data": {
    "businessName": "Mi Gimnasio",
    "profileUpdated": true,
    "logoUploaded": true,
    "response": {
      "success": true
    }
  }
}
```

#### Response de Error (422 Unprocessable Entity)

```json
{
  "success": false,
  "error": "Error al subir el logo: La imagen no existe"
}
```

---

### 2. Obtener Perfil del Negocio

**Endpoint:** `GET /api/customers/whatsapp/business-profile`

**Descripción:** Obtiene la información actual del perfil del negocio de WhatsApp.

#### Response Exitoso (200 OK)

```json
{
  "success": true,
  "profile": {
    "about": "Mi Gimnasio",
    "address": "Calle Principal 123, Ciudad de México",
    "description": "El mejor gimnasio de la ciudad",
    "email": "contacto@migimnasio.com",
    "profile_picture_url": "https://lookaside.fbsbx.com/whatsapp_business/...",
    "websites": ["https://migimnasio.com"],
    "vertical": "HEALTH"
  }
}
```

#### Response de Error (422 Unprocessable Entity)

```json
{
  "success": false,
  "error": "WhatsApp no está configurado"
}
```

---

## Ejemplos de Uso

### Ejemplo 1: Registro básico con solo nombre

```bash
curl -X POST https://tu-dominio.com/api/customers/whatsapp/business-profile/register \
  -H "Content-Type: application/json" \
  -d '{
    "businessName": "Gimnasio FitLife"
  }'
```

### Ejemplo 2: Registro completo con logo y toda la información

```bash
curl -X POST https://tu-dominio.com/api/customers/whatsapp/business-profile/register \
  -H "Content-Type: application/json" \
  -d '{
    "businessName": "Gimnasio FitLife",
    "logo": "'"$(base64 -w 0 logo.png)"'",
    "logoFilename": "logo.png",
    "address": "Av. Reforma 123, Col. Centro, CDMX",
    "description": "Gimnasio con 20 años de experiencia. Clases grupales, área de pesas y cardio.",
    "email": "info@fitlife.com",
    "vertical": "HEALTH",
    "websites": ["https://fitlife.com"]
  }'
```

### Ejemplo 3: Obtener perfil actual

```bash
curl -X GET https://tu-dominio.com/api/customers/whatsapp/business-profile \
  -H "Content-Type: application/json"
```

### Ejemplo 4: Usando JavaScript (Fetch API)

```javascript
// Cargar y convertir imagen a base64
async function uploadLogoAndRegisterProfile() {
  try {
    // Leer archivo de logo
    const fileInput = document.getElementById('logoInput');
    const file = fileInput.files[0];
    
    // Convertir a base64
    const base64Logo = await fileToBase64(file);
    
    // Registrar perfil
    const response = await fetch('/api/customers/whatsapp/business-profile/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        businessName: 'Mi Gimnasio',
        logo: base64Logo,
        logoFilename: file.name,
        address: 'Calle Principal 123',
        description: 'Descripción del gimnasio',
        email: 'contacto@gimnasio.com',
        vertical: 'HEALTH',
        websites: ['https://migimnasio.com']
      })
    });
    
    const result = await response.json();
    console.log('Resultado:', result);
  } catch (error) {
    console.error('Error:', error);
  }
}

// Función auxiliar para convertir File a base64
function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      // Extraer solo la parte base64 (sin el prefijo data:image/...)
      const base64 = reader.result.split(',')[1];
      resolve(base64);
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}
```

### Ejemplo 5: Usando PHP

```php
<?php

// Leer logo y convertir a base64
$logoPath = '/path/to/logo.png';
$logoData = base64_encode(file_get_contents($logoPath));

// Preparar datos
$data = [
    'businessName' => 'Mi Gimnasio',
    'logo' => $logoData,
    'logoFilename' => 'logo.png',
    'address' => 'Calle Principal 123',
    'description' => 'El mejor gimnasio',
    'email' => 'contacto@gimnasio.com',
    'vertical' => 'HEALTH',
    'websites' => ['https://migimnasio.com']
];

// Enviar request
$ch = curl_init('https://tu-dominio.com/api/customers/whatsapp/business-profile/register');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
```

---

## Notas Importantes

1. **Formato del Logo:**
   - Formatos soportados: JPG, PNG
   - Tamaño recomendado: 640x640 píxeles
   - Peso máximo: 5 MB
   - El logo debe estar codificado en base64

2. **Límites de Caracteres:**
   - `businessName` / `about`: 256 caracteres
   - `description`: 512 caracteres

3. **Configuración Requerida:**
   - Asegúrate de tener configuradas las variables de entorno:
     - `WHATSAPP_API_URL=https://graph.facebook.com/v21.0`
     - `WHATSAPP_PHONE_NUMBER_ID=tu_phone_number_id`
     - `WHATSAPP_ACCESS_TOKEN=tu_access_token`

4. **Permisos:**
   - Tu token de acceso debe tener permisos de `whatsapp_business_management`
   - El número de teléfono debe estar verificado en WhatsApp Business

5. **Actualización:**
   - Si llamas al endpoint múltiples veces, los datos se actualizarán
   - No es necesario eliminar el perfil anterior

---

## Errores Comunes

### Error: "WhatsApp no está configurado"
**Causa:** Las variables de entorno no están configuradas correctamente.
**Solución:** Verifica que `WHATSAPP_PHONE_NUMBER_ID` y `WHATSAPP_ACCESS_TOKEN` estén definidos en tu archivo `.env`.

### Error: "El logo no es un base64 válido"
**Causa:** La cadena base64 del logo está corrupta o mal formateada.
**Solución:** Asegúrate de codificar correctamente la imagen en base64.

### Error: "Error al subir el logo: La imagen no existe"
**Causa:** El archivo temporal del logo no pudo ser creado.
**Solución:** Verifica que el directorio temporal tenga permisos de escritura.

### Error: HTTP 403
**Causa:** El token de acceso no tiene los permisos necesarios.
**Solución:** Regenera el token con el permiso `whatsapp_business_management`.

---

## Referencia de la API de WhatsApp

Documentación oficial: https://developers.facebook.com/docs/whatsapp/cloud-api/reference/business-profiles
