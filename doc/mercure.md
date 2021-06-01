# Mercure

This bundle comes with a fully integrated [Mercure](https://www.mercure.rocks) support, 
this mean that you can listen and trigger actions depending on real-time update 
sent by the scheduler/worker/etc.

## Configuration

```yaml
scheduler_bundle:
    mercure:
        enabled: true
        hub_url: 'https://www.foo.com/.well-know/mercure'
        update_url: 'https://www.bar.com/scheduler'
        jwt_token: '!ChangeMe'

# ...
```
