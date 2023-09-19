<?php

namespace CSVDB\Query;

use CSVDB\Converter;
use CSVDB\Enums\ExportEnum;

interface Query
{
    public function get(Converter $converter = null): array;

    public function export(string $type = ExportEnum::CSV): string;
}