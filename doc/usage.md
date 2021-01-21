# Usage

- [Cron](usage.md#Cron)
- [SymfonyCloud](usage.md#SymfonyCloud)

## Cron

```bash
* * * * * cd /path-to-your-project && php bin/console scheduler:consume >> /dev/null 2>&1
```

## SymfonyCloud

### Configuration

```yaml
cron:
    consume_tasks:
        spec: * * * * *
        cmd: bin/console scheduler:consume
```

### Manual usage

```bash
symfony cron consume_tasks
```

### Workers

```yaml
workers:
    tasks:
        commands:
            start: symfony console scheduler:consume --wait
```
