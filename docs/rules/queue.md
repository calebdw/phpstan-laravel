# Queue rules

Opt-in checks for queued jobs. Each one is off by default because it
enforces a style of writing jobs rather than catching a type error.

Enable only the ones you want:

```neon
parameters:
    laravel:
        rules:
            uniqueJobUniqueFor: true
            uniqueJobUniqueId: true
            batchedUniqueJob: true
            jobSerializesModels: true
            batchedJobIsBatchable: true
            batchableJobChecksCancellation: true
            dispatchInTransactionAfterCommit: true
```

## Unique job uniqueFor

`laravel.uniqueJob.missingUniqueFor` &middot; option `rules.uniqueJobUniqueFor` &middot; off by default

A `ShouldBeUnique` job must declare `uniqueFor`, as a property or a method.
Without it Laravel holds the uniqueness lock until the job finishes, so a
worker that dies mid-job never releases the lock.

### Examples

```php
class FetchSocialAvatar implements ShouldQueue, ShouldBeUnique
{
    public function handle(): void {}
}
```

Will result in the following error:

```
Job App\Jobs\FetchSocialAvatar implements ShouldBeUnique but does not declare uniqueFor.
💡 Declare a $uniqueFor property or a uniqueFor() method.
```

## Unique job uniqueId

`laravel.uniqueJob.missingUniqueId` &middot; option `rules.uniqueJobUniqueId` &middot; off by default

A parameterized `ShouldBeUnique` job must declare `uniqueId`. Laravel keys
the lock as class + uniqueId and falls back to an empty uniqueId, so every
dispatch of a parameterized job shares one lock unless you scope it.

A job with no constructor parameters is left alone: the class-wide lock is
correct.

### Examples

```php
class SyncCompany implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;

    public function __construct(public int $companyId) {}
}
```

Will result in the following error:

```
Unique job App\Jobs\SyncCompany has constructor parameters but does not declare uniqueId.
💡 Declare a uniqueId() method or a $uniqueId property to identify distinct jobs.
```

## Batched unique job

`laravel.uniqueJob.batched` &middot; option `rules.batchedUniqueJob` &middot; off by default

A `ShouldBeUnique` job must not be dispatched through `Bus::batch()`,
`Bus::bulk()`, or `Queue::bulk()`. Bulk dispatch skips the uniqueness lock;
batching a unique job can leave the batch hanging when a duplicate is
dropped.

### Examples

```php
Bus::batch([
    new SyncCompany($companyId),
]);
```

Will result in the following error:

```
Unique job App\Jobs\SyncCompany is dispatched via batch().
💡 Dispatch unique jobs individually to preserve uniqueness.
```

## Job SerializesModels

`laravel.job.missingSerializesModels` &middot; option `rules.jobSerializesModels` &middot; off by default

A queued job with a public Eloquent model property must use
`SerializesModels`. Without it the model is serialized whole onto the queue
and rehydrated from a stale dispatch-time snapshot.

Only public properties are checked, including inherited and nullable model
types. Abstract classes are skipped.

### Examples

```php
class SendInvoice implements ShouldQueue
{
    public function __construct(public Invoice $invoice) {}
}
```

Will result in the following error:

```
Job App\Jobs\SendInvoice has model properties ($invoice) but does not use SerializesModels.
💡 Use the Illuminate\Queue\SerializesModels trait.
```

## Batched job is Batchable

`laravel.batchedJob.missingBatchable` &middot; option `rules.batchedJobIsBatchable` &middot; off by default

Every queued job passed to `Bus::batch()` must use the `Batchable` trait,
including jobs nested in chains. Only array literals are inspected.

### Examples

```php
Bus::batch([
    new RegularJob(),
]);
```

Will result in the following error:

```
Job App\Jobs\RegularJob is batched but does not use Batchable.
💡 Use the Illuminate\Bus\Batchable trait.
```

## Batchable job checks cancellation

`laravel.batchableJob.missingCancellationCheck` &middot; option `rules.batchableJobChecksCancellation` &middot; off by default

A queued job that uses `Batchable` must check `$this->batch()?->cancelled()`
or register the `SkipIfBatchCancelled` middleware. Cancelling a batch only
stops future jobs; jobs already on the queue still run unless they check.

Each concrete job is checked using its effective methods, including methods
inherited from parents and traits. Overriding a guarded method requires the
subclass to provide its own check.

### Examples

```php
class GenerateReport implements ShouldQueue
{
    use Batchable;

    public function handle(): void {}
}
```

Will result in the following error:

```
Batchable job App\Jobs\GenerateReport does not check for batch cancellation.
💡 Check $this->batch()?->cancelled() or use the SkipIfBatchCancelled middleware.
```

## Dispatch in transaction afterCommit

`laravel.dispatchInTransaction.missingAfterCommit` &middot; option `rules.dispatchInTransactionAfterCommit` &middot; off by default

A queued job dispatched inside `DB::transaction(...)` must defer until
commit, by chaining `afterCommit()`, declaring `$afterCommit = true`, or
implementing `ShouldQueueAfterCommit`. The last `afterCommit()` or
`beforeCommit()` call in a dispatch chain takes precedence over the property
default.

If the job's queue connection has `after_commit` set in
`config/queue.php`, Laravel already defers every job on that connection and
the rule stays quiet. `onConnection()` and a job `$connection` property are
honoured when they are constant.

Only the `DB::transaction(Closure)` form is inspected. Synchronous dispatch
(`dispatchSync`) and the Bus/Queue facades are left alone.

### Examples

```php
DB::transaction(function () use ($product) {
    NotifyOwner::dispatch($product->id);
});
```

Will result in the following error:

```
Job App\Jobs\NotifyOwner is dispatched inside a transaction without deferring until commit.
💡 Call afterCommit() on the dispatch or implement ShouldQueueAfterCommit.
```
