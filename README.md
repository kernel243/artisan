# Kernel243/Artisan

[![Packagist Downloads](https://img.shields.io/packagist/dt/kernel243/artisan.svg?style=for-the-badge)](https://packagist.org/packages/kernel243/artisan)
[![Visits](https://badges.pufler.dev/visits/kernel243/artisan?style=for-the-badge)](https://packagist.org/packages/kernel243/artisan)

This package provides a set of code generators (artisan commands) for Laravel: repositories, services, API resources, controllers, Blade views, translation files, classes and complete CRUDs, with support for [`nwidart/laravel-modules`](https://github.com/nWidart/laravel-modules).

It is based on `dannyvilla/artisan-commands` and adds options such as the modular architecture.

Visit the developer website: [developper.elongocrea.com](https://developper.elongocrea.com)

## Requirements

| Package | PHP  | Laravel                 |
|---------|------|-------------------------|
| 1.x     | 8.2+ | 10, 11, 12, 13          |
| 0.0.x   | 7.4+ | 5.8 → 10 (unmaintained) |

## Installation

```bash
composer require kernel243/artisan --dev
```

The service provider is auto-discovered.

## Commands

All commands are prefixed with `kernel:`. The former `make:*` names are kept as aliases when Laravel does not provide a command with the same name.

| Command                | Alias                | Generates                                              |
|------------------------|----------------------|--------------------------------------------------------|
| `kernel:repository`    | `make:repository`    | A repository class, optionally based on a model        |
| `kernel:service`       | `make:service`       | A service class                                        |
| `kernel:resource`      | –                    | An API resource (`JsonResource`), also inside a module |
| `kernel:controller`    | –                    | A controller, or an API controller from a model        |
| `kernel:view`          | –                    | A Blade view, optionally extending a layout            |
| `kernel:class`         | –                    | A class, trait, interface or enum                      |
| `kernel:lang`          | `make:lang`          | A PHP or JSON translation file                         |
| `kernel:file`          | `make:file`          | An empty file                                          |
| `kernel:crud`          | `make:crud`          | A Blade CRUD                                           |
| `kernel:resource-crud` | `make:resource-crud` | A CRUD driven by a Resource class                      |

Every generator accepts:

- `--force`: overwrite existing files without asking;
- `--dry-run`: print the generated files without writing anything.

Without `--force`, an existing file is never overwritten silently: the command asks first, and skips the file when running non-interactively.

### Repository

```bash
php artisan kernel:repository UserRepository
php artisan kernel:repository Admin/UserRepository            # app/Repositories/Admin/UserRepository.php
php artisan kernel:repository PostRepository --model=Post     # based on App\Models\Post
php artisan kernel:repository CountryRepository --model=Country --module=Setting
```

### Service

```bash
php artisan kernel:service PayPalPaymentService
php artisan kernel:service PayPalPaymentService --module=Payment
```

### API resource

```bash
php artisan kernel:resource PostResource
php artisan kernel:resource PostResource --module=Blog
```

### Controller

```bash
php artisan kernel:controller HomeController
php artisan kernel:controller PostController --model=Post
```

With `--model`, the controller is an API controller (`index`, `store`, `show`, `update`, `destroy`) backed by `PostRepository` and `PostResource`. These two classes are created when they are missing.

### View

```bash
php artisan kernel:view admin.users.index
php artisan kernel:view admin.users.index --layout=layouts.app
php artisan kernel:view posts.index --module=Blog
```

### Class, trait, interface, enum

```bash
php artisan kernel:class App/Handlers/UserHandler           # app/Handlers/UserHandler.php
php artisan kernel:class App.Handlers.UserHandler --separator=.
php artisan kernel:class App/Concerns/HasUuid --kind=trait
php artisan kernel:class App/Contracts/Payable --kind=interface
php artisan kernel:class App/Enums/Status --kind=enum
```

### Translation files

```bash
php artisan kernel:lang messages --locale=fr   # lang/fr/messages.php
php artisan kernel:lang --locale=es --json     # lang/es.json
```

### Generic file

```bash
php artisan kernel:file docs.api.notes --ext=md   # docs/api/notes.md
```

### CRUD

Generates the model, repository, service, controller, Blade views (`index`, `create`, `edit`, `show`), a layout when the application has none, and a `Route::resource()` line in `routes/web.php`:

```bash
php artisan kernel:crud Product --fields="title:string,description:text,price:decimal,is_active:boolean"
```

The views use Tailwind CSS classes. Pass `--tailwind-config` to also create a `tailwind.config.js` (Tailwind CSS v3 only: v4 does not use it).

### Resource CRUD

Generates a CRUD driven by a Resource class (form fields, table columns and validation rules, Filament-inspired), with generic views shared by every resource:

```bash
php artisan kernel:resource-crud Product --fields="title:string,description:text"
```

## Modules

With `--module=Name`, files are created inside `Modules/Name`. Both module layouts are supported:

| nwidart/laravel-modules | Classes                       | Models                     | Views                           |
|-------------------------|-------------------------------|----------------------------|---------------------------------|
| 10+                     | `Modules/Name/app/...`        | `Modules\Name\Models`      | `Modules/Name/resources/views`  |
| < 10                    | `Modules/Name/...`            | `Modules\Name\Entities`    | `Modules/Name/Resources/views`  |

## Customizing the stubs

```bash
php artisan vendor:publish --tag=kernel-stubs
```

The stubs are copied to `stubs/kernel/`. A published stub takes precedence over the package one.

## Upgrading from 0.0.x

- `make:view` and `make:class` are now `kernel:view` and `kernel:class`: Laravel 11+ provides its own `make:view` and `make:class`, which this package was overriding.
- `kernel:controller` no longer takes the broken `-r` / `-R` arguments: `--model` generates the repository and the resource when they are missing.
- Translation files follow `lang_path()`: `lang/` on recent applications, `resources/lang` when that folder exists.
- `tailwind.config.js` is only created with `--tailwind-config`.

## Testing

```bash
composer test         # Pest
composer lint         # Pint
composer ci:check     # Pint (check) + Pest
```

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](https://choosealicense.com/licenses/mit/)
