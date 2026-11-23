<?php

/*
 * Conventions used by the kernel:* generators.
 *
 * Publish this file (php artisan vendor:publish --tag=kernel-config) to align
 * the generated code with an existing project.
 */
return [

    'actions' => [
        // Suffix appended to action class names: CreatePostAction. Use '' for CreatePost.
        'suffix' => 'Action',

        // Method called on actions: 'handle', '__invoke' or 'execute'.
        'method' => 'handle',

        // Wrap create / update / delete actions in DB::transaction().
        'transaction' => true,
    ],

    'data' => [
        // Generate actions and requests around spatie/laravel-data DTOs when the package is installed.
        'enabled' => true,
    ],

    'requests' => [
        // true: authorization is done by a policy / middleware in the controller.
        // false: generate $this->user()?->can('<module>.<action>') checks.
        'authorize_in_controller' => true,
    ],

    'permissions' => [
        // Between module and ability: 'blog-posts.update'. null: ':' when App\Support\PermissionRegistry
        // already uses "module:ability", '.' otherwise.
        'separator' => null,
    ],

    'inertia' => [
        // Default area of kernel:inertia-crud (e.g. 'admin'): Http/Controllers/Admin, pages admin/..., routes admin.*.
        'zone' => null,

        // Case of the folders of resources/js/pages: 'kebab', 'studly', or null to detect it (Pages/ means studly).
        'pages_case' => null,

        // 'options': breadcrumbs through defineOptions (layout assigned in app.ts, recent starter kits).
        // 'component': the page is wrapped in <AppLayout :breadcrumbs> (older starter kits).
        'layout' => 'options',

        // Flash key shared by HandleInertiaRequests: 'success', or 'toast' for ['type' => 'success', 'message' => ...].
        'flash' => 'success',

        'per_page' => 20,

        // Generate the policy and authorize every action by default (--policy). Needs spatie/laravel-permission.
        'policy' => false,
    ],

];
