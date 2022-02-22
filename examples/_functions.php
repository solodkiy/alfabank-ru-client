<?php
declare(strict_types=1);

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverPlatform;
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

function createWebDriver(string $host, int $port) : RemoteWebDriver
{
    $options = new ChromeOptions();
    $options->addArguments(array(
        '--user-agent=' . 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.80 Safari/537.36'
    ));
    $options->addArguments(['--proxy-server=http://localhost:18880']);
    $capabilities = DesiredCapabilities::chrome();

    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

    //$capabilities = DesiredCapabilities::firefox();

    $url = 'http://' . $host .':' . $port . '/wd/hub';
    $driver = RemoteWebDriver::create($url, $capabilities, 5000);

    //$window = new WebDriverDimension(1024, 768);
    $window = new WebDriverDimension(2560, 1600);
    $driver->manage()->window()->setSize($window);
    return $driver;
}
