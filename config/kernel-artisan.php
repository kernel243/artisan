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

];
