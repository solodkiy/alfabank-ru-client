<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRuClient;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use DateTimeImmutable;
use DateTimeZone;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Solodkiy\SmartSeleniumDriver\Exceptions\SmartSeleniumCommandError;
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
     * @var ?callable
     */
    private $getSmsCodeFunction;

    /**
     * AlfaBankClient constructor.
     * @param SmartSeleniumDriver $driver
     * @param string $login
     * @param string $pass
     * @param ?callable $getSmsCodeFunction
     */
    public function __construct(SmartSeleniumDriver $driver, string $login, string $pass, ?callable $getSmsCodeFunction = null)
    {
        $this->logger = new NullLogger();
        $this->driver = $driver;
        $this->login = $login;
        $this->pass = $pass;
        $this->getSmsCodeFunction = $getSmsCodeFunction;
    }

    /**
     * @throws AlfaBankClientException
     * @throws NoSuchElementException
     * @throws TimeOutException
     */
    public function auth()
    {
        if ($this->isAuth()) {
            return;
        }

        $this->logger->debug('Trying auth...');

        $url = 'https://click.alfabank.ru/';
        $this->driver->get($url);

        $loginElement = $this->driver->findElement(WebDriverBy::cssSelector('input[type=text][aria-label="Логин"]'));
        $loginElement->click();
        $this->driver->getKeyboard()->sendKeys($this->login);
        $this->driver->getKeyboard()->pressKey(WebDriverKeys::ENTER);

        $passwordElement = WebDriverBy::cssSelector('input[type=password][aria-label="Пароль"]');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($passwordElement));

        $this->driver->findElement($passwordElement)->click();
        $this->driver->getKeyboard()->sendKeys($this->pass);
        $this->driver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
        sleep(1);
        $this->checkAuthErrors();;

        $smsElements = $this->driver->findElements(WebDriverBy::cssSelector('input[inputmode=numeric]'));
        if (count($smsElements) > 0) {
            $smsElements[0]->click();
            $smsCode = $this->getSmsCode();
            if (!$smsCode) {
                throw new AlfaBankClientException("Couldn't get sms code");
            }
            $this->driver->getKeyboard()->sendKeys($smsCode);
            sleep(1);
        }

        try {
            $accountsLinkSelector = WebDriverBy::linkText('Счета');
            $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($accountsLinkSelector));
        } catch (NoSuchElementException $e) {
            //$this->driver->takeScreenshot('/tmp/1.png');
            throw new AlfaBankClientException('Auth error');
        }
        $this->logger->debug('Success auth');
    }

    /**
     * @throws AlfaBankClientException
     */
    private function checkAuthErrors()
    {
        $messages = $this->driver->findElements(WebDriverBy::className('notification__content'));
        if ($messages) {
            $errorText = '';
            foreach ($messages as $message) {
                if ($message->getText() === 'Учётная запись временно заблокирована. Чтобы восстановить доступ, нажмите "Восстановить логин и пароль".') {
                    throw new AlfaBankClientException('Account locked', AlfaBankClientException::ACCOUNT_LOCKED);
                }

                if (str_replace("\n", '', $message->getText()) === 'Некорректные данные. Пожалуйста, попробуйте ещё раз.') {
                    throw new AlfaBankClientException('Invalid password', AlfaBankClientException::INVALID_PASSWORD);
                }
                $errorText = $message->getText();
            }
            throw new AlfaBankClientException('Auth error: ' . $errorText, AlfaBankClientException::UNKNOWN_AUTH_ERROR);
        }
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
        $this->logger->debug('Going to accounts page...');
        $accountsLink = WebDriverBy::linkText('Счета');
        $this->driver->findElement($accountsLink)->click();
        $header = WebDriverBy::className('x22s');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementTextIs($header, 'Список счетов'));
    }

    /**
     * @param $html
     * @return AccountData[]
     */
    private function extractAccountFromHtml(string $html): array
    {
        $pattern = ':db2" class="x257 xpi"';
        $accountsCount = substr_count($html, $pattern);

        $result = [];
        for ($i = 0; $i < $accountsCount; $i++) {
            try {
                $accountData = $this->extractAccountData($html, $i);
                $result[] = $accountData;
            } catch (\RuntimeException $e) {
                $this->logger->warning('Extract data was unsuccessful: '. $e->getMessage());
                continue;
            }
        }

        return $result;
    }

    /**
     * @param $html
     * @param $i
     * @return AccountData
     * @throws \RuntimeException
     */
    private function extractAccountData($html, $i): AccountData
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

        $sumString = Utils::extractFirstMatch($map['sum'], $html);
        $currencySymbol = Utils::extractFirstMatch($map['currency'], $html);
        $name = Utils::extractFirstMatch($map['name'], $html);
        $number = Utils::extractFirstMatch($map['number'], $html);

        $decimalMoney = $this->convertSumStringToDecimal($sumString);
        $currency = $this->createCurrencyFromText($currencySymbol);

        try {
            $amount = Money::of($decimalMoney, $currency);
        } catch (MathException $e) {
            throw new \LogicException('Unexpected money value: ' . var_export($decimalMoney, true) . ', ' . var_export($currency, true));
        }

        return new AccountData(
            $amount,
            $number,
            $name,
            'pt2:i1:' . $i . ':s10:cil4',
            $this->convertStringTypeToId(Utils::extractFirstMatch($map['type'], $html))
        );
    }

    /**
     * @param string $sum
     * @return BigDecimal
     * @throws MathException
     */
    private function convertSumStringToDecimal(string $sum) : BigDecimal
    {
        $sum = str_replace(' ', '', $sum);
        return BigDecimal::of($sum);
    }

    /**
     * @param string $text
     * @return Currency
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
            throw new \DomainException('Unknown currency symbol "'.$text.'"');
        }
        try {
            return Currency::of($map[$text]);
        } catch (UnknownCurrencyException $e) {
            throw new \LogicException($e->getMessage(), 0, $e);
        }
    }

    private function convertStringTypeToId(string $typeString)
    {
        $map = [
            'Текущий счёт'              => AccountData::ACCOUNT_TYPE_CURRENT,
            'Мой сейф'                  => AccountData::ACCOUNT_TYPE_SAFE,
            'Мои цели'                  => AccountData::ACCOUNT_TYPE_GOAL,
            'Семейный'                  => AccountData::ACCOUNT_TYPE_FAMILY,
            'Альфа-Счет'                => AccountData::ACCOUNT_TYPE_ALFA_ACCOUNT,
            'Текущий зарплатный счёт'   => AccountData::ACCOUNT_TYPE_SALARY,
            'Брокерский счёт'           => AccountData::ACCOUNT_TYPE_BROKER,
        ];

        if (isset($map[$typeString])) {
            return $map[$typeString];
        } else {
            throw new \RuntimeException('Unknown account type "'. $typeString . '"');
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


    /**
     * @param $number
     * @return string
     * @throws NoSuchElementException
     * @throws TimeOutException
     * @throws SmartSeleniumCommandError
     * @throws AlfaBankClientException
     */
    public function downloadAccountHistory($number): string
    {
        $this->auth();
        $this->goToAccountsPage();
        $accounts = $this->getAccountsList();
        $this->logger->debug('Found ' . count($accounts) . ' accounts');
        $accountData = Utils::first($accounts, function (AccountData $accountData) use ($number) {
            return ($accountData->getNumber() == $number);
        });

        $this->logger->debug('Going to account page ' . $number . '...');
        try {
            $this->driver->findElement(WebDriverBy::id($accountData->getLinkId()))->click();
        } catch (\Exception $e) {
            $this->logger->debug('Clicking error. Trying again...');
            $this->driver->findElement(WebDriverBy::id($accountData->getLinkId()))->click();
        }

        $this->logger->debug('Going to history page...');
        $x = WebDriverBy::linkText('Показать подробную выписку');
        $this->driver->wait()->until(WebDriverExpectedCondition::visibilityOfElementLocated($x));
        $this->driver->findElement($x)->click();


        $x = WebDriverBy::id('pt1:periodSwitch::item-2');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($x));
        $this->driver->findElement($x)->click();
        $this->driver->manage()->timeouts()->implicitlyWait(1);
        $this->driver->findElement(WebDriverBy::id('pt1:showButton::button'))->click();

        $this->logger->debug('Validating date range...');
        $realEndDate = $this->driver->findElement(WebDriverBy::id('pt1:id2::content'))->getAttribute('value');
        $expectedEndDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format('d.m.Y');
        if ($expectedEndDate !== $realEndDate) {
            $this->logger->error('Incorrect date range. Expected ' . $expectedEndDate . ', got: '. $realEndDate);
            throw new AlfaBankClientException('Incorrect date range. Expected ' . $expectedEndDate . ', got: '. $realEndDate);
        }

        $this->driver->clearDownloadDir();

        $downloadLink = WebDriverBy::id('pt1:downloadCSVLink');
        $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($downloadLink));
        $this->driver->findElement($downloadLink)->click();

        $this->logger->debug('Waiting for start download..');
        $fileName = $this->waitForFile();
        $this->logger->debug('Downloading csv..');
        $content = $this->driver->getDownloadedFileByName($fileName);

        // Fix charset
        $content = iconv('windows-1251', 'UTF-8', $content);
        return $content;
    }

    private function waitForFile() : string
    {
        $end = microtime(true) + 5;
        while (microtime(true) < $end) {
            $files = $this->driver->getDownloadedFiles();
            if (count($files) > 0) {
                return (string)Utils::first($files);
            }
            usleep(100 * 1000);
        }
        throw new \RuntimeException('Wait for file timeout!');
    }


    public function __destruct()
    {
        $this->driver->quit();
    }

    private function getSmsCode(): ?string
    {
        $func = $this->getSmsCodeFunction;
        return $func ? (string)$func() : null;
    }
}
