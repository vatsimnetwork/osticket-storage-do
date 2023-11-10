<?php

namespace Vatsim\Osticket\Spaces;

use FileStorageBackend;
use Plugin as BasePlugin;

class Plugin extends BasePlugin
{
    public $config_class = Config::class;

    public function isMultiInstance(): bool
    {
        return false;
    }

    public function bootstrap(): void
    {
        StorageBackend::setConfig($this->getConfig());
        FileStorageBackend::register('S', StorageBackend::class);
    }
}
