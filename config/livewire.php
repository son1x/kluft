<?php

/*
| Nur abweichende Werte; der Rest kommt aus vendor/livewire/livewire/config.
| Zwischenspeicher für Uploads: 50 MB statt 12 MB (Georgsbote-PDFs). Die
| PHP-Grenzen des Servers (upload_max_filesize, post_max_size) müssen das
| ebenfalls erlauben, sonst bricht der Upload ab.
*/
return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:51200'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
