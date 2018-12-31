<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRu;

use Brick\Money\Currency;
use Brick\Money\Money;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use function Functional\first;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Solodkiy\AlfaBankRu\Model\AccountData;
use Solodkiy\SmartSeleniumDriver\SmartSeleniumDriver;

class AlfaBankClient
{
    use LoggerAwareTrait;

    /**
     * @var RemoteWebDriver
     */
    private $driver;

    /**
     * @var string
     */
    private $login;

    /**
     * @var string
     */
    private $pass;

    /**
     * AlfaBankClient constructor.
     * @param SmartSeleniumDriver $driver
     * @param string $login
     * @param string $pass
     */
    public function __construct(SmartSeleniumDriver $driver, string $login, string $pass)
    {
        $this->logger = new NullLogger();
        $this->driver = $driver;
        $this->login = $login;
        $this->pass = $pass;
    }

    public function auth()
    {
        if ($this->isAuth()) {
            return;
        }

        $this->logger->debug('Try auth...');

        $url = 'https://click.alfabank.ru/login/';
        $this->driver->get($url);

        $loginElement = $this->driver->findElement(WebDriverBy::name('username'));
        $loginElement->click();
        $this->driver->getKeyboard()->sendKeys($this->login);

        $this->driver->findElement(WebDriverBy::name('password'))->click();
        $this->driver->getKeyboard()->sendKeys($this->pass);
        $this->driver->getKeyboard()->pressKey(WebDriverKeys::ENTER);

        $x = WebDriverBy::linkText('Счета');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($x));
        $this->logger->debug('Success auth');
    }

    public function getAccountsList()
    {
        $this->auth();
        $this->goToAccountsPage();

        $html = $this->driver->findElement(WebDriverBy::id('pt2:pfufgl21'))->getAttribute('innerHTML');

        return $this->extractAccountFromHtml($html);
    }

    private function isOnAccountsPage()
    {
        $header = WebDriverBy::className('x22s');
        try {
            return ($this->driver->findElement($header)->getText() == 'Список счетов');
        } catch (NoSuchElementException $e) {
            return false;
        }
    }

    private function goToAccountsPage()
    {
        if ($this->isOnAccountsPage()) {
            return;
        }
        $this->logger->debug('Go to accounts page...');
        $accountsLink = WebDriverBy::linkText('Счета');
        $this->driver->findElement($accountsLink)->click();
        $header = WebDriverBy::className('x22s');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementTextIs($header, 'Список счетов'));
    }

    /**
     * @param $html
     * @return AccountData[]
     */
    private function extractAccountFromHtml($html): array
    {
        $result = [];
        for ($i = 0; $i <= 20; $i++) {
            $accountData = $this->extractAccountData($html, $i);
            if (!$accountData) {
                break;
            }
            $result[] = $accountData;
        }

        return $result;
    }

    private function extractAccountData($html, $i): ?AccountData
    {
        $map = [
            'sum'       => '~id="pt2:i1:{I}:s10:cf12".+?class="summ">(.+?)</span>~s',
            'currency'  => '~id="pt2:i1:{I}:s10:cf13".+?class="currency">(.+?)</span>~s',
            'name'      => '~id="pt2:i1:{I}:s10:cil4".+?<span.+?>(.+?)</span>~s',
            'number'    => '~id="pt2:i1:{I}:s10:cf11".+?>(\d+?)</span>~s',
            'type'      => '~id="pt2:i1:{I}:s10:cf5".+?class="x24e">(.+?)</span>~s'
        ];
        $map = array_map(function ($regexp) use ($i) {
            return str_replace('{I}', $i, $regexp);
        }, $map);

        try {
            $sumString = $this->extractFirstMatch($map['sum'], $html);
            $currency = $this->extractFirstMatch($map['currency'], $html);
            $name = $this->extractFirstMatch($map['name'], $html);
            $number = $this->extractFirstMatch($map['number'], $html);

            $floatMoney = $this->convertSumStringToFloat($sumString);
            $currency = $this->createCurrencyFromText($currency);
            $amount = Money::of($floatMoney, $currency);

            return new AccountData(
                $amount,
                $number,
                $name,
                'pt2:i1:' . $i . ':s10:cil4',
                $this->convertStringTypeToId($this->extractFirstMatch($map['type'], $html))
            );
        } catch (\RuntimeException $e) {
            $this->logger->debug('Extract data was unsuccessful: '. $e->getMessage());
            return null;
        }
    }

    private function convertSumStringToFloat(string $sum)
    {
        $sum = str_replace(' ', '', $sum);
        return (float)$sum;
    }

    /**
     * @param string $text
     * @return Currency
     * @throws \Brick\Money\Exception\UnknownCurrencyException
     */
    private function createCurrencyFromText(string $text): Currency
    {
        $map = [
            'р.' => 'RUB',
            '$'  => 'USD',
            '£'  => 'GBP',
            '€'  => 'EUR',
            '₣'  => 'CHF',
        ];

        if (!isset($map[$text])) {
            throw new \RuntimeException('Unknown currency "'.$text.'"');
        }
        return Currency::of($map[$text]);
    }

    private function convertStringTypeToId(string $typeString)
    {
        $map = [
            'Текущий счёт' => AccountData::ACCOUNT_TYPE_CURRENT,
            'Мой сейф'     => AccountData::ACCOUNT_TYPE_SAFE,
            'Мои цели'     => AccountData::ACCOUNT_TYPE_GOAL,
        ];

        if (isset($map[$typeString])) {
            return $map[$typeString];
        } else {
            throw new \RuntimeException('Unknown account type "'. $typeString . '"');
        }
    }

    /**
     * @param $regex
     * @param $string
     * @return mixed
     */
    private function extractFirstMatch($regex, $string)
    {
        if (preg_match($regex, $string, $m)) {
            return $m[1];
        } else {
            throw new \RuntimeException('Regex '.$regex.' not matched');
        }
    }

    private function isAuth()
    {
        try {
            $this->driver->findElement(WebDriverBy::linkText('Выход'));
        } catch (NoSuchElementException $e) {
            return false;
        }
        return true;
    }


    public function downloadAccountHistory($number): string
    {
        $this->auth();
        $this->goToAccountsPage();
        $accounts = $this->getAccountsList();
        $accountData = first($accounts, function (AccountData $accountData) use ($number) {
            return ($accountData->getNumber() == $number);
        });

        $this->logger->debug('Go to account page ' . $number . '...');
        $this->driver->findElement(WebDriverBy::id($accountData->getLinkId()))->click();

        $this->logger->debug('Go to history page...');
        $x = WebDriverBy::linkText('Показать подробную выписку');
        $this->driver->wait()->until(WebDriverExpectedCondition::visibilityOfElementLocated($x));
        $this->driver->findElement($x)->click();


        $x = WebDriverBy::id('pt1:periodSwitch::item-2');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($x));
        $this->driver->findElement($x)->click();
        $this->driver->manage()->timeouts()->implicitlyWait(1);
        $this->driver->findElement(WebDriverBy::id('pt1:showButton::button'))->click();

        $this->driver->clearDownloadDir();

        $downloadLink = WebDriverBy::id('pt1:downloadCSVLink');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($downloadLink));
        $this->driver->findElement($downloadLink)->click();

        $this->logger->debug('Wait for start downloading..');
        $fileName = $this->waitForFile();
        $this->logger->debug('Downloading csv..');
        $content = $this->driver->getDownloadedFileByName($fileName);

        // Fix charset
        $content = iconv('windows-1251', 'UTF-8', $content);
        return $content;
    }

    private function waitForFile()
    {
        $end = microtime(true) + 5;
        while (microtime(true) < $end) {
            $files = $this->driver->getDownloadedFiles();
            if (count($files) > 0) {
                return first($files);
            }
            usleep(100 * 1000);
        }
        throw new \RuntimeException('Wait for file timeout!');
    }


    public function __destruct()
    {
        $this->driver->quit();
    }
}
