# Probe

This bundle provides a probe that helps to check the current tasks state
and fetch external applications state.

- [Returning current state](#returning-current-state)
- [Fetching external application state](#fetching-external-state)
- [DataCollector](#datacollector-integration)

## Returning current state

First, the probe must be enabled and optionally a path defined (default to `/_probe`):

```yaml
scheduler_bundle:
    probe:
        enabled: true
        path: '/_probe'
```

Once done, the current state is displayed as following when sending a `GET` request to the specified path:

```json
{
  "executedTasks": 5,
  "failedTasks": 0,
  "scheduledTasks": 1
}
```

## Fetching external state

Let's imagine that you use this bundle on multiple applications. Sometimes 
you may need to control the state of these applications. To do so, you can define 
a list of clients that will fetch these states:

```yaml
scheduler_bundle:
    probe:
        enabled: true
        path: '/_probe'
        clients:
            foo:
                externalProbePath: 'https://www.foo.com/_external_probe_path'
                errorOnFailedTasks: true # Define if the probe must fail when `failedTasks` is not equal to 0
                delay: 10 # Define a delay before sending the request (in milliseconds)
            bar:
                externalProbePath: 'https://www.bar.com/_second_external_probe_path'
                errorOnFailedTasks: false # Default value
```

By default, the bundle will define a `ProbeTask` that will be executed every minute.
As this task is not executed in background, you must use the `scheduler:consume` command
to launch the probe clients.

**_Note: By default, the runner will return the task as failed if the response returns a 3/4/5xx status code._**

**_Notice: As each client is transformed into a `ProbeTask`, the name of the client is used as the task name, 
to prevent any error when merging both probe clients and default tasks, using a unique name is highly recommended._**

## DataCollector integration

// TODO
