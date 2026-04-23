<?php
namespace Omeka\View\Helper;


use Laminas\View\Helper\AbstractHelper;
use Doctrine\DBAL\Connection;


class MyDatabaseHelper extends AbstractHelper

{protected $connection;
public function __construct(Connection $connection)
{$this->connection = $connection;}

public function runQuery($sql)
{return $this->connection->executeQuery($sql)->fetchAll();
}
}
