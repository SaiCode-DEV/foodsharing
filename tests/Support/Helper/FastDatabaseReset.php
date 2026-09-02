<?php

namespace Tests\Support\Helper;

use Codeception\Exception\ModuleConfigException;
use Codeception\Module\Db;
use Codeception\TestInterface;
use PDO;

/**
 * Resets the database between tests without rebuilding the schema.
 *
 * The stock Db module drops all 97 tables before every single test and replays
 * dump.sql, which is 388 schema statements and 6 INSERTs. That tears down the whole
 * database to reset six tables holding about 90 rows. This module empties the tables
 * that actually contain rows and replays the INSERTs instead, which leaves the same
 * state behind and cuts the backend suites roughly in half.
 *
 * One difference to the stock module is worth knowing: because the schema is no longer
 * rebuilt, a test that changes it is no longer undone. A table created by a test stays
 * (emptied), an ALTER stays in effect. No test does that today, but the reset is not
 * self-healing for schema changes any more.
 */
class FastDatabaseReset extends Db
{
    private ?array $insertStatements = null;
    /** AUTO_INCREMENT values of the untouched database, per table. */
    private ?array $expectedAutoIncrement = null;

    public function _before(TestInterface $test): void
    {
        // Honour the same switches as the module this replaces: without cleanup there is
        // nothing to reset, and without populate there is no dump to restore from.
        if (!$this->config['cleanup'] || !$this->config['populate']) {
            parent::_before($test);

            return;
        }

        // Same order as the module this replaces: honour the reconnect switch, then make
        // sure the next test starts on the default database even if the last one switched.
        $this->reconnectDatabases();
        $this->amConnectedToDatabase(self::DEFAULT_DATABASE);

        $dbh = $this->_getDbh();

        // On the first call the database is still in the state the full dump load left
        // behind, so those are the counter values every later reset has to restore.
        if ($this->expectedAutoIncrement === null) {
            $this->expectedAutoIncrement = $this->readAutoIncrement($dbh);
        }

        // The dump sets NO_AUTO_VALUE_ON_ZERO so the root region keeps its id 0. Without
        // it the INSERT turns that into a 1 and fs_bezirk_closure points at nothing.
        $dbh->exec("SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");
        $dbh->exec('SET FOREIGN_KEY_CHECKS=0;');

        $tables = $dbh->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE '%TABLE'")
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if ($dbh->query("SELECT 1 FROM `{$table}` LIMIT 1")->fetchColumn() !== false) {
                $dbh->exec("TRUNCATE TABLE `{$table}`");
            }
        }

        foreach ($this->getInsertStatements() as $statement) {
            $dbh->exec($statement);
        }

        // Restore the counters. Two cases need it: tables whose CREATE TABLE carries an
        // explicit start value that TRUNCATE does not know about, and tables a test filled
        // and Codeception emptied again in _after - those are empty, but their counter
        // stays high and the next test would get different IDs.
        foreach ($this->readAutoIncrement($dbh) as $table => $actual) {
            $expected = $this->expectedAutoIncrement[$table] ?? null;
            if ($expected !== null && (int)$actual !== (int)$expected) {
                $dbh->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = " . (int)$expected);
            }
        }

        $dbh->exec('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function readAutoIncrement(PDO $dbh): array
    {
        return $dbh->query(
            'SELECT TABLE_NAME, AUTO_INCREMENT FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND AUTO_INCREMENT IS NOT NULL'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function getInsertStatements(): array
    {
        if ($this->insertStatements !== null) {
            return $this->insertStatements;
        }

        $dump = $this->config['dump'] ?? null;
        if (empty($dump) || !is_readable($dump)) {
            throw new ModuleConfigException(self::class, sprintf('The dump file "%s" is missing or unreadable. This module needs it to restore the seed rows after emptying the tables.', (string)$dump));
        }

        $statements = [];
        $buffer = '';
        foreach (preg_split('/\r\n|\n|\r/', file_get_contents($dump)) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')
                || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $buffer .= "\n" . rtrim($line);
            if (str_ends_with(rtrim($line), ';')) {
                $statement = substr($buffer, 0, -1);
                if (stripos(ltrim($statement), 'INSERT') === 0) {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        return $this->insertStatements = $statements;
    }
}
