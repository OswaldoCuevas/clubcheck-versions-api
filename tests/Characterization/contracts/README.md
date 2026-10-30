# Inventario HTTP de AdminController

`admin-controller.json` es el inventario versionado de los contratos basales.
Cada entrada guarda:

- identificador y nombre del caso;
- método del controlador y ruta HTTP;
- método, path y body/opciones enviados;
- status, headers y fragmentos de respuesta esperados;
- efectos secundarios esperados.

Las respuestas completas recibidas no se versionan porque incluyen fechas,
puertos y otros datos de ejecución. Cada corrida las escribe en:

```text
tests/.artifacts/http-characterization/latest/
tests/.artifacts/http-characterization/runs/<fecha-proceso>/
```

Cada artefacto contiene la solicitud, la expectativa, la respuesta real y
`httpContractMatched`. Cookies, CSRF, contraseñas, access keys, tokens de
máquina y licencias se reemplazan por `<redacted>`.

No se debe cambiar una expectativa únicamente para hacer pasar una prueba.
Primero hay que decidir si el cambio de comportamiento fue intencional.
