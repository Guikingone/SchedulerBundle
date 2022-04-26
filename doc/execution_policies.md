# Execution policies

Execution policies are the policies used by the worker to execute tasks.

The execution policy is configured via the `worker`:

```yaml
scheduler_bundle:
    # ...

    worker:
        mode: 'default'
```

Supported policies:

- [DefaultPolicy](#defaultpolicy)
- [FiberPolicy](#fiberpolicy)

Extending the policies:

- [Creating custom policy](#creating-a-custom-execution-policy)

## DefaultPolicy

The [DefaultPolicy](../src/Worker/ExecutionPolicy/DefaultPolicy.php) is the default policy used by the worker.

It executes the tasks one by one in the order they are received.

## FiberPolicy

_Requires PHP `>= 8.1`_

The [FiberPolicy](../src/Worker/ExecutionPolicy/FiberPolicy.php) uses [Fibers](https://www.php.net/manual/en/language.fibers.php) to execute tasks,
a fiber is created for each task.

It executes the tasks one by one in the order they are received.

## Creating a custom execution policy

Creating a new policy is as easy as it sounds, you only need to implement the [ExecutionPolicyInterface](../src/Worker/ExecutionPolicy/ExecutionPolicyInterface.php).

**Note**: Each policy is automatically injected in the [ExecutionPolicyRegistry](../src/Worker/ExecutionPolicy/ExecutionPolicyRegistry.php).
