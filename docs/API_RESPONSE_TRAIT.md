# API Response Trait (`App\Traits\ApiResponseTrait`)

## What it does

`ApiResponseTrait` standardizes JSON responses across most API controllers.

Primary shapes:

```json
{
  "status": "success",
  "message": "...",
  "data": {}
}
```

```json
{
  "status": "error",
  "message": "...",
  "errors": {}
}
```

Paginated shape:

```json
{
  "status": "success",
  "message": "...",
  "data": [],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0,
    "from": null,
    "to": null,
    "has_more_pages": false
  }
}
```

## Available methods

- `successResponse($data = null, ?string $message = null, int $code = 200)`
- `errorResponse(string $message, int $code = 400, $errors = null)`
- `validationErrorResponse($errors, string $message = 'Validation failed')` (422)
- `notFoundResponse(string $message = 'Resource not found')` (404)
- `unauthorizedResponse(string $message = 'Unauthorized')` (401)
- `forbiddenResponse(string $message = 'Forbidden')` (403)
- `createdResponse($data = null, ?string $message = 'Resource created successfully')` (201)
- `noContentResponse(?string $message = 'Operation completed successfully')` (204)
- `paginatedResponse($data, ?string $message = null)`

## Pagination input types

`paginatedResponse` accepts:

- `LengthAwarePaginator`
- `ResourceCollection` wrapping a `LengthAwarePaginator`

If it receives anything else, it throws `InvalidArgumentException`.

## Usage examples

```php
use App\Traits\ApiResponseTrait;

class ExampleController extends Controller
{
    use ApiResponseTrait;

    public function show(): \Illuminate\Http\JsonResponse
    {
        return $this->successResponse(['ok' => true], 'Fetched successfully');
    }

    public function store(): \Illuminate\Http\JsonResponse
    {
        return $this->createdResponse(['id' => 1], 'Created successfully');
    }
}
```

## Important project note

Not every controller method currently uses this trait. Some endpoints still return raw `response()->json(...)` payloads, so response shape is not globally uniform yet.

## Suggested best practice

For new API methods, prefer trait responses to keep frontend contracts consistent.
