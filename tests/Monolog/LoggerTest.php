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

use Monolog\Processor\WebProcessor;
use Monolog\Handler\TestHandler;

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    const NAME = 'foo channel';

    /**
     * @var Logger
     */
    private $logger;

    protected function setUp()
    {
        $this->logger = new Logger(self::NAME);
    }

    /**
     * @test
     */
    public function notifiesTheHandlerWhenLogRecordHasBeenAddedAndTheHandlerCapableWithIt()
    {
        $message = 'test';

        $validRecord = $this->logicalAnd(
            $this->isType('array'),
            $this->arrayHasKey('message'),
            $this->contains($message),
            $this->arrayHasKey('channel'),
            $this->contains(self::NAME)
        );

        $handler = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
        $handler->expects($this->never())->method('isHandling')->will($this->returnValue(true));
        $handler->expects($this->once())->method('handle')->with($validRecord);

        $this->logger->addWarningHandler($handler);
        $this->logger->addWarning($message);
    }

    /**
     * @test
     */
    public function neverNotifyTheHandlerWhenItIsntCapableWith()
    {
        $handler = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
        $handler->expects($this->never())->method('isHandling');
        $handler->expects($this->never())->method('handle');

        $this->logger->addErrorHandler($handler);
        $this->logger->addWarning('test');
    }

    /**
     * @test
     */
    public function onlyTheFirstCapableHandlerNotified()
    {
        $handler1 = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
        $handler1->expects($this->never())->method('isHandling');
        $handler1->expects($this->never())->method('handle');

        $handler2 = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
        $handler2->expects($this->never())->method('isHandling');
        $handler2->expects($this->once())->method('handle')->will($this->returnValue(true));

        $this->logger->addErrorHandler($handler1);
        $this->logger->addWarningHandler($handler2);
        $this->logger->addWarning('irrelevant');
    }

    /**
     * @covers Monolog\Logger::__construct
     */
    public function testChannel()
    {
        $handler = new TestHandler;
        $this->logger->addCriticalHandler($handler);
        $this->logger->addCritical('test');
        list($record) = $handler->getRecords();
        $this->assertEquals(self::NAME, $record['channel']);
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testLog()
    {
        $handler = $this->getMock('Monolog\Handler\NullHandler', array('handle'));
        $handler->expects($this->once())->method('handle');
        $this->logger->pushHandler($handler);

        $this->assertTrue($this->logger->addWarning('test'));
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testLogNotHandled()
    {
        $handler = $this->getMock('Monolog\Handler\NullHandler', array('handle'), array(Logger::ERROR));
        $handler->expects($this->never())->method('handle');
        $this->logger->pushHandler($handler);

        $this->assertFalse($this->logger->addWarning('test'));
    }

    /**
     * @test
     */
    public function aProcessorProcessesALogMessage()
    {
        $message = 'message';
        $test = $this;
        $this->logger->pushProcessor(function($record) use($test, $message) {
            $test->assertEquals($message, $record['message']);
            return $record;
        });
        $handler = $this->getMock('Monolog\Handler\NullHandler');
        $handler->expects($this->any())->method('isHandling')->will($this->returnValue(true));
        $this->logger->pushHandler($handler);
        $this->logger->addWarning($message);
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testProcessorsAreExecuted()
    {
        $handler = new TestHandler;
        $this->logger->pushHandler($handler);
        $this->logger->pushProcessor(function($record) {
            $record['extra']['win'] = true;
            return $record;
        });
        $this->logger->addError('test');
        list($record) = $handler->getRecords();
        $this->assertTrue($record['extra']['win']);
    }

    /**
     * @dataProvider logMethodProvider
     * @covers Monolog\Logger::addDebug
     * @covers Monolog\Logger::addInfo
     * @covers Monolog\Logger::addWarning
     * @covers Monolog\Logger::addError
     * @covers Monolog\Logger::addCritical
     * @covers Monolog\Logger::addAlert
     */
    public function testLogMethods($method, $expectedLevel)
    {
        $handler = new TestHandler;
        $this->logger->pushHandler($handler);
        $this->logger->{$method}('test');
        list($record) = $handler->getRecords();
        $this->assertEquals($expectedLevel, $record['level']);
    }

    public function logMethodProvider()
    {
        return array(
            // monolog methods
            array('addDebug', Logger::DEBUG),
            array('addInfo', Logger::INFO),
            array('addWarning', Logger::WARNING),
            array('addError', Logger::ERROR),
            array('addCritical', Logger::CRITICAL),
            array('addAlert', Logger::ALERT),
        );
    }
}
