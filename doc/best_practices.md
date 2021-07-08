# Best practices

- [Transports](#Transports)
- [Using the probe](#using-the-probe)
- [Lazy loading](#using-lazy-loading)
- [Lock store](#external-lock-store)
- [External ressources](#external-ressources)

## Transports

When using transports that rely on network calls, you must keep in mind that these transports
can fail to retrieve/create and so on, it could be a good idea to use the fail over or round robin
transport to prevent any errors:

```yaml
scheduler_bundle:
    transport:
        dsn: 'failover://(doctrine://... || fs://last_in_first_out)'
```

## Using the probe

_Introduced in `0.5`_

Ensuring that tasks are scheduled, executed and so on can be hard, 
thanks to the [Probe](probe.md), you can ease this verification phase and 
trigger actions if something goes wrong.

The probe can also define [External probes](probe.md#fetching-external-state) that
will fetch and retrieve others projects (or the main one!) probe state.

Combining the probe with an up-to-date task consumption and adapted error handling strategy
is a key to succeed using this bundle.

## Using lazy loading

When possible, try to use the [LazyScheduler](lazy_loading.md#lazyscheduler) when performing actions
that does not require to have a fully initialized scheduler.

When fetching tasks and/or tasks list, using the `$lazy` argument can help delay the actions until 
the extreme end, even if the performances are not impacted and pushed to the extreme in this bundle,
delaying heavy operations can help improve DX and final UX, do not hesitate to use lazy loading.

## External lock store

This bundle relies on lock store via the worker to execute tasks without overlapping,
by default (and if no store is specified), a `FlockStore` is used.

Even if this approach still valid in most of the cases, we highly recommend using
an external store (like Redis, PDO, etc) to improve performances, lock access 
and ease the debug phase.

## External ressources

Consider exploring this [article](https://www.endpoint.com/blog/2008/12/08/best-practices-for-cron)
to have a better idea of general approaches related to repetitive tasks.
