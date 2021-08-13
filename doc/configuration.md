# Configuration

```yaml
scheduler_bundle:
    # The path used to trigger tasks using http request, default to "/_tasks"
    path:                 /_tasks
    
    # The timezone used by the scheduler, if not defined, the default value will be "UTC"
    timezone:             UTC
    scheduler:
        # Define the scheduler mode (lazy or default)
        mode:                 default # One of "default"; "lazy"
    probe:
        enabled:              false
    
        # The path used by the probe to return the internal state
        path:                 /_probe
        clients:
    
            # Prototype
            name:
    
                # Define the path where the probe state is available
                externalProbePath:    null
    
                # Define if the probe fails when the "failedTasks" node is higher than 0
                errorOnFailedTasks:   false
    
                # Define the delay before executing the client (in milliseconds)
                delay:                0
    transport:

        # The transport DSN used by the scheduler
        dsn:                  ~

        # Configure the transport, every options handling is configured in each transport
        options:

            # The policy used to sort the tasks scheduled
            execution_mode:       first_in_first_out

            # The path used by the FilesystemTransport to store tasks
            path:                 '%kernel.project_dir%/var/tasks'
    tasks:

        # Prototype
        name:                 ~

    # The store used by every worker to prevent overlapping, by default, a FlockStore is created
    lock_store:           null

    # The limiter used to control the execution and retry of tasks, MUST be a valid limiter identifier
    rate_limiter:         null
```

Here's a breakdown of each major key:

- `path`: Define the path used to trigger tasks using an HTTP call.

- `scheduler`: Define the `mode` used by the scheduler to perform actions.

- `timezone`: Define the timezone used by the scheduler, each task can override this value.

- `lock_store`: Allows to specify a lock factory used by the [Worker](../src/Worker/Worker.php) and
  created via the key `framework.lock`, by default, the worker will create a new `LockFactory` using a `FlockStore`,
  if you need to give a custom lock store, the store "service id" is required (ex: `lock.foo.store`).

  More information can be found [here](lock.md).

- `rate_limiter`: Define the id of the rate limiter used by the worker to prevent extra execution of a task.
