<?php

namespace CSVDB\Builder;

use CSVDB\Converter;
use CSVDB\CSVDB;
use CSVDB\Enums\ExportEnum;

interface Statement
{
    public function count(): Statement;

    public function where(array $where = array(), string $operator = CSVDB::AND): Statement;

    public function orderBy($orderVal = array()): Statement;

    public function limit(int $limit = 0, int $offset = 0): Statement;

    public function get(Converter $converter = null): array;

    public function export(string $type = ExportEnum::CSV): string;
}