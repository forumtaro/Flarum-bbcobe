<?php

namespace Olleksi\Bbcodes;

use Flarum\Extend;

return [
    // API роут для збереження BB-кодів
    (new Extend\Routes('api'))
        ->post('/olleksi-bbcodes', 'olleksi.bbcodes.save', SaveBbcodesHandler::class),

    // Підключаємо JS для форуму
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/forum.js'),

    // Підключаємо JS для адмінки
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/admin.js'),

    // Налаштування за замовчуванням
    (new Extend\Settings())
        ->default('olleksi-bbcodes.hide_bold', false)
        ->default('olleksi-bbcodes.hide_italic', false)
        ->default('olleksi-bbcodes.hide_underline', false)
        ->default('olleksi-bbcodes.hide_link', false)
        ->default('olleksi-bbcodes.hide_image', false)
        ->default('olleksi-bbcodes.hide_code', false)
        ->default('olleksi-bbcodes.hide_quote', false)
        ->default('olleksi-bbcodes.hide_strike', false)
        ->default('olleksi-bbcodes.hide_header', false)
        ->default('olleksi-bbcodes.hide_list', false)
        ->default('olleksi-bbcodes.hide_spoiler', false)
        ->default('olleksi-bbcodes.hide_mention', false)
        ->default('olleksi-bbcodes.hide_preview', false)
        ->default('olleksi-bbcodes.custom_bbcodes', '[]')
        ->serializeToForum('olleksi-bbcodes.hide_bold', 'olleksi-bbcodes.hide_bold', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_italic', 'olleksi-bbcodes.hide_italic', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_underline', 'olleksi-bbcodes.hide_underline', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_link', 'olleksi-bbcodes.hide_link', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_image', 'olleksi-bbcodes.hide_image', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_code', 'olleksi-bbcodes.hide_code', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_quote', 'olleksi-bbcodes.hide_quote', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_strike', 'olleksi-bbcodes.hide_strike', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_header', 'olleksi-bbcodes.hide_header', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_list', 'olleksi-bbcodes.hide_list', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_spoiler', 'olleksi-bbcodes.hide_spoiler', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_mention', 'olleksi-bbcodes.hide_mention', 'boolval')
        ->serializeToForum('olleksi-bbcodes.hide_preview', 'olleksi-bbcodes.hide_preview', 'boolval')
        ->serializeToForum('olleksi-bbcodes.custom_bbcodes', 'olleksi-bbcodes.custom_bbcodes'),
];
