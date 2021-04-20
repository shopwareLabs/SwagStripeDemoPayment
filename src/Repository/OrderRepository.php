<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;

class OrderRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function fetchOrder(string $transactionId): ?array
    {
        $value = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('`order`')
            ->where('transaction_id = :id')
            ->setParameter('id', $transactionId)
            ->execute()
            ->fetch(FetchMode::ASSOCIATIVE);

        return $value !== false ? $value : null;
    }

    public function fetchColumn(string $column, string $transactionId): ?string
    {
        $value = $this->connection->createQueryBuilder()
            ->select(sprintf('`%s`', $column))
            ->from('`order`')
            ->where('transaction_id = :id')
            ->setParameter('id', $transactionId)
            ->execute()
            ->fetchColumn();

        return $value !== false ? $value : null;
    }

    public function insertNewOrder(array $data): void
    {
        $this->connection->insert('`order`', $data);
    }

    public function updateOrderStatus(string $status, $transactionId): void
    {
        $this->connection->update(
            '`order`',
            ['status' => $status],
            ['transaction_id' => $transactionId]
        );
    }
}
