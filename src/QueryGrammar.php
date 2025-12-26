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
     * Compile WHERE clauses - DON'T rewrite here since this gets called multiple times
     */
    public function compileWheres(Builder $query): string
    {
        return parent::compileWheres($query);
    }

    /**
     * Override compileSelect to do the final parameter rewrite once at the end
     */
    public function compileSelect(Builder $query): string
    {
        $sql = parent::compileSelect($query);
        return self::prepareParameters($sql);
    }

    /**
     * Replace all occurrences of PARAMETER_SIGN with sequential placeholders.
     * This should only be called ONCE on the final SQL statement.
     */
    public static function prepareParameters(string $sql): string
    {
        $index = 0;

        while (($pos = strpos($sql, self::PARAMETER_SIGN)) !== false) {
            $sql = substr_replace($sql, ':' . $index, $pos, strlen(self::PARAMETER_SIGN));
            $index++;
        }

        return $sql;
    }

    protected function compileDeleteWithoutJoins(Builder $query, $table, $where): string
    {
        $sql = "alter table {$table} delete {$where}";
        return self::prepareParameters($sql);
    }
}
