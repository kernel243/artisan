# Changelog

## [1.0.0] - Unreleased

### Added
- `kernel:action`, `kernel:data`, `kernel:request` and `kernel:enum` generators.
- `kernel:inertia-crud`: Inertia + Vue TypeScript CRUD (migration, model, factory, DTO, requests, actions, controller, routes, Wayfinder pages, Pest tests), formatted with the project's Pint and Prettier; `--policy` authorizes every action.
- `kernel:policy`: permission per ability, optional tenant check (404 for another tenant), permissions added to `App\Support\PermissionRegistry` (created with its seeder when missing).
- Publishable configuration `config/kernel-artisan.php` (`vendor:publish --tag=kernel-config`).
- Richer `--fields` syntax (`nullable`, `unique`, `?`) and field inference from an existing model.
- Support for Laravel 10, 11, 12 and 13 (PHP 8.2+), tested on Laravel 10.50, 11.57, 12.73 and 13.43.
- `--force` and `--dry-run` options on every generator.
- `kernel:class --kind=enum`.
- Support for the nwidart/laravel-modules 10+ layout (`Modules/Name/app`, `Models`, `resources/views`).
- Publishable stubs (`vendor:publish --tag=kernel-stubs`, overrides in `stubs/kernel/`).
- `kernel:crud --tailwind-config` / `kernel:resource-crud --tailwind-config`.
- Pest test suite and Pint.

### Changed
- Every command is prefixed with `kernel:`; `make:repository`, `make:service`, `make:lang`, `make:file`, `make:crud` and `make:resource-crud` remain as aliases.
- `make:view` → `kernel:view` and `make:class` → `kernel:class`, so Laravel's native `make:view` / `make:class` are no longer overridden.
- `kernel:controller --model` generates the missing repository and API resource.
- The package now depends on `illuminate/*` instead of `laravel/framework`.
- Generated repositories use typed signatures and `newQuery()`; `update()` respects `$fillable`.
- Translation files follow `lang_path()`.

### Fixed
- DTO dates are typed `string` (laravel-data rejected form dates such as `2026-11-23T10:00`), nullable properties use `?type`.
- `Update<Model>Request` ignores the record of a multi-word model (`$this->route('blog_post')`).
- `kernel:controller` required two bogus `-r` / `-R` arguments and never wrote the controller.
- The controller stub used a hard-coded `$this->countryRepository`.
- `make:crud` / `make:resource-crud` failed because stubs were looked up in sub-folders.
- CRUD placeholders were partially replaced (`DummyResourceKebab` → `PostsKebab`, `namespace DummyNamespace;` left in repositories, `use App\Post`).
- Invalid Blade in the generic resource views (`{{ route('{{ $routePrefix }}.index') }}`).
- Edit forms produced `\$post->title`, which broke the views.
- The generated `datatable` route pointed to a missing method and was shadowed by `show`.
- `kernel:class` built paths with backslashes on Linux.
- Module detection depended on the current working directory.
- Nested folders were not created for views and resources.

### Removed
- The GitHub Actions workflow (`php.yml`): checks are run locally.

## [0.0.3] - 2023-06-26
