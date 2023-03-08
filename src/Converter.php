<?php

namespace CSVDB;

interface Converter
{
    public function convert($records): array;
}