<?php

declare(strict_types=1);

namespace LaravelClickhouseEloquent;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

class QueryGrammar extends Grammar
{
    /**
     * Marker we temporarily use in place of "?" so we can later rewrite in-order.
     * Must be something that will never naturally occur in SQL.
     */
    public const PARAMETER_SIGN = '#@?';

    /**
     * Turn array values into placeholders.
     *
     * NOTE: This is used by WHERE IN, etc. Laravel expects this to return placeholders,
     * not actual values.
     */
    public function parameterize(array $values): string
    {
        return implode(', ', array_fill(0, count($values), self::PARAMETER_SIGN));
    }

    /**
     * Compile a single parameter placeholder.
     */
    public function parameter($value): string
    {
        return $this->isExpression($value)
            ? (string) $this->getValue($value)
            : self::PARAMETER_SIGN;
    }

    /**
     * Compile WHERE clauses and then rewrite PARAMETER_SIGN markers into :0, :1, ...
     */
    public function compileWheres(Builder $query): string
    {
        return self::prepareParameters(parent::compileWheres($query));
    }

    /**
     * Replace all occurrences of PARAMETER_SIGN with sequential placeholders.
     *
     * IMPORTANT: This function may be called multiple times on the same SQL string
     * as parts of the query get recompiled. If the SQL already contains :0, :1, etc,
     * we must continue counting from the highest existing index to avoid collisions.
     */
    public static function prepareParameters(string $sql): string
    {
        $next = self::nextParameterIndex($sql);

        while (($pos = strpos($sql, self::PARAMETER_SIGN)) !== false) {
            $sql = substr_replace($sql, ':' . $next, $pos, strlen(self::PARAMETER_SIGN));
            $next++;
        }

        return $sql;
    }

    /**
     * Find the next available numeric placeholder index by scanning the SQL.
     *
     * Supports:
     *  - :0, :1, :2 ...
     */
    private static function nextParameterIndex(string $sql): int
    {
        $max = -1;

        // Match :0, :12, :p0, :p12
        if (preg_match_all('/:(?:p)?(\d+)/', $sql, $m)) {
            foreach ($m[1] as $n) {
                $i = (int) $n;
                if ($i > $max) {
                    $max = $i;
                }
            }
        }

        return $max + 1; // if none found => 0
    }

    protected function compileDeleteWithoutJoins(Builder $query, $table, $where): string
    {
        return "alter table {$table} delete {$where}";
    }
}
