# Execution policies

Execution policies are the policies used by the worker to execute tasks.

// TODO

Supported policies:

- [DefaultPolicy](#defaultpolicy)
- [FiberPolicy](#fiberpolicy)

Extending the policies:

- [Creating custom policy](#creating-a-custom-execution-policy)

## DefaultPolicy

// TODO

## FiberPolicy

// TODO

## Creating a custom execution policy

Creating a new policy is as easy as it sounds, you only need to implement the [ExecutionPolicyInterface](../src/Worker/ExecutionPolicy/ExecutionPolicyInterface.php).

**Note**: Each policy is automatically injected in the [ExecutionPolicyRegistry](../src/Worker/ExecutionPolicy/ExecutionPolicyRegistry.php).
