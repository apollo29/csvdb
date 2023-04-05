<?php

namespace CSVDB\Helpers;


use CSVDB\enums\DatatypeEnum;

trait DatatypeTrait
{

    /**
     * Data Types
     * Data types of CSV data-columns, keyed by the column name. Possible values
     * are string, float, integer, boolean, date. See DatatypeEnum.
     *
     * @var array
     */
    public array $data_types = [];

    /**
     * Check data type for one column.
     * Check for most commonly data type for one column.
     *
     * @param array $data_types
     *
     * @return string|false
     */
    private function getMostFrequentDatatypeForColumn(array $data_types)
    {
        // remove 'false' from array (can happen if CSV cell is empty)
        $types_filtered = array_filter($data_types);

        if (empty($types_filtered)) {
            return false;
        }

        $types_freq = array_count_values($types_filtered);
        arsort($types_freq);
        reset($types_freq);

        return key($types_freq);
    }

    /**
     * Check data type foreach Column
     * Check data type for each column and returns the most commonly.
     *
     * Requires PHP >= 5.5
     *
     * @return array|bool
     * @uses   DatatypeEnum::getValidTypeFromSample
     *
     */
    public function getDatatypes()
    {
        $data = $this->select()->get();
        if (count($data) === 0) {
            throw new \UnexpectedValueException('No data set yet.');
        }

        $result = [];
        foreach ($this->headers() as $cName) {
            $column = array_column($data, $cName);
            $cDatatypes = array_map(DatatypeEnum::class . '::getValidTypeFromSample', $column);

            $result[$cName] = $this->getMostFrequentDatatypeForColumn($cDatatypes);
        }

        $this->data_types = $result;

        return !empty($this->data_types) ? $this->data_types : [];
    }

    /**
     * Check data type of titles / first row for auto detecting if this could be
     * a heading line.
     *
     * Requires PHP >= 5.5
     *
     * @return bool
     * @uses   DatatypeEnum::getValidTypeFromSample
     *
     */
    public function autoDetectFileHasHeading(): bool
    {
        if (empty($this->data)) {
            throw new \UnexpectedValueException('No data set yet.');
        }

        if ($this->config->headers) {
            $first_row = $this->headers();
        } else {
            $first_row = $this->data[0];
        }

        $first_row = array_filter($first_row);
        if (empty($first_row)) {
            return false;
        }

        $first_row_datatype = array_map(DatatypeEnum::class . '::getValidTypeFromSample', $first_row);

        return $this->getMostFrequentDatatypeForColumn($first_row_datatype) === DatatypeEnum::TYPE_STRING;
    }
}
