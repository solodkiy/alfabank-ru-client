<?php
declare(strict_types=1);

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverDimension;
use Solodkiy\SmartSeleniumDriver\SmartSeleniumDriver;

class SimpleLogger implements \Psr\Log\LoggerInterface
{
    public function emergency($message, array $context = array())
    {
        $this->log('emerg', $message);
    }
    public function alert($message, array $context = array())
    {
        $this->log('alert', $message);
    }
    public function critical($message, array $context = array())
    {
        $this->log('crit', $message);
    }
    public function error($message, array $context = array())
    {
        $this->log('err', $message);
    }
    public function warning($message, array $context = array())
    {
        $this->log('warn', $message);
    }
    public function notice($message, array $context = array())
    {
        $this->log('notice', $message);
    }
    public function info($message, array $context = array())
    {
        $this->log('info', $message);
    }
    public function debug($message, array $context = array())
    {
        $this->log('debug', $message);
    }
    public function log($level, $message, array $context = array())
    {
        echo '['.$level . '] '. $message ."\n";
    }
};

function createWebDriver(string $host, int $port) : SmartSeleniumDriver
{
    $capabilities = DesiredCapabilities::chrome();
    $url = 'http://' . $host .':' . $port . '/wd/hub';
    $driver = SmartSeleniumDriver::create($url, $capabilities, 5000);

    $window = new WebDriverDimension(1024, 768);
    $driver->manage()->window()->setSize($window);
    return $driver;
}
