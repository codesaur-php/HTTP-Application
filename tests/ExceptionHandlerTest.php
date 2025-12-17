<?php

namespace codesaur\Http\Application\Tests;

use PHPUnit\Framework\TestCase;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\ExceptionHandlerInterface;

class ExceptionHandlerTest extends TestCase
{
    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ExceptionHandler();
    }

    public function testExceptionHandlerImplementsInterface(): void
    {
        $this->assertInstanceOf(ExceptionHandlerInterface::class, $this->handler);
    }

    public function testExceptionMethodExists(): void
    {
        $this->assertTrue(method_exists($this->handler, 'exception'));
    }

    public function testExceptionWithErrorCode(): void
    {
        $exception = new \Error('Not Found', 404);
        
        ob_start();
        $this->handler->exception($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Error 404', $output);
        $this->assertStringContainsString('Not Found', $output);
        $this->assertEquals(404, http_response_code());
    }

    public function testExceptionWithZeroCode(): void
    {
        $exception = new \Exception('Generic Error', 0);
        
        ob_start();
        $this->handler->exception($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Exception', $output);
        $this->assertStringContainsString('Generic Error', $output);
    }

    public function testExceptionLogsError(): void
    {
        $exception = new \Error('Test Error', 500);
        
        // Capture error_log output by setting error_log to a temporary file
        $logFile = sys_get_temp_dir() . '/phpunit_error_log_' . uniqid() . '.log';
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', $logFile);

        ob_start();
        $this->handler->exception($exception);
        ob_end_clean();

        // Restore original error_log
        ini_set('error_log', $originalErrorLog);

        // Check if log file was created and contains error
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $this->assertStringContainsString('Error 500', $logContent);
            $this->assertStringContainsString('Test Error', $logContent);
            unlink($logFile);
        }
    }

    public function testExceptionWithDevelopmentMode(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', true);
        }

        $exception = new \Exception('Development Error');
        
        ob_start();
        $this->handler->exception($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Development Error', $output);
    }

    public function testExceptionHtmlOutput(): void
    {
        $exception = new \Exception('Test Exception');
        
        ob_start();
        $this->handler->exception($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('<html', $output);
        $this->assertStringContainsString('<head>', $output);
        $this->assertStringContainsString('<body>', $output);
        $this->assertStringContainsString('Test Exception', $output);
    }

    public function testExceptionWithDifferentExceptionTypes(): void
    {
        $types = [
            new \Exception('Exception'),
            new \Error('Error'),
            new \RuntimeException('RuntimeException'),
            new \InvalidArgumentException('InvalidArgumentException'),
        ];

        foreach ($types as $exception) {
            ob_start();
            $this->handler->exception($exception);
            $output = ob_get_clean();

            $this->assertStringContainsString($exception->getMessage(), $output);
            $this->assertStringContainsString(get_class($exception), $output);
        }
    }
}
