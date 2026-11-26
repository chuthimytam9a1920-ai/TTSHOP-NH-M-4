<?php
/** Shared connection; transactions always use the caller's mysqli handle. */
abstract class DatabaseService
{
    protected mysqli $connection;
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
}
