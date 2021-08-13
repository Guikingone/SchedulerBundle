# Lock

This bundle provides a deep integration of the [Lock component](https://symfony.com/doc/current/components/lock.html),
by default, each task receive a key once scheduled.

- [Usage](#usage)
- [Executing tasks](#executing-tasks)

## Usage

Given the following `framework.yaml` file:

```yaml
# see https://symfony.com/doc/current/reference/configuration/framework.html
framework:
    # ...

    lock:
        scheduler: 'sqlite:///%kernel.project_dir%/var/lock.db'
```

The lock store to use by the [TaskLockBagMiddleware](../src/Middleware/TaskLockBagMiddleware.php)
can be defined:

```yaml
scheduler_bundle:
  lock_store: 'lock.scheduler.store'

# ...
```

PS: As conventions can evolve, you can retrieve the store name using `bin/console debug:container store`: 

```bash
$ bin/console debug:container store

Select one of the following services to display its information:
  [0] http_cache.store
  [1] lock.store.combined.abstract
  [2] scheduler.lock_store.factory
  [3] lock.scheduler.store
  [4] Symfony\Component\Lock\PersistingStoreInterface $schedulerLockStore
  [5] lock.default.store
  [6] lock.store
  [7] Symfony\Component\Lock\PersistingStoreInterface
  
  # ...
```

### Default behavior

As explained in [the configuration](configuration.md), by default, this bundle creates a `FlockStore`,
even if it's enough in `test` or `dev` environment, this store does not support serializing the key 
(which occurs if you use external transport), consider using a persisting store in production.

## Executing tasks

By default, this bundle will lock tasks before executing them, as the default store is `FlockStore`,
tasks can be locked between processes BUT you may want to use an external lock store, as explained earlier,
you can easily define a store and set the `lock_store` to use it. 

For more information, see the [Worker](../src/Worker/AbstractWorker.php).

**Keep in mind that the store MUST support sharing the key to lock tasks between processes.**
