# Best practices

- [Transports](#Transports)
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

## External ressources

Consider exploring this [article](https://www.endpoint.com/blog/2008/12/08/best-practices-for-cron)
to have a better idea of general approaches related to repetitive tasks.
