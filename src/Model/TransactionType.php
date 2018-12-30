<?php
declare(strict_types = 1);


namespace Solodkiy\AlfaBankRu\Model;

use MyCLabs\Enum\Enum;

/**
 * Class TransactionType
 * @package Solodkiy\Money
 * @method static self IN
 * @method static self OUT
 */
final class TransactionType extends Enum implements \JsonSerializable
{
    private const IN = 'in';
    private const OUT = 'out';

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->getValue();
    }
}
