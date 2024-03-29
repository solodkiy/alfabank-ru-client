<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRuClient;

use Brick\Money\Currency;
use Brick\Money\Money;
use Webmozart\Assert\Assert;

final class AccountData
{
    public const ACCOUNT_TYPE_UNKNOWN      = 'unknown';
    public const ACCOUNT_TYPE_CURRENT      = 'current';
    public const ACCOUNT_TYPE_SAFE         = 'safe';
    public const ACCOUNT_TYPE_GOAL         = 'goal';
    public const ACCOUNT_TYPE_FAMILY       = 'family';
    public const ACCOUNT_TYPE_ALFA_ACCOUNT = 'alfa-account';
    public const ACCOUNT_TYPE_SALARY       = 'salary';
    public const ACCOUNT_TYPE_BROKER       = 'broker';

    /**
     * @var Money
     */
    private $amount;

    private $number;

    private $name;

    private $type;

    /**
     * AccountData constructor.
     * @param Money $balance
     * @param $number
     * @param $name
     * @param $linkId
     * @param $type
     */
    public function __construct(Money $balance, string $number, string $name, string $type)
    {
        $this->amount = $balance;
        $this->number = $number;
        $this->name = $name;

        Assert::oneOf($type, [
            self::ACCOUNT_TYPE_CURRENT,
            self::ACCOUNT_TYPE_SAFE,
            self::ACCOUNT_TYPE_GOAL,
            self::ACCOUNT_TYPE_FAMILY,
            self::ACCOUNT_TYPE_ALFA_ACCOUNT,
            self::ACCOUNT_TYPE_SALARY,
            self::ACCOUNT_TYPE_BROKER,
            self::ACCOUNT_TYPE_UNKNOWN,
        ]);
        $this->type = $type;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBalance(): Money
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->amount->getCurrency();
    }

    public function hasHistory(): bool
    {
        return $this->type !== self::ACCOUNT_TYPE_GOAL;
    }
}
