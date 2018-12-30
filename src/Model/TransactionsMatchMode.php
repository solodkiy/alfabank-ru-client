<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRu\Model;

use MyCLabs\Enum\Enum;

/**
 * @method static self EXTRA_SOFT
 * @method static self SOFT
 * @method static self HARD
 * @method static self NORMAL
 */
final class TransactionsMatchMode extends Enum
{
    private const EXTRA_SOFT = 'extra_soft';
    private const SOFT = 'soft';
    private const NORMAL = 'normal';
    private const HARD = 'hard';

    public function isSoft(): bool
    {
        return $this->getValue() == self::SOFT;
    }

    public function isExtraSoft(): bool
    {
        return $this->getValue() == self::EXTRA_SOFT;
    }

    public function isHard(): bool
    {
        return $this->getValue() == self::HARD;
    }
}
