<?php

namespace CSVDB\Query;

use CSVDB\Converter;
use CSVDB\Converter\CSVConverter;
use CSVDB\Converter\SQLConverter;
use CSVDB\CSVDB;
use CSVDB\Enums\ExportEnum;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\UnableToProcessCsv;
use PHPSQLParser\PHPSQLParser;

class QueryBuilder implements Query
{

    private string $query;
    private CSVDB $csvdb;

    public function __construct(string $query, CSVDB $csvdb)
    {
        $this->query = $query;
        $this->csvdb = $csvdb;
    }

    public array $keywords = [
        "INSERT", "SELECT", "UPDATE", "DELETE", "FROM", "COUNT", "INTO", "VALUES", "SET", "WHERE", "ORDER", "LIKE", "LIMIT", "ASC", "DESC" //, "NOT", "NULL"
    ];

    public array $expr_types = [
        "table", "expression", "colref", "operator", "const", "column-list", "record", "in-list"
    ];

    public array $operators = [
        "=", "!=", "<>", "like", "and", "or", "in"
    ];

    private ?PHPSQLParser $parser = null;

    private function parser(): PHPSQLParser
    {
        if ($this->parser == null) {
            $this->parser = new PHPSQLParser();
        }
        return $this->parser;
    }

    private function checkKeywords(array $query): bool
    {
        $keywords = array_keys($query);
        $diff = array_diff($keywords, $this->keywords);
        // todo throw?
        return empty($diff);
    }

    private function checkExpression(array $query): bool
    {
        $check = true;
        foreach ($query as $method => $expression) {
            if ($method != "DELETE" && $method != "LIMIT") {
                foreach ($expression as $item) {
                    if (!in_array($item["expr_type"], $this->expr_types)) {
                        // todo throw?
                        if (!in_array($item["base_expr"], $this->keywords)) {
                            $check = false;
                        }
                    }
                    if ($item["expr_type"] == "operator") {
                        // todo throw?
                        if (!in_array(strtolower($item["base_expr"]), $this->operators)) {
                            $check = false;
                        }
                    }
                    if ($item["expr_type"] == "table") {
                        // todo throw?
                        if (strtolower($item["table"]) != strtolower($this->csvdb->database)) {
                            $check = false;
                        }
                    }
                }
            }
        }
        return $check;
    }

    /**
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     * @throws CannotInsertRecord
     */
    private function prepareStmt(array $query, string $method)
    {
        $count = false;
        $fields = array();
        $where_expr = array();
        $where = array();
        $limit = array();
        $order = array();
        foreach ($query as $keyword => $expression) {
            foreach ($expression as $item) {
                if ($keyword == "SELECT") {
                    // todo check expr_type colref?? if ($item['expr_type'] == "colref" && $item['base_expr'] != "*") {
                    if ($item['base_expr'] != "*") {
                        $fields[] = str_replace(['"', "'"],"",$item['base_expr']);
                    }
                    if ($item['expr_type'] == "aggregate_function") {
                        $count = true;
                    }
                } else if ($keyword == "INSERT") {
                    if ($item['expr_type'] == "column-list") {
                        $fields = $this->create_insert_fields($item['sub_tree'], $fields);
                    }
                } else if ($keyword == "VALUES") {
                    if ($item['expr_type'] == "record") {
                        $fields = $this->create_insert_values($item['data'], $fields);
                    }
                } else if ($keyword == "SET") {
                    if ($item['expr_type'] == "expression") {
                        $field = $item["sub_tree"][0]["base_expr"];
                        $value = trim($item["sub_tree"][2]["base_expr"], "\"\'");
                        $fields[$field] = $value;
                    }
                } else if ($keyword == "WHERE") {
                    if ($item['expr_type'] == "colref") {
                        $where = array(
                            "field" => $item['base_expr']
                        );
                    }
                    if ($item['expr_type'] == "operator") {
                        if (strtolower($item['base_expr']) != "and" && strtolower($item['base_expr']) != "or") {
                            //$where["operator"][] = $item['base_expr']; // when multiple operators like "NOT LIKE"
                            $where["operator"] = $item['base_expr'];
                        } else {
                            $where_expr[] = ["concat" => $item['base_expr']];
                        }
                    }
                    if ($item['expr_type'] == "const") {
                        $where["value"] = trim($item['base_expr'], "\"\'");
                        $where_expr[] = $where;
                    }
                    if ($item['expr_type'] == "in-list") {
                        $where["value"] = $item['sub_tree'];
                        $where_expr[] = $where;
                    }
                } else if ($keyword == "ORDER") {
                    if ($item['expr_type'] == "colref") {
                        // todo check if more than one order!
                        $order = [$item['base_expr'] => $this->direction($item['direction'])];
                    }
                }
            }

            if ($keyword == "LIMIT") {
                $limit = ["limit" => $expression['rowcount'], "offset" => $expression['offset']];
            }
        }

        if ($method == "INSERT") {
            return $this->csvdb->insert($fields);
        } else if ($method == "SELECT") {
            $q = $this->csvdb->select($fields);
            if ($count) {
                $q->count();
            }
            if (!empty($where_expr)) {
                $q->where($this->create_where($where_expr));
            }
            if (!empty($limit)) {
                $q->limit($limit["limit"], $limit["offset"]);
            }
            if (!empty($order)) {
                $q->orderBy($order);
            }
            return $q->get();
        } else if ($method == "UPDATE") {
            return $this->csvdb->update($fields, $this->create_where($where_expr));
        } else if ($method == "DELETE") {
            return $this->csvdb->delete($this->create_where($where_expr));
        }
        return array();
    }

    private function create_where(array $where_expr): array
    {
        $where = array();
        if (count($where_expr) == 1) {
            $where[] = $this->create_where_expression($where_expr[0]);
        } else {
            for ($i = 0; $i < count($where_expr); $i++) {
                $current = $i;
                $expression = $where_expr[$i];
                if (array_key_exists("concat", $expression)) {
                    if (strtolower($expression["concat"]) == "or") {
                        $where[] =
                            [
                                $this->create_where_expression($where_expr[$current - 1]),
                                $this->create_where_expression($where_expr[$current + 1]),
                                $this->operator($expression["concat"])
                            ];
                        $i++;
                    } else if (strtolower($expression["concat"]) == "and") {
                        if (empty($where)) {
                            $where[] = [
                                $this->create_where_expression($where_expr[$current - 1]),
                                $this->create_where_expression($where_expr[$current + 1])
                            ];
                            $i++;
                        } else {
                            $where[] = $this->create_where_expression($where_expr[$current + 1]);
                        }
                    }
                }
            }
        }
        if (count($where) == 1) {
            $where = $where[0];
        }
        return $where;
    }

    private function create_where_expression(array $expression): array
    {
        if ($expression["operator"] == "=") {
            return [$expression["field"] => $expression["value"]];
        } else if (strtolower($expression["operator"]) == "like") {
            return [$expression["field"] => [$expression["value"], CSVDB::LIKE]];
        } else if (strtolower($expression["operator"]) == "!=" || strtolower($expression["operator"]) == "<>") {
            return [$expression["field"] => [$expression["value"], CSVDB::NEG]];
        } else if (strtolower($expression["operator"]) == "in") {
            $values = array_map(function ($value) {
                return trim($value['base_expr'], "\"\'");
            }, $expression["value"]);
            return [[$expression["field"] => $values], CSVDB::OR];
        }
        return array();
    }

    private function operator(string $operator): string
    {
        if (strtolower($operator) == CSVDB::OR) {
            return CSVDB::OR;
        } else {
            return CSVDB::AND;
        }
    }

    private function direction(string $direction): string
    {
        if (strtolower($direction) == CSVDB::DESC) {
            return CSVDB::DESC;
        } else {
            return CSVDB::ASC;
        }
    }

    private function create_insert_fields(array $expression, array $fields): array
    {
        foreach ($expression as $item) {
            if ($item["expr_type"] == "colref") {
                $fields[] = $item["base_expr"];
            }
        }
        return $fields;
    }

    private function create_insert_values(array $expression, array $fields): array
    {
        $insert = array();
        for ($i = 0; $i < count($expression); $i++) {
            if ($expression[$i]["expr_type"] == "const") {
                $insert[$fields[$i]] = trim($expression[$i]['base_expr'], "\"\'");
            }
        }
        return $insert;
    }


    /**
     * @throws UnableToProcessCsv
     * @throws InvalidArgument
     * @throws CannotInsertRecord
     * @throws \Exception
     */
    public function get(Converter $converter = null): array
    {
        $obj = $this->parser()->parse($this->query);

        if (!empty($this->query) && $this->checkKeywords($obj) && $this->checkExpression($obj)) {
            $method = array_keys($obj)[0];
            $data = $this->prepareStmt($obj, $method);

            // converter
            if (isset($converter) && is_array($data)) {
                $data = $converter->convert($data);
            }

            return $data;
        } else {
            throw new \Exception("There is an Error in your Query: $this->query; Please check the available Keywords [LINK]");
        }
    }

    /**
     * @throws InvalidArgument
     * @throws \ReflectionException
     * @throws Exception
     * @throws \Exception|UnableToProcessCsv
     */
    public function export(string $type = ExportEnum::CSV): string
    {
        if (!ExportEnum::isValid($type)) {
            throw new \Exception("Invalid Export Type: $type");
        } else {
            switch ($type) {
                case ExportEnum::JSON:
                    return json_encode($this->get(), JSON_PRETTY_PRINT);
                case ExportEnum::SQL:
                    return implode("\n", $this->get(new SQLConverter($this->csvdb->database)));
                case ExportEnum::PHP:
                    return var_export($this->get(), true);
                default:
                    return implode("\n", $this->get(new CSVConverter($this->csvdb->config->delimiter, $this->csvdb->config->headers)));
            }
        }
    }
}