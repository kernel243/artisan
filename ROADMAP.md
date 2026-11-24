# Feuille de route — kernel243/artisan

Objectif : que le paquet génère du code **conforme aux conventions des projets ELONGO CREA**. Côté backend : Actions, DTO spatie/laravel-data, FormRequest, enums, policies. Côté frontend : Inertia, Vue 3, TypeScript, Wayfinder et shadcn-vue. Le code produit doit pouvoir être commité sans retouche.

Légende : ✅ fait · 🚧 en cours · ⏳ à faire. Priorité P1 (indispensable) → P3 (confort).

## 1. Améliorations de l'existant

| # | Amélioration | Priorité | État |
|---|--------------|----------|------|
| 1.1 | Compatibilité Laravel 10 à 13 et PHP 8.2+, sans écraser `make:view` / `make:class` | P1 | ✅ (PR #4) |
| 1.2 | `--force` / `--dry-run` partout, stubs publiables, modules nwidart v10+ | P1 | ✅ (PR #4) |
| 1.3 | Publier `v1.0.0` et passer les ~10 projets de `^0.0.3` à `^1.0`, en `require-dev` | P1 | ⏳ |
| 1.4 | Config publiable `config/kernel-artisan.php` : suffixe `Action`, méthode (`handle` / `__invoke` / `execute`), clé flash, casse des dossiers de pages | P1 | ✅ |
| 1.5 | Repository : finders métier (`paginateFiltered()` avec `whereLike`, `options()` pour les selects), `forceDelete()` seulement si le modèle a `SoftDeletes` | P2 | ⏳ |
| 1.6 | Inférer les champs depuis un modèle existant (`#[Fillable]`, `$fillable`, `casts()`, `Schema::getColumns()`) pour pré-remplir DTO, requests, pages et factory | P2 | 🚧 (fillable + casts faits, schéma DB à faire) |
| 1.7 | Détecter les conventions du projet en lisant `app/Actions` (suffixe, méthode) quand la config n'est pas publiée | P2 | ⏳ |
| 1.8 | Respecter la config nwidart (`modules.paths`, `modules.namespace`) au lieu de chemins devinés | P2 | ⏳ |
| 1.9 | Mode interactif (Laravel Prompts) quand un argument manque | P3 | ⏳ |
| 1.10 | `kernel:doctor` : vérifie les paquets attendus (laravel-data, inertia, wayfinder, permission) et les stubs publiés obsolètes | P3 | ⏳ |
| 1.11 | Larastan sur le paquet, script local `composer test:matrix` (Docker, Laravel 10 → 13) | P3 | ⏳ |
| 1.12 | Marquer le CRUD Blade comme « legacy » au profit du CRUD Inertia | P3 | ⏳ |

## 2. Nouvelles commandes

| # | Commande | Génère | Priorité | État |
|---|----------|--------|----------|------|
| 2.1 | `kernel:action` | `Actions/<Domaine>/<Verbe><Modele>Action` : injection en propriété promue, `DB::transaction`, docblock français, variantes create / update / delete | P1 | ✅ |
| 2.2 | `kernel:data` | DTO spatie/laravel-data en snake_case, plus la variante `<Modele>UpdateData` en `Optional\|…` | P1 | ✅ |
| 2.3 | `kernel:request` | `Store<Modele>Request` / `Update<Modele>Request` : règles en tableau, `Rule::unique()->ignore()`, `messages()` / `attributes()` en français | P1 | ✅ |
| 2.4 | `kernel:enum` | Enum string-backed (cases TitleCase, valeurs snake_case), `label()` en français, trait `HasOptions` (`values()`, `options()`) | P1 | ✅ |
| 2.5 | `kernel:inertia-crud` | Le CRUD complet aux conventions : migration commentée, modèle (`casts()`, `SoftDeletes`, factory), DTO, requests, actions, contrôleur `Inertia::render` aux props façonnées (`through()`), routes de zone nommées, pages Vue TS (Index avec recherche différée et pagination, Form unique création / modification en `useForm` + Wayfinder), flash, tests Pest en français, formatage Pint / Prettier du projet | P1 | ✅ |
| 2.5b | `kernel:inertia-crud` (suite) | Selects pour les clés étrangères (relation `BelongsTo`, libellé dans la liste) et les enums (cast, `Rule::enum()`, `options()`, `label()`) | P2 | ✅ |
| 2.5c | `kernel:inertia-crud` (suite) | Page `Show`, `--module`, filtres par select dans la liste (`--tenant` fait avec 2.12) | P3 | ⏳ |
| 2.6 | `kernel:page` | Page Vue TS isolée (layout, breadcrumbs typés, `Head`) | P2 | ⏳ |
| 2.7 | `kernel:policy` | Policy qui vérifie la permission **et** l'appartenance au tenant (404 pour un autre tenant, échec fermé), ajout au registre de permissions (`MODULES` ou `all()`, séparateur détecté), seeder idempotent ; `kernel:inertia-crud --policy` | P2 | ✅ |
| 2.8 | `kernel:api` | Contrôleur `Api/V1` + `JsonResource` + routes `v1` avec `throttle` + tests | P2 | ⏳ |
| 2.9 | `kernel:test` | Test Pest en français pour une action ou un contrôleur (cas nominal, validation, autorisation) | P2 | ⏳ |
| 2.10 | `kernel:integration` | Pattern Manager : config `default` / `providers`, contrat, drivers, driver `log` hors production, binding (paiement, SMS, push) | P3 | ⏳ |
| 2.11 | `kernel:webhook` | Contrôleur invocable hors CSRF, signature HMAC (`hash_equals`), transaction, idempotence, canal de log dédié | P3 | ⏳ |
| 2.12 | `kernel:tenant` | `TenantManager` (tenant de l'utilisateur, `runFor()` hors requête), trait `BelongsTo<Tenant>` à global scope qui échoue fermé, colonne sur `users` ; `kernel:inertia-crud --tenant` (index uniques et règles `unique` / `exists` par tenant) | P3 | ✅ |
| 2.13 | `kernel:component` / `kernel:composable` | Composant Vue PascalCase / composable `useX.ts` | P3 | ⏳ |
