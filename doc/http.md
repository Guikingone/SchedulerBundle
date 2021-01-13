# HTTP

This bundle provides an HTTP entrypoint that allows you to consume tasks using an HTTP request.

**Note: The security of this entrypoint is not handled via the bundle.**

## Configuration

The path used to trigger tasks can be configured (default to `/_tasks`):

```yaml
# config/packages/scheduler.yaml

scheduler_bundle:
    path: /_foo
# ...
```

## Usage

The path is available at `domain/path` and accept the following situations:

- The request must be made via `GET` HTTP method, the `expression` or `name` argument is required

```bash
www.domain.com/**_path_**?name=foo
```

OR 

```bash
www.domain.com/**_path_**?expression=* * * * *
```

By default, the worker will consume the exact amount of tasks "due" at the precise moment of the request.

Once executed, a `200` HTTP status code response is returned with the executed tasks in the body.

_Note: Both arguments can be passed if required_.
