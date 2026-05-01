<?php

declare(strict_types=1);

namespace app\database;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;

# Fachada estática sobre o Doctrine DBAL — elimina boilerplate de $conn->createQueryBuilder()
final class DB
{
    # Inicia um SELECT e retorna o QueryBuilder para encadeamento
    # Uso: DB::select('id', 'name')->from('users')->executeQuery()->fetchAllAssociative()
    public static function select(string ...$cols): QueryBuilder
    {
        $qb = Doctrine::connection()->createQueryBuilder();
        return $cols ? $qb->select(...$cols) : $qb->select('*');
    }

    # INSERT direto com array associativo — retorna número de linhas afetadas
    # Uso: DB::insert('users', ['name' => 'João', 'email' => 'j@j.com'])
    public static function insert(string $table, array $data): int
    {
        return (int) Doctrine::connection()->insert($table, $data);
    }

    # UPDATE com critérios WHERE em array — retorna linhas afetadas
    # Uso: DB::update('users', ['name' => 'Maria'], ['id' => 1])
    public static function update(string $table, array $data, array $where): int
    {
        return (int) Doctrine::connection()->update($table, $data, $where);
    }

    # DELETE com critérios WHERE em array — retorna linhas afetadas
    # Uso: DB::delete('users', ['id' => 5])
    public static function delete(string $table, array $where): int
    {
        return (int) Doctrine::connection()->delete($table, $where);
    }

    # SQL bruto — para DDL, CTEs e consultas fora do escopo do QueryBuilder
    # Uso: DB::raw('SELECT * FROM users WHERE id = ?', [1])->fetchAllAssociative()
    public static function raw(string $sql, array $params = []): Result
    {
        return Doctrine::connection()->executeQuery($sql, $params);
    }

    # Acesso à conexão nativa — necessário para createNamedParameter e transações manuais
    # Uso com parâmetro nomeado: $qb = DB::select('id')->from('users');
    #                            $qb->where('email = ' . $qb->createNamedParameter($email))
    public static function conn(): \Doctrine\DBAL\Connection
    {
        return Doctrine::connection();
    }
}
