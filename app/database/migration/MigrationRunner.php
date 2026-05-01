<?php

declare(strict_types=1);

namespace app\database\migration;

use app\database\Doctrine;
use Doctrine\DBAL\Connection;

# Descobre, rastreia e executa migrations no padrão {YmdHis}_{Nome}.php
final class MigrationRunner
{
    private const TABLE = 'migrations';         # Tabela de controle no banco
    private const DIR   = __DIR__;              # Diretório das migrations (mesmo desta classe)
    private const NS    = 'app\\database\\migration\\'; # Namespace das classes de migration

    private Connection $db;

    public function __construct()
    {
        $this->db = Doctrine::connection();
        # Cria a tabela de controle se ainda não existir — idempotente
        $this->db->executeStatement(
            "CREATE TABLE IF NOT EXISTS " . self::TABLE .
                " (migration VARCHAR(255) PRIMARY KEY, executed_at TIMESTAMP DEFAULT NOW())"
        );
    }

    # Retorna nomes das migrations já executadas (nome do arquivo sem .php)
    private function executed(): array
    {
        return $this->db->fetchFirstColumn('SELECT migration FROM ' . self::TABLE . ' ORDER BY migration');
    }

    # Descobre arquivos no padrão *_*.php — sort() ordena pelo timestamp do nome
    private function discover(): array
    {
        $files = glob(self::DIR . '/*_*.php') ?: [];
        sort($files);
        return $files;
    }

    # Instancia a classe de migration — extrai o nome da classe da parte após o primeiro "_"
    private function load(string $file): AbstractMigration
    {
        require_once $file;
        # Ex: 20250430143022_CreateCustomers.php → classe CreateCustomers
        $class = pathinfo(explode('_', basename($file), 2)[1] ?? '', PATHINFO_FILENAME);
        return new (self::NS . $class)($this->db);
    }

    # Executa operação dentro de transação — faz rollback automático em caso de falha
    private function transact(callable $fn): void
    {
        $this->db->beginTransaction();
        try {
            $fn();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    # Aplica todas as migrations pendentes em ordem cronológica
    public function up(): void
    {
        $done = $this->executed();
        foreach ($this->discover() as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME); # Nome sem .php — chave única no banco
            if (in_array($key, $done, true)) continue; # Pula migrations já executadas
            $this->transact(function () use ($file, $key) {
                $this->load($file)->up();
                $this->db->insert(self::TABLE, ['migration' => $key]); # Registra como executada
            });
            echo "  ✔ {$key}" . PHP_EOL;
        }
    }

    # Reverte a última migration executada — uma por vez, nunca em lote
    public function down(): void
    {
        $done = $this->executed();
        if (!$done) {
            echo "  Nenhuma migration para reverter." . PHP_EOL;
            return;
        }
        $last = end($done); # Pega o nome da migration mais recente
        $this->transact(function () use ($last) {
            $this->load(self::DIR . "/{$last}.php")->down();
            $this->db->delete(self::TABLE, ['migration' => $last]); # Remove do controle
        });
        echo "  ✔ Revertido: {$last}" . PHP_EOL;
    }

    # Exibe o estado atual: ✔ executada | ○ pendente
    public function status(): void
    {
        $done = $this->executed();
        foreach ($this->discover() as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            echo (in_array($key, $done, true) ? '  ✔' : '  ○') . " {$key}" . PHP_EOL;
        }
    }
}
