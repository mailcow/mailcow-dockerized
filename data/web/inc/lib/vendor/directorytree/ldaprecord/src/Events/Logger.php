<?php

namespace LdapRecord\Events;

use LdapRecord\Auth\Events\Event as AuthEvent;
use LdapRecord\Auth\Events\Failed;
use LdapRecord\Models\Events\Event as ModelEvent;
use LdapRecord\Query\Events\QueryExecuted as QueryEvent;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class Logger
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Logs the given event.
     *
     * @param mixed $event
     *
     * @return void
     */
    public function log($event)
    {
        switch (true) {
            case $event instanceof AuthEvent:
                $this->auth($event);
                break;
            case $event instanceof ModelEvent:
                $this->model($event);
                break;
            case $event instanceof QueryEvent:
                $this->query($event);
                break;
        }
    }

    /**
     * Logs an authentication event.
     *
     * @param AuthEvent $event
     *
     * @return void
     */
    public function auth(AuthEvent $event)
    {
        if (isset($this->logger)) {
            $connection = $event->getConnection();

            $message = "LDAP ({$connection->getHost()})"
                ." - Operation: {$this->getOperationName($event)}"
                ." - Username: {$event->getUsername()}";

            $result = null;
            $type = 'info';

            if (is_a($event, Failed::class)) {
                $type = 'warning';
                $result = " - Reason: {$connection->getLastError()}";
            }

            $this->logger->$type($message.$result);
        }
    }

    /**
     * Logs a model event.
     *
     * @param ModelEvent $event
     *
     * @return void
     */
    public function model(ModelEvent $event)
    {
        if (isset($this->logger)) {
            $model = $event->getModel();

            $on = get_class($model);

            $connection = $model->getConnection()->getLdapConnection();

            $message = "LDAP ({$connection->getHost()})"
                ." - Operation: {$this->getOperationName($event)}"
                ." - On: {$on}"
                ." - Distinguished Name: {$model->getDn()}";

            $this->logger->info($message);
        }
    }

    /**
     * Logs a query event.
     *
     * @param QueryEvent $event
     *
     * @return void
     */
    public function query(QueryEvent $event)
    {
        if (isset($this->logger)) {
            $query = $event->getQuery();

            $connection = $query->getConnection()->getLdapConnection();

            $selected = implode(',', $query->getSelects());

            $message = "LDAP ({$connection->getHost()})"
                ." - Operation: {$this->getOperationName($event)}"
                ." - Base DN: {$query->getBaseDn()}"
                ." - Filter: {$query->getQuery()}"
                ." - Selected: ({$selected})"
                ." - Time Elapsed: {$event->getTime()}";

            $this->logger->info($message);
        }
    }

    /**
     * Returns the operational name of the given event.
     *
     * @param mixed $event
     *
     * @return string
     */
    protected function getOperationName($event)
    {
        return (new ReflectionClass($event))->getShortName();
    }
}
