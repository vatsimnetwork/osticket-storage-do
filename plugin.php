<?php

use Vatsim\Osticket\Spaces\Plugin;

return [
    'id' => 'storage:do',
    'version' => '1.0',
    'ost_version' => '1.17',
    'name' => 'Attachments in DigitalOcean Spaces',
    'author' => 'VATSIM Tech Team <tech@vatsim.net>',
    'description' => 'Stores attachments in DigitalOcean Spaces',
    'plugin' => 'bootstrap.php:'.Plugin::class,
];
