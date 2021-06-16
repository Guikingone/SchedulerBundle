# Configuration storage

- [InMemoryConfiguration](#inmemoryconfiguration)
- [FilesystemConfiguration](#filesystemconfiguration)
- [LazyConfiguration](#lazyconfiguration)

## InMemoryConfiguration

The [InMemoryConfiguration](../src/Transport/Configuration/InMemoryConfiguration.php) stores every configuration key 
in memory, this configuration is perfect for `test` environnement or/and for POC applications.

### Usage

```yaml
scheduler_bundle:
    configuration:
        dsn: 'configuration://memory'

# ...
```

## FilesystemConfiguration

The [FilesystemConfiguration](../src/Transport/Configuration/FilesystemConfiguration.php) 
stores every configuration key in the filesystem.

### Usage

```yaml
scheduler_bundle:
    configuration:
        dsn: 'configuration://fs' # Or 'configuration://filesystem'

# ...
```

This configuration accept 3 options:

- `file_extension`: The extension of the file that contains the configuration keys/values, default to `json`.
- `filename_mask`: The "mask" used to create the file, default to `%s/_symfony_scheduler_/configuration`.
- `path`: The path used to store the configuration file, default to `sys_get_temp_dir`.

### Example

```yaml
scheduler_bundle:
    configuration:
        dsn: 'configuration://fs?file_extension=xml'

# ...
```

**PS: This configuration does not support resolving container parameters (ex: `kernel.project_dir`).** 

## LazyConfiguration

The [LazyConfiguration](../src/Transport/Configuration/LazyConfiguration.php) act as a wrapper around
a configuration, this configuration allows to delay the retrieving of keys and values.

### Usage

```yaml
scheduler_bundle:
    configuration:
        mode: 'lazy'
        dsn: 'configuration://memory'

# ...
```

**The activation of this configuration is done via the `mode` key and requires that a configuration is defined.**
