<?php
declare(strict_types = 1);


namespace Solodkiy\AlfaBankRu\Model;

use ArrayIterator;
use Brick\DateTime\LocalDate;
use function Functional\filter;
use function Functional\first;
use function Functional\reduce_left;
use IteratorAggregate;
use Solodkiy\Freeze\FreezeTrait;
use Solodkiy\Uuid\Uuid;
use Traversable;

final class TransactionsCollection implements IteratorAggregate, \Countable
{
    use FreezeTrait;

    /**
     * @var Transaction[]
     */
    private $transactions = [];

    /**
     * HoldTransactionsCollection constructor.
     * @param Transaction[] $transactions
     */
    public function __construct(array $transactions = [])
    {
        foreach ($transactions as $transaction) {
            $this->transactions[] = $transaction;
        }
    }

    public function addTransaction(Transaction $transaction)
    {
        $this->transactions[] = $transaction;
    }

    public function findByData(Transaction $needle): ?Transaction
    {
        return first($this, function (Transaction $transaction) use ($needle) {
            return $transaction->isEqualByData($needle);
        });
    }

    public function findByReference(string $reference): ?Transaction
    {
        return first($this, function (Transaction $transaction) use ($reference) {
            return $transaction->getReference() === $reference;
        });
    }



    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return new ArrayIterator($this->transactions);
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->transactions);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function filterHold(): self
    {
        return $this->filter(function (Transaction $transaction) {
            return $transaction->isHold();
        });
    }
    public function filterCommitted(): self
    {
        return $this->filter(function (Transaction $transaction) {
            return !$transaction->isHold();
        });
    }

    public function getFirstDay(): ?LocalDate
    {
        return reduce_left($this, function (Transaction $transaction, $index, $collection, $currentResult) {
            $day = $transaction->getDate();
            if (is_null($currentResult)) {
                return $day;
            } else {
                return ($day->isBefore($currentResult) ? $day : $currentResult);
            }
        });
    }

    public function filter(callable $lambda): self
    {
        $newCollection = new self(filter($this, $lambda));
        $newCollection->freeze();
        return $newCollection;
    }

    public function without(Uuid $id): self
    {
        return $this->filter(function (Transaction $transaction) use ($id) {
            return !$transaction->getId()->equals($id);
        });
    }
}
