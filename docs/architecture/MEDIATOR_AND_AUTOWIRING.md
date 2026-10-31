# Modular features, Mediator, and autowiring

ClubCheck organizes newly migrated code as vertical features inside each module.

## Module structure

```text
app/Modules/Announcements/
├── Controllers/
│   ├── AdminAnnouncementController.php
│   └── Requests/
├── Contracts/
├── Features/
│   ├── ShowAnnouncementsPage/
│   ├── ListAnnouncements/
│   ├── SaveAnnouncement/
│   ├── GetAnnouncementViews/
│   ├── ActivateAnnouncement/
│   ├── DeleteAnnouncement/
│   └── UploadAnnouncementImage/
├── Repositories/
├── Services/
└── services.php
```

Each feature directory contains `Command.php` or `Query.php` and `Handler.php`. Feature-specific collaborators such as `ImagePolicy.php` stay beside that Handler.

PHP does not support nested classes such as C#'s `Feature.Command` and `Feature.Handler`. Keeping `Command.php` and `Handler.php` in one feature namespace provides the same grouping while preserving PSR-4 autoloading.

## Request flow

```text
HTTP request
  -> Router
  -> module Controller
  -> Request::toCommand()
  -> IMediator::send(Command)
  -> Container resolves Handler
  -> Handler::handle(Command)
  -> scalar, value object, or Result
  -> JsonResponse
```

The Mediator resolves by convention in the same namespace:

```text
Features/UploadAnnouncementImage/Command.php
                              -> Handler.php
```

The Handler and its dependencies are created only when `send()` receives that Command or Query.

## Result values

A Result class is not mandatory. `UploadAnnouncementImage/Handler` returns the public image path as a `string` because that is its only output. Use a dedicated Result DTO when an operation returns multiple named values, needs stronger semantics, or exposes a stable contract to several consumers.

## Autowiring and bindings

The Container inspects required Handler constructor parameters with Reflection. Concrete classes such as `ImagePolicy` are constructed automatically. Interfaces are selected in the module's `services.php`:

```php
$container->singleton(
    IImageMimeDetector::class,
    FileInfoImageMimeDetector::class,
);
```

Dependencies requiring scalar configuration use a factory:

```php
$container->singleton(
    IAnnouncementImageStorage::class,
    static fn () => new LocalAnnouncementImageStorage($directory, $publicPrefix),
);
```

Shared infrastructure is composed in `config/services.php`; each module owns its implementation choices in `app/Modules/<Module>/services.php`.

The announcements module binds `IAnnouncementRepository` to `AnnouncementRepository`, and `ICurrentUserProvider` to the authenticated session. The repository contains the announcement SQL and receives `IDatabaseConnection`; it does not depend on a legacy Model. Handlers therefore do not instantiate models, open database connections, or read global session state directly.

## Lifetimes

- `singleton()` returns the same instance during the current Container/request.
- `bind()` creates a new instance for each resolution.
- Unregistered concrete classes are autowired as new instances.
- Commands, Queries, Requests, entities, and value objects are created for each operation and are not Container services.

`IDatabaseConnection` is a singleton because every repository resolved during one request should share the same connection and transaction context. Repositories are transient bindings because they are stateless; they receive that shared connection through their constructor. This singleton belongs to the application Container, not to a global static service, so tests can replace it safely.

The Container rejects unresolved scalar parameters and circular dependencies with explicit errors.
