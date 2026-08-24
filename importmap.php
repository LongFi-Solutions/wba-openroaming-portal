<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    'tw-elements' => [
        'path' => './assets/lib/tw-elements.umd.min.js',
    ],
    'tw-elements/css/tw-elements.min.css' => [
        'path' => './assets/lib/tw-elements.min.css',
        'type' => 'css',
    ],
    '@symfony/ux-leaflet-map' => [
        'path' => './vendor/symfony/ux-leaflet-map/assets/dist/map_controller.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'tom-select/dist/css/tom-select.default.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'tom-select' => [
        'version' => '2.6.2',
    ],
    '@orchidjs/sifter' => [
        'version' => '1.1.0',
    ],
    '@orchidjs/unicode-variants' => [
        'version' => '1.1.2',
    ],
    'tom-select/dist/css/tom-select.default.min.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'tom-select/dist/css/tom-select.bootstrap4.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'tom-select/dist/css/tom-select.bootstrap5.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '4.5.1',
    ],
    'lodash-es' => [
        'version' => '4.18.1',
    ],
    'parchment' => [
        'version' => '3.0.0',
    ],
    'eventemitter3' => [
        'version' => '5.0.4',
    ],
    'fast-diff' => [
        'version' => '1.3.0',
    ],
    'lodash.clonedeep' => [
        'version' => '4.5.0',
    ],
    'lodash.isequal' => [
        'version' => '4.5.0',
    ],
    'quill/dist/quill.snow.css' => [
        'version' => '2.0.3',
        'type' => 'css',
    ],
    'quill/dist/quill.bubble.css' => [
        'version' => '2.0.3',
        'type' => 'css',
    ],
    'quill' => [
        'version' => '2.0.3',
    ],
    'quill-delta' => [
        'version' => '5.1.0',
    ],
    'quill-table-better' => [
        'version' => '1.2.3',
    ],
    'quill-table-better/dist/quill-table-better.css' => [
        'version' => '1.2.3',
        'type' => 'css',
    ],
    'axios' => [
        'version' => '1.19.0',
    ],
    'quill2-emoji' => [
        'version' => '0.1.2',
    ],
    'quill2-emoji/dist/style.css' => [
        'version' => '0.1.2',
        'type' => 'css',
    ],
    'quill-resize-image' => [
        'version' => '1.0.11',
    ],
    'quill-toggle-fullscreen-button' => [
        'version' => '0.2.0',
    ],
    'quill-html-edit-button' => [
        'version' => '3.0.0',
    ],
    '@kurkle/color' => [
        'version' => '0.4.0',
    ],
    'leaflet' => [
        'version' => '1.9.4',
    ],
    'leaflet/dist/leaflet.min.css' => [
        'version' => '1.9.4',
        'type' => 'css',
    ],
];
