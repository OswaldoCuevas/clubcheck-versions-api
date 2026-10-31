# Pruebas HTTP de caracterización administrativa

Esta suite protege el comportamiento observable de los módulos administrativos antes
de separarlo en controladores, validadores, casos de uso y repositorios.
Aunque se ejecuta con PHPUnit, es una suite de integración/caracterización,
no una suite unitaria: Guzzle realiza peticiones HTTP reales contra un
servidor PHP local aislado.

## Seguridad de datos

La suite crea un esquema con el patrón:

```text
clubcheck_admin_test_<16 caracteres hexadecimales>
```

Primero aplica `database/schema.sql` y después todos los archivos SQL de
`database/migrations`. Las migraciones históricas que ya están incorporadas
en `schema.sql` se registran como `alreadyApplied`. Cualquier otro error
detiene la suite. Al finalizar se elimina exclusivamente el esquema aleatorio.

El runner se niega a eliminar un esquema cuyo nombre no cumpla el patrón de
seguridad. Nunca usa las credenciales de `.env` si se proporcionan las
variables `CLUBCHECK_TEST_DB_*`.

Variables opcionales:

```text
CLUBCHECK_TEST_DB_HOST=127.0.0.1
CLUBCHECK_TEST_DB_PORT=3306
CLUBCHECK_TEST_DB_USERNAME=root
CLUBCHECK_TEST_DB_PASSWORD=
```

## Ejecución

```powershell
composer test:admin-http
```

Para ejecutar solamente un grupo:

```powershell
vendor\bin\phpunit --filter AdminCustomerMutationTest --testdox
vendor\bin\phpunit --filter AdminAnnouncementMutationTest --testdox
```

## Qué se conserva

- Los 42 métodos públicos del controlador tienen un contrato basal.
- El inventario se compara con reflexión y con las rutas reales.
- Todas las rutas administrativas se prueban sin autenticación.
- Se prueban códigos HTTP, JSON, HTML y efectos en MySQL.
- Los flujos exitosos cubren clientes, anuncios, imágenes, configuraciones y
  plantillas de WhatsApp, JWT, IPs y licencias gratuitas firmadas.
- Stripe y Meta no se contactan: los casos que requieren servicios externos
  se detienen antes de la llamada. Para cubrirlos completamente será necesario
  introducir adaptadores inyectables y servidores falsos.

## Artefactos

El reporte de migraciones queda en:

```text
tests/.artifacts/database/migration-report.json
```

Las solicitudes y respuestas HTTP quedan en las carpetas descritas en
`tests/Characterization/contracts/README.md`. Estos directorios están
ignorados por Git; el contrato revisado y versionado vive en
`tests/Characterization/contracts/admin-controller.json` y en las assertions.

## Comportamientos actuales relevantes

La suite documenta, sin corregirlos todavía, estos contratos actuales:

- marcar una IP inexistente responde `200`;
- crear un cliente con un email duplicado responde `500`, mientras que un
  AccessCode duplicado responde `409`.

Cuando se decida corregirlos, debe hacerse en un cambio separado y actualizar
el contrato deliberadamente.
