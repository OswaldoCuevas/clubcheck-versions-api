# ClubCheck development rules

## Code readability and formatting

- Write PHP for human readability, following a PSR-12-style layout. Do not compress code to reduce line count or place several statements, conditions, loops, properties, or methods on the same line.
- Use one statement per line. Put braces and block bodies on separate lines, and expand `if`, `foreach`, `try/catch`, `match`, constructors, and method calls when keeping them on one line makes the code harder to scan.
- Format non-trivial associative arrays with one key/value pair per line and trailing commas. This is especially important for database rows, API payloads, dependency lists, mappings, and configuration.
- Give intermediate values descriptive variables instead of building long nested expressions. Prefer explicit steps that a person can debug over dense one-line expressions.
- Controllers, Commands, Queries, Handlers, Contracts, Repositories, Services, and tests must all follow these readability rules. Generated or migrated code is not exempt.
- The only project-specific compact-format exception is simple route registration in `routes/web.php`, where one complete route declaration may remain on one line when it is still easy to understand. Keep one route per line; complex route logic must still be expanded normally.
- Before completing a change, inspect every created or substantially modified PHP file for compressed code in addition to running `php -l` and the relevant tests. Do not consider syntactically valid but unreadable code complete.

## JSON responses from controllers

- New or migrated controller actions must send JSON through `App\Http\JsonResponse::send()` or `App\Http\JsonResponse::sendResult()`.
- Always read JSON request bodies with `App\Http\JsonPayload::parse()` in every controller that is created, modified, or migrated. Do not decode `php://input` directly or duplicate invalid-JSON handling inside controllers.
- Do not duplicate `http_response_code()`, the JSON `Content-Type` header, `json_encode()`, `echo`, or `exit` inside controllers.
- Do not extend `Core\Controller` only to reuse its `json()` method. A controller should inherit from it only when it actually needs the other legacy controller behavior.
- Keep business rules out of `JsonResponse`; it is only an HTTP presentation utility.
- Controlled API failures must use `App\Exceptions\ApiException` with a case from `App\Exceptions\ApiErrorCode`; do not add literal `error_code` strings for new exceptions.
- Add new error cases to `ApiErrorCode` with their HTTP status and safe public message before using them.
- Preserve the existing response status, keys, values, and body when migrating an endpoint.
- After changing a JSON endpoint, run its relevant unit and HTTP characterization tests. Run the complete test suite before completing a migration.

## Controller boundaries and shared HTTP helpers

- Controllers must contain only their constructor and public actions that are registered as router endpoints. Do not add private or protected controller helper methods for reusable behavior.
- Move reusable HTTP concerns to focused helpers under `App\Http`. Use `JsonPayload` for JSON bodies, `Request` for query parameters, server values, IP addresses, and authorization headers, and `JsonResponse` for JSON output.
- Do not create loose global functions. Shared behavior must live in a focused class with a clear responsibility and must remain free of business rules.
- Controller actions should translate HTTP input into a Command or Query, dispatch it through `IMediator`, and translate the result into an HTTP response. Business validation, normalization, persistence, and orchestration belong outside the controller.
- Before completing a controller change, inspect every modified modular controller and confirm it has no private or protected helper methods and no duplicated JSON parsing or response-sending logic.

## Commands, Queries, Mediator, and dependency injection

- This application is a modular monolith. Split modules by business capability (`Customers`, `Licenses`, `WhatsApp`, `Announcements`, etc.), not by the actor or UI that uses them.
- `Admin` is a delivery channel, not a catch-all business module. Keep only the dashboard shell in `Modules/Admin`; place administrative controllers inside the module that owns the capability, for example `Modules/Customers/Controllers/AdminCustomerController.php`.
- A module controller may group several closely related endpoints, but every operation must dispatch its own Command or Query to a vertical Feature. Do not recreate a global `AdminController` or a generic action switch.
- New or migrated module operations should be vertical slices under `Modules/<Module>/Features/<Feature>/`, with `Command.php` or `Query.php` beside `Handler.php`. The Mediator maps `...\\Command`/`...\\Query` to `...\\Handler` in the same feature namespace.
- Handlers must expose `handle()` and receive their service dependencies through the constructor.
- Controllers dispatch messages through `App\Application\Contracts\IMediator::send()`; they must not construct Handlers or infrastructure adapters.
- Keep module Controllers, Contracts, Features, Repositories, Services, and module service registration inside that module. Concrete repository implementations belong in `Modules/<Module>/Repositories/`; keep their interfaces in `Contracts/`. Do not place newly migrated module controllers in the legacy global `app/Controllers` folder.
- A dedicated Result DTO is optional. Return a scalar or existing value object when that fully describes the result; create a Result class when multiple named values or a stable return contract are needed.
- The autowiring Container constructs concrete classes. Register only interface bindings, scalar configuration factories, or services requiring special lifecycle behavior.
- Keep module-specific bindings in that module's `services.php`; `config/services.php` only composes shared infrastructure and module providers.
- Repository implementations execute persistence operations themselves and receive `IDatabaseConnection` through their constructor; do not use legacy Models as repository adapters. The database connection is request-scoped through the Container singleton, while stateless repositories use transient `bind()` registrations.
- Feature Handlers must not inject or instantiate legacy classes from `app/Models`. A migrated Handler depends on a module contract such as `ICustomerRepository`; the module's `services.php` binds that contract to its concrete repository.
- When migrating a legacy Model, separate its responsibilities instead of moving the whole class unchanged: SQL, database transactions, persistence-specific row mapping, and CRUD operations belong in `Modules/<Module>/Repositories/`; business validation, decisions, normalization required by the use case, and orchestration belong in the corresponding Feature Handler or a feature-local domain policy.
- Do not mark a module migration complete while a migrated Controller or Handler still references a legacy Model. Search the migrated module for `Models\\` references, add repository-focused unit tests, run the relevant HTTP characterization tests, and then run the complete test suite.
- Repository contracts belong in `Modules/<Module>/Contracts/` and expose business-oriented persistence operations, not raw SQL or HTTP concepts. Keep Commands, Queries, Handlers, and Controllers unaware of `IDatabaseConnection`; only concrete repositories may depend on it.
- Commands, Queries, Results, Requests, entities, and value objects contain operation data and must not be registered as singletons.
- Constructor parameters with scalar configuration require an explicit factory. Do not read global configuration from application Handlers when it can be supplied by the composition root.
