# Policies

Policies (also called `SchedulePolicies`) are the sort policies used by the transports to return tasks,
the idea is to sort tasks when retrieving the full list in order to improve performances.

The main entrypoint for using policies is the `execution_mode` option sent to every transport.

Some transports can also use these policies to sort tasks when adding new ones.

Supported policies:

- [BatchPolicy](#batchpolicy)
- [DeadlinePolicy](#deadlinepolicy)
- [ExecutionDurationPolicy](#executiondurationpolicy)
- [FirstInFirstOut](#firstinfirstout)
- [FirstInLastOut](#firstinlastout)
- [IdlePolicy](#idlepolicy)
- [MemoryUsage](#memoryusage)
- [NicePolicy](#nicepolicy)
- [RoundRobinPolicy](#deadlinepolicy)

Extending the policies:

- [Creating custom policy](#creating-custom-policy)

## BatchPolicy

BatchPolicy (which can also be named as `PriorityPolicy`) aims to sort tasks using their priorities.

Each task is sorted in an ascendant way when returned.

[Source](../src/SchedulePolicy/BatchPolicy.php)

## DeadlinePolicy

// TODO

[Source](../src/SchedulePolicy/DeadlinePolicy.php)

## ExecutionDurationPolicy

Each task is sorted depending on the task `execution duration` and in an ascendant way.

[Source](../src/SchedulePolicy/ExecutionDurationPolicy.php)

## FirstInFirstOut

Each task is sorted depending on the `scheduling date` and in an ascendant way.

[Source](../src/SchedulePolicy/FirstInFirstOutPolicy.php)

## FirstInLastOut

Each task is sorted depending on the `scheduling date` and in a descendant way.

[Source](../src/SchedulePolicy/FirstInLastOutPolicy.php)

## IdlePolicy

Each task is sorted depending on the `priority` and if this one is lower than `19` and in an ascendant way.

[Source](../src/SchedulePolicy/IdlePolicy.php)

## MemoryUsage

Each task is tracked during its execution to keep information about the execution duration and so on,
this policy use this information to sort tasks in an ascendant way, 
this policy can help when executing tasks in a "resources limited" environment.

[Source](../src/SchedulePolicy/MemoryUsagePolicy.php)

## NicePolicy

Each task is sorted depending on the `nice` value and the `priority` and in an ascendant way.

[Source](../src/SchedulePolicy/NicePolicy.php)

## Creating custom policy

Creating a new policy is as easy as it sounds, first, you must implement the [PolicyInterface](../src/SchedulePolicy/PolicyInterface.php).

**Note**: Each policy is automatically injected in the [SchedulePolicyOrchestrator](../src/SchedulePolicy/SchedulePolicyOrchestrator.php).
