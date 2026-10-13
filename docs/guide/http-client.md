# HTTP client

HTTP request stubs preserve conditional response types: synchronous requests
return a response, while asynchronous requests return a lazy promise. Pool and
batch requests start asynchronously.

## Exceptions

Request methods declare `\Exception`, not only `ConnectionException`.
Connection failures throw `ConnectionException`; configured `throw()` calls and
exhausted retries can throw `RequestException`. Middleware, retry predicates,
and request or response callbacks can propagate other exceptions unchanged.

Asynchronous calls retain this declaration because request preparation, such
as converting an `Arrayable` body, runs before the promise is returned and can
throw immediately. Batch requests can also throw `BatchInProgressException`.

With strict checked-exception analysis, catching or declaring only
`ConnectionException` or `RequestException` does not cover the complete request
throw type. Catch or declare `\Exception` for the remaining exceptions.

This is a conservative exception contract, not configuration-dependent callback
inference. PHP errors such as `TypeError` can also propagate; the declaration
does not attempt to enumerate all possible `\Throwable` failures.
