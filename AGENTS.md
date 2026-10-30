# ClubCheck development rules

## JSON responses from controllers

- New or migrated controller actions must send JSON through `App\Http\JsonResponse::send()`.
- Do not duplicate `http_response_code()`, the JSON `Content-Type` header, `json_encode()`, `echo`, or `exit` inside controllers.
- Do not extend `Core\Controller` only to reuse its `json()` method. A controller should inherit from it only when it actually needs the other legacy controller behavior.
- Keep business rules out of `JsonResponse`; it is only an HTTP presentation utility.
- Controlled API failures must use `App\Exceptions\ApiException` with a case from `App\Exceptions\ApiErrorCode`; do not add literal `error_code` strings for new exceptions.
- Add new error cases to `ApiErrorCode` with their HTTP status and safe public message before using them.
- Preserve the existing response status, keys, values, and body when migrating an endpoint.
- After changing a JSON endpoint, run its relevant unit and HTTP characterization tests. Run the complete test suite before completing a migration.
