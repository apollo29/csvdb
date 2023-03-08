<?php

namespace CSVDB;

interface Converter
{
    public function convert(iterable $records): array;
}