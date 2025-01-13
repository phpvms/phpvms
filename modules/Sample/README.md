# phpvms-module

Sample/template repository for a phpVMS plugin. See the full docs here: http://docs.phpvms.net/developers/add-ons-and-modules

## Generating a new module

The easiest way to generate a new module for phpVMS is to use the `artisan` command:

```
php artisan module:make {ModuleName}
```

That will create a module in the `modules` folder, which you can then copy out into its own repository and develop.

## Composer Configuration

### Type

The `type` field needs to be set to "phpvms-module", and 

```json
"type": "phpvms-module",
"require": {}
```

### Autoload

The path to your namespace must be set by the `autoload` section:

```json
"autoload": {
    "psr-4": {
        "Modules\\Sample\\": "."
    }
}
```