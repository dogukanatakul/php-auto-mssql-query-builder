<?php
        $query = file_get_contents("multi-select.json");
        $query = json_decode($query, true);

        $sqls = [];
        foreach ($query['tables'] as $table) {
            if (isset($query[$table . "_rules"]) and $rules = $query[$table . "_rules"]) {
                $sqlQuery = [];
                foreach ($rules as $rule) {

                    if ($rule['type'] == "where" or $rule['type'] == "like" or $rule['type'] == "in") {
                        $sqlQuery[] = (isset($rule['operator']) ? $rule['operator'] : "AND");
                    }

                    if (isset($rule['group']) and $rule['group'] == "start") {
                        $sqlQuery[] = "(";
                    }
                    if ($rule['type'] == "where") {
                        $sqlQuery[] = $table . "." . $rule['column'] . "='" . $rule['value'] . "'";
                    } elseif ($rule['type'] == "like") {
                        $sqlQuery[] = $table . "." . $rule['column'] . " like '%" . $rule['value'] . "%'";
                    } elseif ($rule['type'] == "in") {
                        $sqlQuery[] = $table . "." . $rule['column'] . " IN ('" . implode("','", $rule['value']) . "')";
                    } elseif ($rule['type'] == "another") {
                        foreach ($rule['value'] as $childRule) {
                            $column = explode(".", $childRule['column']);
                            $sqlQuery[] = (isset($childRule['operator']) ? $childRule['operator'] : "AND");
                            if ($childRule['type'] == "where") {
                                $sqlQuery[] = $table . "." . $column[0] . " IN (SELECT " . $column[3] . " FROM " . $column[1] . " WHERE CONVERT(VARCHAR, " . $column[2] . ")='" . $childRule['value'] . "')";
                            } elseif ($childRule['type'] == "another") {
                                $childQueryExp = explode(".", $childRule['value']['column']);
                                $childQuery = "(SELECT convert (VARCHAR," . $childQueryExp[0] . ") FROM " . $childQueryExp[1] . " WHERE CONVERT(VARCHAR," . $childQueryExp[2] . ")='" . $childRule['value']['value'] . "')";
                                $sqlQuery[] = $table . "." . $column[0] . " IN (SELECT CONVERT(VARCHAR, " . $column[3] . ") FROM " . $column[1] . " WHERE CONVERT(VARCHAR, " . $rule['column'] . ") IN " . $childQuery . ")";
                            }
                        }
                    }
                    if (isset($rule['group']) and $rule['group'] == "end") {
                        $sqlQuery[] = ")";
                    }
                }
                unset($sqlQuery[0]);
                $sqls[$table] = "SELECT * FROM " . $table . " WHERE " . implode(" ", $sqlQuery);
            } else {
                $sqls[$table] = "SELECT * FROM " . $table;
            }
        }
