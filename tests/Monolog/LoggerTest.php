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
		$handler->expects($this->once())->method('isHandling')->will($this->returnValue(true));
		$handler->expects($this->once())->method('handle')->with($validRecord);

		$this->logger->pushHandler($handler);
		$this->logger->addWarning($message);
	}

	/**
	 * @test
	 */
	public function neverNotifyTheHandlerWhenItIsntCapableWith()
	{
		$handler = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
		$handler->expects($this->once())->method('isHandling')->will($this->returnValue(false));
		$handler->expects($this->never())->method('handle');

		$this->logger->pushHandler($handler);
		$this->logger->addWarning('test');
	}

	/**
	 * @test
	 */
	public function onlyTheFirstCapableHandlerNotified()
	{
		$handler1 = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
		$handler1->expects($this->once())->method('isHandling')->will($this->returnValue(false));
		$handler1->expects($this->never())->method('handle');

		$handler2 = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
		$handler2->expects($this->once())->method('isHandling')->will($this->returnValue(true));
		$handler2->expects($this->once())->method('handle')->will($this->returnValue(true));

		$handler3 = $this->getMock('\\Monolog\\Handler\\HandlerInterface');
		$handler3->expects($this->never())->method('isHandling');
		$handler3->expects($this->never())->method('handle');

		$this->logger->pushHandler($handler3);
		$this->logger->pushHandler($handler2);
		$this->logger->pushHandler($handler1);
		$this->logger->addWarning('irrelevant');
	}

     /**
     * @covers Monolog\Logger::__construct
     */
    public function testChannel()
    {
        $handler = new TestHandler;
        $this->logger->pushHandler($handler);
        $this->logger->addWarning('test');
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
        $handler->expects($this->never())
            ->method('handle');
        $this->logger->pushHandler($handler);

        $this->assertFalse($this->logger->addWarning('test'));
    }

    /**
     * @covers Monolog\Logger::pushHandler
     * @covers Monolog\Logger::popHandler
     * @expectedException LogicException
     */
    public function testPushPopHandler()
    {
        $handler1 = new TestHandler;
        $handler2 = new TestHandler;

        $this->logger->pushHandler($handler1);
        $this->logger->pushHandler($handler2);

        $this->assertEquals($handler2, $this->logger->popHandler());
        $this->assertEquals($handler1, $this->logger->popHandler());
        $this->logger->popHandler();
    }

    /**
     * @covers Monolog\Logger::pushProcessor
     * @covers Monolog\Logger::popProcessor
     * @expectedException LogicException
     */
    public function testPushPopProcessor()
    {
        $processor1 = new WebProcessor;
        $processor2 = new WebProcessor;

        $this->logger->pushProcessor($processor1);
        $this->logger->pushProcessor($processor2);

        $this->assertEquals($processor2, $this->logger->popProcessor());
        $this->assertEquals($processor1, $this->logger->popProcessor());
        $this->logger->popProcessor();
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
            array('addDebug',    Logger::DEBUG),
            array('addInfo',     Logger::INFO),
            array('addWarning',  Logger::WARNING),
            array('addError',    Logger::ERROR),
            array('addCritical', Logger::CRITICAL),
            array('addAlert',    Logger::ALERT),
        );
    }
}
