<?php

namespace CSVDB\Cache;

use League\Csv\Exception;
use League\Csv\InvalidArgument;

trait CacheTrait
{

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function setup_cache(): void
    {
        if ($this->config->cache) {
            $this->cache();
        }
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function cache(): void
    {
        $this->cache = $this->reader(true);
    }

}