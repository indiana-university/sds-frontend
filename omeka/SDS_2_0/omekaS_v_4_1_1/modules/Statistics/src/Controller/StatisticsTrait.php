<?php declare(strict_types=1);

namespace Statistics\Controller;

trait StatisticsTrait
{
    /**
     * List years as key and value from a table.
     *
     * When the option to include dates without value is set, value may be null.
     */
    protected function listYears(string $table, ?int $fromYear = null, ?int $toYear = null, bool $includeEmpty = false, string $field = 'created'): array
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select("DISTINCT EXTRACT(YEAR FROM $table.$field) AS 'period'")
            // ->select("DISTINCT SUBSTRING($table.$field, 1, 4) AS 'period'")
            ->from($table, $table)
            ->orderBy('period', 'asc');
        // Don't use function YEAR() in where for speed. Extract() is useless here.
        // TODO Add a generated index (doctrine 2.11, so Omeka 4).
        if ($fromYear && $toYear) {
            method_exists($expr, 'between')
                ? $qb->andWhere($expr->between($table . '.' . $field, ':from_date', ':to_date'))
                : $qb->andWhere($expr->andX($expr->gte($table . '.' . $field, ':from_date'), $expr->lte($table . '.' . $field, ':to_date')));
            $qb
                ->setParameters([
                    'from_date' => $fromYear . '-01-01 00:00:00',
                    'to_date' => $toYear . '-12-31 23:59:59',
                ], [
                    'from_date' => \Doctrine\DBAL\ParameterType::STRING,
                    'to_date' => \Doctrine\DBAL\ParameterType::STRING,
                ]);
        } elseif ($fromYear) {
            $qb
                ->andWhere($expr->gte($table . '.' . $field, ':from_date'))
                ->setParameter('from_date', $fromYear . '-01-01 00:00:00', \Doctrine\DBAL\ParameterType::STRING);
        } elseif ($toYear) {
            $qb
                ->andWhere($expr->lte($table . '.' . $field, ':to_date'))
                ->setParameter('to_date', $toYear . '-12-31 23:59:59', \Doctrine\DBAL\ParameterType::STRING);
        }
        $result = $this->connection->executeQuery($qb, $qb->getParameters(), $qb->getParameterTypes())->fetchFirstColumn();

        $result = array_combine($result, $result);
        if (!$includeEmpty || count($result) <= 1) {
            return $result;
        }

        $range = array_fill_keys(range(min($result), max($result)), null);
        return array_replace($range, $result);
    }

    /**
     * List year-months as key and value from a table.
     *
     * When the option to include dates without value is set, value may be null.
     */
    protected function listYearMonths(string $table, ?int $fromYearMonth = null, ?int $toYearMonth = null, bool $includeEmpty = false, string $field = 'created'): array
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select("DISTINCT EXTRACT(YEAR_MONTH FROM $table.$field) AS 'period'")
            // ->select("DISTINCT CONCAT(SUBSTRING($table.$field, 1, 4), SUBSTRING($table.$field, 6, 2)) AS 'period'")
            ->from($table, $table)
            ->orderBy('period', 'asc');
        // Don't use function YEAR() in where for speed. Extract() is useless here.
        // TODO Add a generated index (doctrine 2.11, so Omeka 4).
        $bind = [];
        $types = [];
        if ($fromYearMonth) {
            $bind['from_date'] = sprintf('%04d-%02d', substr((string) $fromYearMonth, 0, 4), substr((string) $fromYearMonth, 4, 2)) . '-01 00:00:00';
            $types['from_date'] = \Doctrine\DBAL\ParameterType::STRING;
        }
        if ($toYearMonth) {
            $year = (int) substr((string) $toYearMonth, 0, 4);
            $month = (int) substr((string) $toYearMonth, 4, 2) ?: 12;
            $day = $month === 2 ? date('L', mktime(0, 0, 0, 1, 1, $year) ? 29 : 28) : (in_array($month, [4, 6, 9, 11]) ? 30 : 31);
            $bind['to_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day) . ' 23:59:59';
            $types['to_date'] = \Doctrine\DBAL\ParameterType::STRING;
        }
        if ($fromYearMonth && $toYearMonth) {
            method_exists($expr, 'between')
                ? $qb->andWhere($expr->between($table . '.' . $field, ':from_date', ':to_date'))
                : $qb->andWhere($expr->andX($expr->gte($table . '.' . $field, ':from_date'), $expr->lte($table . '.' . $field, ':to_date')));
        } elseif ($fromYearMonth) {
            $qb->andWhere($expr->gte($table . '.' . $field, ':from_date'));
        } elseif ($toYearMonth) {
            $qb->andWhere($expr->lte($table . '.' . $field, ':to_date'));
        }
        $result = $this->connection->executeQuery($qb, $bind, $types)->fetchFirstColumn();
        $result = array_combine($result, $result);
        if (!$includeEmpty || count($result) <= 1) {
            return $result;
        }

        // Fill all the missing months.
        $periods = $result;

        $first = reset($periods);
        $firstDate = $fromYearMonth ?: substr((string) $first, 0, 4) . '01';
        $firstYear = (int) substr((string) $firstDate, 0, 4);
        $firstMonth = (int) substr((string) $firstDate, 4, 2);

        $reversedPeriods = array_reverse($periods);
        $last = reset($reversedPeriods);
        $lastDate = $toYearMonth ?: substr((string) $last, 0, 4) . '12';
        $lastYear = (int) substr((string) $lastDate, 0, 4);
        $lastMonth = (int) substr((string) $lastDate, 4, 2);

        $range = [];

        // Fill months for first year.
        $isSingleYear = $firstYear === $lastYear;
        foreach (range($firstMonth, $isSingleYear ? $lastMonth : 12) as $currentMonth) {
            $range[sprintf('%04d%02d', $firstYear, $currentMonth)] = null;
        }

        // Fill months for intermediate years.
        $hasIntermediateYears = $firstYear + 1 < $lastYear;
        if ($hasIntermediateYears) {
            for ($currentYear = $firstYear + 1; $currentYear < $lastYear - 1; $currentYear++) {
                for ($currentMonth = 1; $currentMonth < 13; $currentMonth++) {
                    $range[sprintf('%04d%02d', $currentYear, $currentMonth)] = null;
                }
            }
        }

        // Fill months for last year.
        if (!$isSingleYear) {
            foreach (range($firstMonth, $lastMonth ?: 12) as $currentMonth) {
                $range[sprintf('%04d%02d', $firstYear, $currentMonth)] = null;
            }
        }

        return array_replace($range, $periods);
    }

    /**
     * Get one or more property ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[]|int|null The property ids matching terms or ids, or all
     * properties by terms.
     */
    protected function getPropertyId($termsOrIds = null)
    {
        static $propertiesByTerms;
        static $propertiesByTermsAndIds;

        if (is_null($propertiesByTermsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    'property.id AS id',
                    // Required with only_full_group_by.
                    'vocabulary.id'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
            ;
            $propertiesByTerms = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            $propertiesByTermsAndIds = array_replace($propertiesByTerms, array_combine($propertiesByTerms, $propertiesByTerms));
        }

        if (is_null($termsOrIds)) {
            return $propertiesByTerms;
        }

        if (is_scalar($termsOrIds)) {
            return $propertiesByTermsAndIds[$termsOrIds] ?? null;
        }

        return array_intersect_key($propertiesByTermsAndIds, array_flip($termsOrIds));
    }
}
