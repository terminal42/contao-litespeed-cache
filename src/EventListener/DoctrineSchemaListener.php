<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\EventListener;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

#[AsEventListener]
class DoctrineSchemaListener
{
    public function __invoke(GenerateSchemaEventArgs $event): void
    {
        $table = $event->getSchema()->createTable(ProxyHandler::CACHE_TASKS_TABLE);

        $table->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $table->addColumn('tstamp', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $table->addColumn('job', Types::JSON);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['tstamp']);
    }
}
