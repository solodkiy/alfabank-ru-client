<?php
declare(strict_types = 1);


namespace Solodkiy\AlfaBankRu\Model;

use Solodkiy\Freeze\FreezeTrait;
use Solodkiy\Uuid\Uuid;

final class TransactionsDiff
{
    use FreezeTrait;

    private $newHold = [];
    private $newCommitted = [];

    private $deletedIds = [];

    private $sameCount = 0;

    private $updated = [];

    public function addSame(Uuid $currentId, Transaction $newTransaction)
    {
        $this->sameCount++;
    }

    public function addDeleted(Uuid $currentId)
    {
        $this->deletedIds[] = $currentId;
    }

    public function addNew(Transaction $newTransaction)
    {
        if ($newTransaction->isHold()) {
            $this->newHold[] = $newTransaction;
        } else {
            $this->newCommitted[] = $newTransaction;
        }
    }

    public function deleteNew(Transaction $newTransaction)
    {
        if ($newTransaction->isHold()) {
            foreach ($this->newHold as $index => $transaction) {
                if ($newTransaction->isEqualByData($transaction)) {
                    unset($this->newHold[$index]);
                    return;
                }
            }
        } else {
            foreach ($this->newCommitted as $index => $transaction) {
                if ($newTransaction->isEqualByData($transaction)) {
                    unset($this->newCommitted[$index]);
                    return;
                }
            }
        }
    }

    public function addUpdated(Uuid $currentId, Transaction $newTransaction)
    {
        $this->updated[] = [$currentId, $newTransaction];
    }

    public function countNewHold(): int
    {
        return count($this->newHold);
    }

    public function countNewCommitted(): int
    {
        return count($this->newCommitted);
    }

    public function countUpdated(): int
    {
        return count($this->updated);
    }

    public function countDeleted(): int
    {
        return count($this->deletedIds);
    }

    public function stat(): array
    {
        return [
            'new_hold'      => $this->countNewHold(),
            'new_committed' => $this->countNewCommitted(),
            'updated'       => $this->countUpdated(),
            'deleted'       => $this->countDeleted(),
        ];
    }

    /**
     * @return Transaction[]
     */
    public function getNewHold(): array
    {
        return $this->newHold;
    }

    /**
     * @return Transaction[]
     */
    public function getNewCommitted(): array
    {
        return $this->newCommitted;
    }

    /**
     * @return array
     */
    public function getDeletedIds(): array
    {
        return $this->deletedIds;
    }

    /**
     * @return array
     */
    public function getUpdated(): array
    {
        return $this->updated;
    }

    public function isEmpty(): bool
    {
        return $this->countDeleted() == 0
            && $this->countUpdated() == 0
            && $this->countNewCommitted() == 0
            && $this->countNewHold() == 0;
    }
}
