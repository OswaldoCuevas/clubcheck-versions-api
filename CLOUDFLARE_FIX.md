# Configuración para tu aplicación para pasar Cloudflare

## Headers requeridos para evitar bloqueos de Cloudflare:

### 1. User-Agent (MUY IMPORTANTE)
```
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36
```

### 2. Headers adicionales
```
Accept: application/json, text/plain, */*
Accept-Language: en-US,en;q=0.9,es;q=0.8
Accept-Encoding: gzip, deflate, br
Connection: keep-alive
Cache-Control: no-cache
Pragma: no-cache
Referer: https://admin-apis.com/clubcheck/
Origin: https://admin-apis.com
```

### 3. Configuración de timeout
```
Connect Timeout: 10 segundos
Request Timeout: 30 segundos (no 43s o 40min)
```

## Código de ejemplo para tu app:

### C# / .NET
```csharp
using (var client = new HttpClient())
{
    client.DefaultRequestHeaders.Add("User-Agent", 
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36");
    client.DefaultRequestHeaders.Add("Accept", "application/json");
    client.DefaultRequestHeaders.Add("Referer", "https://admin-apis.com/clubcheck/");
    
    client.Timeout = TimeSpan.FromSeconds(30); // NO 43 segundos
    
    var response = await client.GetAsync("https://admin-apis.com/clubcheck/api/version");
    var content = await response.Content.ReadAsStringAsync();
}
```

### Python
```python
import requests

headers = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Accept': 'application/json',
    'Referer': 'https://admin-apis.com/clubcheck/',
    'Accept-Language': 'en-US,en;q=0.9'
}

response = requests.get('https://admin-apis.com/clubcheck/api/version', 
                       headers=headers, 
                       timeout=30)  # NO 43 segundos
```

### Node.js
```javascript
const axios = require('axios');

const config = {
    headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept': 'application/json',
        'Referer': 'https://admin-apis.com/clubcheck/'
    },
    timeout: 30000 // 30 segundos, NO 43000
};

const response = await axios.get('https://admin-apis.com/clubcheck/api/version', config);
```

## Verificación inmediata:

### 1. Test con curl (simula navegador)
```bash
curl -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
     -H "Accept: application/json" \
     -H "Referer: https://admin-apis.com/clubcheck/" \
     --max-time 30 \
     "https://admin-apis.com/clubcheck/api/version"
```

### 2. Test con curl (simula app)
```bash
curl -H "User-Agent: MyApp/1.0" \
     --max-time 30 \
     "https://admin-apis.com/clubcheck/api/version"
```

## Configuración de Cloudflare recomendada:

### Page Rules para /clubcheck/api/*:
- Security Level: Low o Essentially Off
- Cache Level: Bypass
- Browser Integrity Check: Off
- Email Obfuscation: Off

### Firewall Rules:
- Allow específico para tu rango de IPs
- Skip security para User-Agents conocidos de tu app

### Rate Limiting:
- Excluir /api/ paths de rate limiting
- O configurar límites más altos para APIs
