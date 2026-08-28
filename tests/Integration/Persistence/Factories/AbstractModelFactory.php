<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence\Factories;

use Magma\database\DatabaseConnectionManager;
use PDO;

abstract class AbstractModelFactory
{
    protected DatabaseConnectionManager $dbManager;
    protected string $tableName;

    public function __construct(DatabaseConnectionManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    abstract protected function getDefaults(): array;

    /**
     * Create a record merging overrides with defaults.
     *
     * @param array $overrides
     * @return int The ID of the inserted record.
     */
    public function create(array $overrides = []): int
    {
        $data = array_merge($this->getDefaults(), $overrides);
        
        return $this->insert($this->tableName, $data);
    }

    /**
     * Execute a raw insert and return the ID.
     */
    protected function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) RETURNING id";
        
        $db = $this->dbManager->getWriteConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $stmt->fetchColumn();
    }
}
