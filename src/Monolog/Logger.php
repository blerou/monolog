<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;

/**
 * Monolog log channel
 *
 * It contains a stack of Handlers and a stack of Processors,
 * and uses them to store records that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Logger
{
    /**
     * Detailed debug information
     */
    const DEBUG = 100;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    const INFO = 200;

    /**
     * Exceptional occurences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    const WARNING = 300;

    /**
     * Runtime errors
     */
    const ERROR = 400;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    const CRITICAL = 500;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    const ALERT = 550;

    protected $name;

    /**
     * The handler stack
     *
     * @var array of Monolog\Handler\HandlerInterface
     */
    protected $handlers = array();

    protected $processors = array();

    /**
     * @param string $name The logging channel
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Pushes an handler on the stack.
     *
     * @param HandlerInterface $handler
     */
    public function pushHandler(HandlerInterface $handler)
    {
        array_unshift($this->handlers, $handler);
    }

    /**
     * Adds a processor in the stack.
     *
     * @param callable $callback
     */
    public function pushProcessor($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Processors must be valid callables (callback or object with an __invoke method), '.var_export($callback, true).' given');
        }
        array_unshift($this->processors, $callback);
    }

    public function addWarningHandler($handler)
    {
        $this->addHandler($handler, self::WARNING);
    }

    public function addErrorHandler($handler)
    {
        $this->addHandler($handler, self::ERROR);
    }

    private function addHandler($handler, $priority)
    {
        $this->handlers[] = array($handler, $priority);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addDebug($message, array $context = array())
    {
        return $this->addRecord(
            $this->createRecord(self::DEBUG, 'DEBUG', $message, $context),
            $this->findHandlerFor(self::DEBUG)
        );
    }

    /**
     * Adds a log record at the INFO level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addInfo($message, array $context = array())
    {
        return $this->addRecord(
            $this->createRecord(self::INFO, 'INFO', $message, $context),
            $this->findHandlerFor(self::INFO)
        );
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addWarning($message, array $context = array())
    {
        return $this->addRecord(
            $this->createRecord(self::WARNING, 'WARNING', $message, $context),
            $this->findHandlerFor(self::WARNING)
        );
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addError($message, array $context = array())
    {
        return $this->addRecord(
            $this->createRecord(self::ERROR, 'ERROR', $message, $context),
            $this->findHandlerFor(self::ERROR)
        );
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addCritical($message, array $context = array())
    {
        return $this->addRecord(
            $this->createRecord(self::CRITICAL, 'CRITICAL', $message, $context),
            $this->findHandlerFor(self::CRITICAL)
        );
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addAlert($message, array $context = array())
    {
        return $this->addRecord(
            $this->createRecord(self::ALERT, 'ALERT', $message, $context),
            $this->findHandlerFor(self::ALERT)
        );
    }

    /**
     * creates a log record.
     *
     * @param integer $level The logging level
     * @param string $levelName The logging level name
     * @param string $message The log message
     * @param array $context The log context
     * @return array
     */
    private function createRecord($level, $levelName, $message, array $context = array())
    {
        return array(
            'message' => (string)$message,
            'context' => $context,
            'level' => $level,
            'level_name' => $levelName,
            'channel' => $this->name,
            'datetime' => new \DateTime(),
            'extra' => array(),
        );
    }

    private function findHandlerFor($priority)
    {
        foreach ($this->handlers as $handler) {
            if (is_array($handler) && $handler[1] == $priority) {
                return $handler[0];
            } else if ($handler instanceof HandlerInterface && $handler->isHandling(array('level' => $priority))) {
                return $handler;
            }
        }
        return null;
    }

    /**
     * Adds a log record.
     *
     * @param array $record the log record to add
     * @param array $handler the affected handlers
     * @return Boolean Whether the record has been processed
     */
    private function addRecord($record, $handler)
    {
        if ($handler) {
            $record = $this->preprocessRecord($record);
            $handler->handle($record);
            return true;
        } else {
            return false;
        }
    }

    private function preprocessRecord($record)
    {
        foreach ($this->processors as $processor) {
            $record = call_user_func($processor, $record);
        }
        return $record;
    }
}
