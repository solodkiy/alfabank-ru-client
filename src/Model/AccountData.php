<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRu\Model;

use Brick\Money\Money;

final class AccountData
{
    const ACCOUNT_TYPE_CURRENT = 'current';
    const ACCOUNT_TYPE_SAFE = 'safe';
    const ACCOUNT_TYPE_GOAL = 'goal';

    /**
     * @var Money
     */
    private $amount;

    private $number;

    private $name;

    private $linkId;

    private $type;

    /**
     * AccountData constructor.
     * @param Money $amount
     * @param $number
     * @param $name
     * @param $linkId
     * @param $type
     */
    public function __construct(Money $amount, string $number, string $name, string $linkId, string $type)
    {
        $this->amount = $amount;
        $this->number = $number;
        $this->name = $name;
        $this->linkId = $linkId;
        $this->type = $type;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getLinkId(): string
    {
        return $this->linkId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
