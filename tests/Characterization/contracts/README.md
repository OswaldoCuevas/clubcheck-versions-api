# Inventario HTTP de los módulos administrativos

`admin-controller.json` y `customers-controller.json` son los inventarios
versionados de los contratos basales.
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

`customers-controller.json` protege las acciones heredadas de
los endpoints de clientes durante su migración a Features del monolito modular.

No se debe cambiar una expectativa únicamente para hacer pasar una prueba.
Primero hay que decidir si el cambio de comportamiento fue intencional.
