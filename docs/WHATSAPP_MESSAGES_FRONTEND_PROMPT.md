# Prompt de Implementación - Listado de Mensajes WhatsApp

Este documento contiene un prompt detallado para implementar la interfaz de listado de mensajes de WhatsApp en el frontend.

---

## Prompt para el Frontend Developer / AI

Necesito implementar una interfaz completa para listar y filtrar los mensajes de WhatsApp enviados desde el backend. La aplicación está construida en [especifica tu framework: React, Vue, Angular, WPF, etc.].

### Contexto
Tengo un API REST que gestiona el envío de mensajes de WhatsApp a clientes de un gimnasio/club. El backend ya tiene implementado el endpoint de listado con filtros avanzados.

### Especificaciones del Endpoint

**URL:** `GET /api/customers/whatsapp/messages/:customerApiId`

**Query Parameters:**
- `page` (opcional, default: 1) - Número de página
- `perPage` (opcional, default: 50, max: 500) - Registros por página
- `startDate` (opcional) - Fecha inicio formato YYYY-MM-DD
- `endDate` (opcional) - Fecha fin formato YYYY-MM-DD  
- `status` (opcional) - Filtrar por: `success` o `failed`
- `search` (opcional) - Buscar en teléfono, mensaje o error

**Respuesta del API:**
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

### Requisitos de UI/UX

1. **Tabla/Lista de Mensajes:**
   - Mostrar: Fecha y hora, Teléfono, Mensaje, Estado (exitoso/fallido)
   - Indicador visual para mensajes exitosos (verde) y fallidos (rojo)
   - Si el mensaje falló, mostrar el error
   - Ordenados por fecha descendente (más reciente primero)

2. **Filtros:**
   - **Rango de fechas:** Selector de fecha inicio y fecha fin
   - **Estado:** Dropdown o botones para filtrar por Todos/Exitosos/Fallidos
   - **Búsqueda:** Input de texto para buscar por teléfono, mensaje o error
   - Botón "Limpiar filtros" para resetear todos los filtros

3. **Paginación:**
   - Mostrar información: "Mostrando X - Y de Z mensajes"
   - Botones: Primera página, Anterior, Siguiente, Última página
   - Selector de registros por página (10, 20, 50, 100)
   - Input para ir a página específica

4. **Estados de Carga:**
   - Indicador de carga mientras se obtienen los datos
   - Mensaje cuando no hay resultados
   - Manejo de errores con mensajes amigables

5. **Diseño Responsivo:**
   - En móviles, convertir la tabla en cards/tarjetas
   - Los filtros deben ser colapsables en pantallas pequeñas

### Funcionalidades Adicionales (Opcionales)

- Exportar a CSV o Excel
- Botón para reenviar un mensaje fallido
- Ver detalles completos en un modal al hacer clic en un mensaje
- Auto-refresh cada X segundos
- Indicador de mensajes recién llegados

### Estructura de Código Esperada

```typescript
// Tipos/Interfaces
interface WhatsAppMessage {
    Id: string;
    UserId: string | null;
    CustomerApiId: string;
    PhoneNumber: string;
    Message: string;
    SentDay: string;
    SentHour: string;
    Successful: number; // 0 o 1
    ErrorMessage: string | null;
}

interface Pagination {
    total: number;
    page: number;
    perPage: number;
    totalPages: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
}

interface MessageFilters {
    page?: number;
    perPage?: number;
    startDate?: string;
    endDate?: string;
    status?: 'success' | 'failed' | '';
    search?: string;
}

interface MessagesResponse {
    customerApiId: string;
    messages: WhatsAppMessage[];
    pagination: Pagination;
    filters: MessageFilters;
}

// Función de llamada al API
async function getWhatsAppMessages(
    customerApiId: string, 
    filters: MessageFilters
): Promise<MessagesResponse> {
    const params = new URLSearchParams();
    
    params.append('page', String(filters.page || 1));
    params.append('perPage', String(filters.perPage || 20));
    
    if (filters.startDate) params.append('startDate', filters.startDate);
    if (filters.endDate) params.append('endDate', filters.endDate);
    if (filters.status) params.append('status', filters.status);
    if (filters.search) params.append('search', filters.search);

    const response = await fetch(
        `${API_BASE_URL}/api/customers/whatsapp/messages/${customerApiId}?${params}`,
        {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        }
    );

    if (!response.ok) {
        throw new Error('Error al obtener mensajes');
    }

    return await response.json();
}
```

### Ejemplo de Uso del Componente

```jsx
// React
<WhatsAppMessagesViewer 
    customerApiId="CLUB-001"
    onMessageClick={(message) => console.log(message)}
/>

// Vue
<whatsapp-messages-viewer 
    :customer-api-id="'CLUB-001'"
    @message-click="handleMessageClick"
/>

// Angular
<app-whatsapp-messages-viewer 
    [customerApiId]="'CLUB-001'"
    (messageClick)="handleMessageClick($event)">
</app-whatsapp-messages-viewer>
```

### Configuración del API Base URL

Asegúrate de configurar la URL base del API:
```javascript
const API_BASE_URL = 'https://tu-servidor.com/clubcheck';
```

### Consideraciones de Seguridad

- Validar y sanitizar las entradas del usuario antes de enviarlas al API
- Implementar debounce en el campo de búsqueda (300-500ms)
- No exponer datos sensibles en los logs del navegador

### Testing

Por favor, incluir pruebas para:
- Carga inicial de mensajes
- Aplicación de filtros
- Cambio de página
- Manejo de errores
- Estado de carga

---

## Ejemplo de Implementación en React

Si estás usando React, aquí tienes un punto de partida:

```jsx
import React, { useState, useEffect } from 'react';
import { getWhatsAppMessages } from './api/whatsapp';

export function WhatsAppMessagesViewer({ customerApiId }) {
    const [messages, setMessages] = useState([]);
    const [pagination, setPagination] = useState(null);
    const [filters, setFilters] = useState({
        page: 1,
        perPage: 20,
        startDate: '',
        endDate: '',
        status: '',
        search: ''
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        loadMessages();
    }, [filters.page, filters.perPage, filters.status]);

    const loadMessages = async () => {
        setLoading(true);
        setError(null);
        
        try {
            const response = await getWhatsAppMessages(customerApiId, filters);
            setMessages(response.messages);
            setPagination(response.pagination);
        } catch (err) {
            setError('Error al cargar los mensajes');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleFilterChange = (key, value) => {
        setFilters(prev => ({
            ...prev,
            [key]: value,
            page: 1 // Reset a página 1 al cambiar filtros
        }));
    };

    const handleSearch = () => {
        loadMessages();
    };

    const clearFilters = () => {
        setFilters({
            page: 1,
            perPage: 20,
            startDate: '',
            endDate: '',
            status: '',
            search: ''
        });
    };

    // ... resto del componente con el render
}
```

---

## Preguntas para Aclarar

Antes de comenzar la implementación, por favor confirma:

1. ¿Qué framework/librería estás usando? (React, Vue, Angular, WPF, etc.)
2. ¿Usas alguna librería de UI? (Material-UI, Ant Design, Bootstrap, etc.)
3. ¿Tienes alguna guía de estilos o diseño específico a seguir?
4. ¿Necesitas manejo de estado global? (Redux, Vuex, NgRx, etc.)
5. ¿Qué librería usas para las llamadas HTTP? (axios, fetch, etc.)
6. ¿Necesitas soporte para algún idioma específico? (i18n)

Por favor implementa el componente completo con estas especificaciones.
