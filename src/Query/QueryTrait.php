<?php

namespace CSVDB\Query;

use CSVDB\CSVDB;
use PHPSQLParser\PHPSQLParser;

trait QueryTrait
{
    public array $keywords = [
        "INSERT", "SELECT", "UPDATE", "DELETE", "FROM", "COUNT", "INTO", "SET", "WHERE", "ORDER", "LIKE", "NOT", "NULL", "ASC", "DESC"
    ];

    public array $expr_types = [
        "table", "expression", "colref", "operator", "const"
    ];

    public array $operators = [
        "=", "!=", "<>", "LIKE"
    ];

    private ?PHPSQLParser $parser = null;

    private function parser(): PHPSQLParser
    {
        if ($this->parser == null) {
            $this->parser = new PHPSQLParser();
        }
        return $this->parser;
    }

    /**
     * @throws \Exception
     */
    public function query(string $query)
    {
        var_dump($query);
        $obj = $this->parser()->parse($query);
        //var_dump($obj);
        if ($this->checkKeywords($obj) && $this->checkExpression($obj)) {
            $method = array_keys($obj)[0];
            switch ($method) {
                case "SELECT":
                    return $this->prepareSelect($obj);
            }
        } else {
            throw new \Exception("There is an Error in your Query: $query; Please check the available Keywords [LINK]");
        }
        return false;
    }

    private function checkKeywords(array $query): bool
    {
        $keywords = array_keys($query);
        $diff = array_diff($keywords, $this->keywords);
        return empty($diff);
    }

    private function checkExpression(array $query): bool
    {
        $check = true;
        foreach ($query as $expression) {
            foreach ($expression as $item) {
                if (!in_array($item["expr_type"], $this->expr_types)) {
                    if (!in_array($item["base_expr"], $this->keywords)) {
                        $check = false;
                    }
                }
            }
        }
        return $check;
    }

    private function prepareSelect(array $query): array
    {
        $select_fields = array();
        $where_expr = array();
        foreach ($query as $keyword => $expression) {
            foreach ($expression as $item) {
                if ($keyword == "SELECT") {
                    if ($item['expr_type'] == "colref" && $item['base_expr'] != "*") {
                        $select_fields[] = $item['base_expr'];
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
                }
            }
        }
        $where_expr2 = $this->create_where($where_expr);
        print_r($where_expr2);

        $q = $this->select($select_fields);
        if (!empty($where_expr)) {
            $q->where($where_expr2);
        }
        return $q->get();
    }

    private function create_where(array $where_expr): array
    {
        $where = array();
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
        }
        return array();
    }

    private function operator(string $operator): string
    {
        if (strtolower($operator) == "or") {
            return CSVDB::OR;
        } else {
            return CSVDB::AND;
        }
    }
}