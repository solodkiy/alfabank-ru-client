<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRu\Model;

use Brick\DateTime\LocalDate;
use Brick\Money\Money;
use Solodkiy\Freeze\FreezeTrait;
use Solodkiy\Uuid\Uuid;

final class Transaction
{
    use FreezeTrait;

    /**
     * @var Uuid
     */
    private $id;

    /**
     * @var LocalDate
     */
    private $date;

    /**
     * @var string
     */
    private $account;

    /**
     * @var TransactionType
     */
    private $type;

    /**
     * @var Money
     */
    private $amount;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $reference = null;

    /**
     * CardTransaction constructor.
     */
    public function __construct()
    {
        $this->id = Uuid::generate();
    }


    public function isHold(): bool
    {
        return is_null($this->reference);
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    /**
     * @return LocalDate
     */
    public function getDate(): LocalDate
    {
        return $this->date;
    }

    /**
     * @return TransactionType
     */
    public function getType(): TransactionType
    {
        return $this->type;
    }

    /**
     * @return Money
     */
    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAccount(): string
    {
        return $this->account;
    }

    public function hasReference(): bool
    {
        return !is_null($this->reference);
    }


    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     *
    public function jsonSerialize()
    {
        $result = [];
        return [
            'date' => $this->date,
            'account' => $this->account,
            'type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
            'reference' => $this->reference,
            'is_hold' => $this->isHold,
        ];
    }/


    /**
     * @param LocalDate $date
     */
    public function setDate(LocalDate $date): void
    {
        $this->assertNotFrozen();
        $this->date = $date;
    }

    /**
     * @param string $account
     */
    public function setAccount(string $account): void
    {
        $this->assertNotFrozen();
        $this->account = $account;
    }

    /**
     * @param TransactionType $type
     */
    public function setType(TransactionType $type): void
    {
        $this->assertNotFrozen();
        $this->type = $type;
    }

    /**
     * @param Money $amount
     */
    public function setAmount(Money $amount): void
    {
        $this->assertNotFrozen();
        $this->amount = $amount;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->assertNotFrozen();
        $this->description = $description;
    }

    /**
     * @param Uuid $id
     */
    public function setId(Uuid $id): void
    {
        $this->assertNotFrozen();
        $this->id = $id;
    }

    public function setCommitted(string $reference)
    {
        $this->assertNotFrozen();
        $this->reference = $reference;
    }

    /**
     * @return Uuid
     */
    public function getId(): Uuid
    {
        return $this->id;
    }

    public function isEqualByData(Transaction $that)
    {
        return $this->hashData() === $that->hashData();
    }

    private function hashData(): string
    {
        $hash = [
            'date' => (string)$this->getDate(),
            'amount' => (string)$this->getAmount(),
            'type' => (string)$this->getType(),
            'reference' => $this->getReference(),
            'description' => $this->getDescription(),
            'account' => $this->getAccount(),
        ];
        return md5(json_encode($hash));
    }
}
