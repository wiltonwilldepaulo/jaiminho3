<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Connection;

# Contrato base obrigatório — todo arquivo de migration deve estender esta classe
abstract class AbstractMigration
{
    # A conexão DBAL é injetada pelo MigrationRunner via construtor
    public function __construct(protected readonly Connection $db) {}

    # up(): aplica a migration — CREATE TABLE, ALTER TABLE, índices etc.
    abstract public function up(): void;

    # down(): reverte a migration — deve desfazer exatamente o que up() fez
    abstract public function down(): void;

    # Helper: executa DDL sem precisar referenciar $this->db explicitamente
    protected function execute(string $sql): void
    {
        $this->db->executeStatement($sql);
    }
}
