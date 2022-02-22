<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRuClient;

use Brick\Math\Exception\MathException;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class AlfaBankWebClient
{
    use LoggerAwareTrait;

    private RemoteWebDriver $driver;

    private string $login;

    private string $pass;

    /**
     * @var InteractionTrapInterface
     */
    private $interactionTrap;

    /**
     * AlfaBankClient constructor.
     * @param RemoteWebDriver $driver
     * @param string $login
     * @param string $pass
     */
    public function __construct(RemoteWebDriver $driver, string $login, string $pass, InteractionTrapInterface $interactionTrap)
    {
        $this->logger = new NullLogger();
        $this->driver = $driver;
        $this->login = $login;
        $this->pass = $pass;
        $this->interactionTrap = $interactionTrap;
    }

    /**
     * @throws AlfaBankClientException
     * @throws NoSuchElementException
     * @throws TimeOutException
     */
    public function webAuth(): void
    {
        if ($this->isWebAuthorized()) {
            return;
        }

        $this->logger->debug('Trying login to web.alfabank.ru...');

        $url = 'https://web.alfabank.ru/dashboard/';
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
        $this->checkLoginFormErrors();

        $smsElements = $this->driver->findElements(WebDriverBy::cssSelector('input[inputmode=numeric]'));
        if (count($smsElements) > 0) {
            $smsElements[0]->click();

            $smsCode = $this->interactionTrap->waitForSms();
            if (!$smsCode) {
                throw new AlfaBankClientException("Couldn't get sms code");
            }
            $this->driver->getKeyboard()->sendKeys($smsCode);
            $this->driver->takeScreenshot('/tmp/3.png');
            sleep(1);

            // todo check if sms correct?
        }

        try {
            $historyLinkSelector = WebDriverBy::linkText('История');
            $this->driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($historyLinkSelector));
        } catch (NoSuchElementException $e) {
            $this->driver->takeScreenshot('/tmp/4.png');
            throw new AlfaBankClientException('Unknown login error');
        }

        // Get headers
        $this->driver->findElement($historyLinkSelector)->click();
        sleep(1);
        $this->logger->debug('Success login');
    }


    /**
     * @throws AlfaBankClientException
     */
    private function checkLoginFormErrors()
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

    /**
     * @return AccountData[]
     * @throws AlfaBankClientException
     * @throws NoSuchElementException
     * @throws TimeOutException
     * @throws \RuntimeException
     */
    public function getAccountsList(): array
    {
        $this->webAuth();
        $html = $this->driver->findElement(WebDriverBy::cssSelector('body'))->getAttribute('innerHTML');

        return $this->extractAccountFromHtml($html);
    }

    private function fixCurrencyCode(string $currency): string
    {
        if ($currency === 'RUR') {
            return 'RUB';
        }
        return $currency;
    }

    /**
     * @param string $html
     * @return AccountData[]
     * @throws MathException
     * @throws UnknownCurrencyException
     * @throws \RuntimeException
     */
    private function extractAccountFromHtml(string $html): array
    {
        if (preg_match('/window\.__main\((.+)\);/', $html, $m)) {
            $jsonString = $m[1];
            $data = json_decode($jsonString, true);
            if (!$data) {
                throw new \RuntimeException('Cannot read state json');
            }
            $settings = $data['state']['layoutData']['balance']['settings'] ?? null;
            if (is_null($settings)) {
                throw new \RuntimeException('Cannot read accounts list in json');
            }
            $result = [];
            foreach ($settings as $settingsData) {
                foreach ($settingsData['accounts'] as $accountData) {
                    $result[] = $this->extractAccountData($accountData);
                }
            }
            return $result;

        }

        throw new \RuntimeException('State json not found in page html');
    }

    /**
     * @param array $data
     * @return AccountData
     * @throws MathException
     * @throws UnknownCurrencyException
     */
    private function extractAccountData(array $data): AccountData
    {
        $amount = Money::ofMinor(
            $data['amount']['value'],
            Currency::of($this->fixCurrencyCode($data['amount']['currency']))
        );

        return new AccountData(
            $amount,
            $data['number'],
            $data['description'],
            $this->extractType($data['type'])
        );
    }

    private function extractType($type): string
    {
        $map = [
            'EE' => AccountData::ACCOUNT_TYPE_CURRENT,
            'SE' => AccountData::ACCOUNT_TYPE_FAMILY,
            'FY' => AccountData::ACCOUNT_TYPE_ALFA_ACCOUNT,
            'EH' => AccountData::ACCOUNT_TYPE_SALARY,
            'GK' => AccountData::ACCOUNT_TYPE_BROKER,
        ];

        return $map[$type] ?? AccountData::ACCOUNT_TYPE_UNKNOWN;
    }

    private function isWebAuthorized()
    {
        try {
            $this->driver->findElement(WebDriverBy::linkText('Выход'));
        } catch (NoSuchElementException $e) {
            return false;
        }
        return true;
    }

    public function __destruct()
    {
        $this->driver->quit();
    }


}
