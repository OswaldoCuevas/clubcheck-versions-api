# Pruebas de caracterización de CustomersController

Esta suite conserva el comportamiento HTTP observable de
`app/Controllers/CustomersController.php` antes de migrarlo al monolito
modular. PHPUnit coordina la prueba y Guzzle envía peticiones reales a un
servidor PHP local aislado.

No son pruebas unitarias de lógica interna: el controlador actual crea sus
dependencias directamente y termina las respuestas con `exit`. Por eso la
caracterización HTTP es la protección más fiel antes de separar Features,
Handlers y Repositories. También existe una prueba unitaria estructural que
impide agregar o quitar una acción pública sin actualizar el inventario.

## Base de datos aislada

La suite reutiliza la infraestructura de caracterización administrativa:

1. crea un esquema aleatorio con el patrón seguro
   `clubcheck_admin_test_<16 hexadecimales>`;
2. aplica `database/schema.sql`;
3. aplica todos los archivos de `database/migrations/`;
4. carga fixtures conocidos para cada prueba;
5. elimina únicamente ese esquema al terminar.

Puede configurarse con:

```text
CLUBCHECK_TEST_DB_HOST=127.0.0.1
CLUBCHECK_TEST_DB_PORT=3306
CLUBCHECK_TEST_DB_USERNAME=root
CLUBCHECK_TEST_DB_PASSWORD=
```

## Ejecución

```powershell
composer test:customers-http
```

Para una clase específica:

```powershell
vendor\bin\phpunit --filter CustomerSessionWorkflowTest --testdox
vendor\bin\phpunit --filter CustomerProtectedWorkflowTest --testdox
```

## Cobertura actual

- inventario de las 18 acciones públicas y comparación con el Router;
- método HTTP, path y middleware `customer_jwt` de cada endpoint;
- CORS/OPTIONS en todas las rutas;
- rechazo de rutas protegidas sin JWT;
- ciclo de vida de sesiones;
- lectura y actualización del cliente autenticado;
- pull y push de sincronización con bulks vacíos;
- round trip con inserción, actualización, persistencia y lectura de datos en
  `users`, `subscriptions`, `attendances` y `products`;
- filtrado de registros eliminados mediante `includeRemovedByBulk`;
- consulta, espera y registro del token de máquina;
- validación de JWT por body y por header;
- validaciones de payload, JSON inválido y métodos incorrectos;
- contador mensual de mensajes.

Los flujos que llaman realmente a Stripe no se ejecutan para evitar depender
de una red o cuenta externa. Sus validaciones anteriores a la llamada sí se
caracterizan. Cuando se migren esas acciones, Stripe debe quedar detrás de un
contrato inyectable para probar también éxito y errores con una implementación
falsa.

## Comportamientos heredados detectados

La suite documenta estos comportamientos sin corregirlos:

- un heartbeat enviado después de finalizar una sesión vuelve a marcarla como
  activa y responde `200`;
- `GET /api/customers/validate` coincide con la ruta genérica
  `/api/customers/:customerId` y responde `401 TOKEN_MISSING`.

Si se decide corregirlos, debe hacerse deliberadamente y actualizar el
contrato en un cambio separado.

## Inventario y artefactos

El contrato versionado está en
`tests/Characterization/contracts/customers-controller.json`.

Cada petición guarda método, path, body/opciones, expectativa y respuesta en:

```text
tests/.artifacts/http-characterization/latest/
tests/.artifacts/http-characterization/runs/<fecha-proceso>/
```

Los artefactos y la base temporal están ignorados por Git. No se debe cambiar
una expectativa solamente para hacer pasar una prueba: primero se debe decidir
si el nuevo comportamiento es intencional.
