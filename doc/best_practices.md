# Best practices

- [Transports](#Transports)
- [Using the probe](#using-the-probe)
- [Lazy loading](#using-lazy-loading)
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

// TODO

## Using lazy loading

When possible, try to use the [LazyScheduler](lazy_loading.md#lazyscheduler) when performing actions
that does not require to have a fully initialized scheduler.

When fetching tasks and/or tasks list, using the `$lazy` argument can help delay the actions until 
the extreme end, even if the performances are not impacted and pushed to the extreme in this bundle,
delaying heavy operations can help improve DX and final UX, do not hesitate to use lazy loading.

## External ressources

Consider exploring this [article](https://www.endpoint.com/blog/2008/12/08/best-practices-for-cron)
to have a better idea of general approaches related to repetitive tasks.
