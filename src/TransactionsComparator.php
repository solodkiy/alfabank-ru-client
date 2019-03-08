<?php
declare(strict_types = 1);


namespace Solodkiy\AlfaBankRu;

use Brick\DateTime\LocalDate;
use function Functional\filter;
use function Functional\first;
use function Functional\map;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Solodkiy\AlfaBankRu\Model\Transaction;
use Solodkiy\AlfaBankRu\Model\TransactionsCollection;
use Solodkiy\AlfaBankRu\Model\TransactionsDiff;
use Solodkiy\AlfaBankRu\Model\TransactionsMatchMode;

final class TransactionsComparator
{
    use LoggerAwareTrait;

    /**
     * @var DescriptionParser
     */
    private $descriptionParser;

    /**
     * TransactionCollectionsDiffer constructor.
     */
    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->descriptionParser = new DescriptionParser();
    }

    public function diff(TransactionsCollection $currentCollection, TransactionsCollection $newCollection) : TransactionsDiff
    {
        return $this->internalDiff($currentCollection, $newCollection, false);
    }

    private function internalDiff(TransactionsCollection $currentCollection, TransactionsCollection $newCollection, bool $softMode) : TransactionsDiff
    {
        $originCurrentCollection = $currentCollection;
        $originNewCollection = $newCollection;

        if ($newCollection->isEmpty()) {
            // Возвращаем emptyDiff. В релаьности это может быть ошибкой, но пока забиваем на это
            return new TransactionsDiff();
        }

        $diff = new TransactionsDiff();
        $currentCollection = $this->trimBeforeDay($currentCollection, $newCollection->getFirstDay());

        foreach ($newCollection as $newTransaction) {
            /** @var Transaction $newTransaction */
            $currentTransaction = $newTransaction->isHold()
                                ? $currentCollection->findByData($newTransaction)
                                : $currentCollection->findByReference($newTransaction->getReference());
            if ($currentTransaction) {
                $currentCollection = $currentCollection->without($currentTransaction->getId());
                $newCollection = $newCollection->without($newTransaction->getId());
                $diff->addSame($currentTransaction->getId(), $newTransaction);
            }
        }

        foreach ($newCollection->filterHold() as $newHoldTransaction) {
            $diff->addNew($newHoldTransaction);
            $newCollection = $newCollection->without($newHoldTransaction->getId());
        }

        // Process disappeared transactions
        /*
        foreach ($currentCollection->filterCommitted() as $transaction) {
            /** @var $transaction CardTransaction *
            if ($transaction->getType()->equals(TransactionType::IN())) {
                $currentCollection = $currentCollection->without($transaction->getId());
                $this->logger->info('skip disappeared in transaction (' . $transaction->getId() . ')');
            }
        }
        */
        $leftCommitted = $currentCollection->filterCommitted();
        if (count($leftCommitted) > 0) {
            foreach ($leftCommitted as $transaction) {
                /** @var $transaction Transaction */
                $reference = $transaction->getReference();
                if ($reference[0] == 'B') {
                    $leftCommitted = $leftCommitted->without($transaction->getId());
                    $this->logger->warning('Try to skip disappeared B-type transactions');
                }
            }
        }
        if (count($leftCommitted) > 0) {
            throw new \RuntimeException('Found disappeared committed transactions!');
        }

        foreach ($newCollection->filterCommitted() as $newCommittedTransaction) {
            $mode = $softMode ? TransactionsMatchMode::SOFT() : TransactionsMatchMode::NORMAL();

            $holdOne = $this->matchHold($currentCollection, $newCommittedTransaction, $mode);
            if ($holdOne) {
                $currentCollection = $currentCollection->without($holdOne->getId());
                $diff->addUpdated($holdOne->getId(), $newCommittedTransaction);
            } else {
                // new Committed
                $diff->addNew($newCommittedTransaction);
            }
            $newCollection = $newCollection->without($newCommittedTransaction->getId());
        }

        if (count($currentCollection)) {
            if ($diff->countNewCommitted() > 0) {
                if (!$softMode) {
                    $this->logger->warning('Found disappeared transaction. Try SoftMode');
                    return $this->internalDiff($originCurrentCollection, $originNewCollection, true);
                } else {
                    if ($diff->countNewCommitted() === 1 && count($currentCollection) === 1) {
                        $this->logger->warning('Found disappeared transaction. Try ExtraSoftMode match');
                        $holdOne = first($currentCollection);
                        $newCommittedTransaction = first($diff->getNewCommitted());
                        $isSame = $this->isTransactionsSame($newCommittedTransaction, $holdOne, TransactionsMatchMode::EXTRA_SOFT());
                        if ($isSame) {
                            $diff->addUpdated($holdOne->getId(), $newCommittedTransaction);
                            $diff->deleteNew($newCommittedTransaction);
                        } else {
                            throw new \RuntimeException('Found disappeared transaction. In ExtraSoftMode!');
                        }
                    } else {
                        throw new \RuntimeException('Found disappeared transaction. In SoftMode!');
                    }
                }
            } else {
                // Считаем эти транзакции за отменённые
                $descriptions = map($currentCollection, function (Transaction $tr) {
                    return $tr->getDescription();
                });
                $this->logger->warning('Delete '.count($currentCollection). ' hold transactions ('.implode(',', $descriptions) . ')');
                foreach ($currentCollection as $disappearedHoldTransaction) {
                    $diff->addDeleted($disappearedHoldTransaction->getId());
                }
            }
        }

        $diff->freeze();
        return $diff;
    }

    private function trimBeforeDay(TransactionsCollection $collection, LocalDate $day): TransactionsCollection
    {
        return $collection->filter(function (Transaction $transaction) use ($day) {
            return $transaction->getDate()->isAfterOrEqualTo($day);
        });
    }

    private function matchHold(TransactionsCollection $storedHoldTransactions, Transaction $committedTransaction, TransactionsMatchMode $mode = null): ?Transaction
    {
        $mode = $mode ?? TransactionsMatchMode::NORMAL();
        $equalAmount = filter($storedHoldTransactions, function (Transaction $holdTransaction) use ($committedTransaction, $mode) {
            return $this->isTransactionsSame($committedTransaction, $holdTransaction, $mode);
        });
        if (count($equalAmount) === 1) {
            return first($equalAmount);
        } elseif (count($equalAmount) > 1) {
            if ($mode->equals(TransactionsMatchMode::NORMAL())) {
                // try hard mode
                $this->logger->warning('Found more than one. Try hard mode');
                return $this->matchHold($storedHoldTransactions, $committedTransaction, TransactionsMatchMode::HARD());
            }
            throw new \RuntimeException('more than one:', $equalAmount, $committedTransaction);
        }
        return null;
    }

    private function isTransactionsSame(Transaction $committed, Transaction $hold, TransactionsMatchMode $mode)
    {
        $typeEquals = $hold->getType()->equals($committed->getType());
        if (!$typeEquals) {
            return false;
        }

        $committedInfo = $this->descriptionParser->extractCommitted($committed->getDescription());
        $holdInfo = $this->descriptionParser->extractHold($hold->getDescription());
        $cardEquals = $committedInfo['card'] == $holdInfo['card'];

        if (!$cardEquals) {
            return false;
        }

        if ($mode->isExtraSoft()) {
            return true;
        }

        if ($committedInfo && $holdInfo && !$mode->isExtraSoft()) {
            if ($committedInfo['code'] && $holdInfo['code'] && !$mode->isSoft()) {
                if ($committedInfo['code'] != $holdInfo['code']) {
                    return false;
                }
            }
            if ($mode->isHard()) {
                if (!$hold->getDate()->isEqualTo($committedInfo['hold_date'])) {
                    return false;
                }
            }

            return (Utils::isMoneyEquals($committedInfo['amount'], $holdInfo['amount']));
        }

        $amountEquals = Utils::isMoneyEquals($hold->getAmount(), $committed->getAmount());
        if ($amountEquals) {
            return true;
        } else {
            if ($mode->isSoft()) {
	        if (is_null($committedInfo)) {
                    return false;
                }
                $sourceCurrency = $committedInfo['amount']->getCurrency();
                $realCurrency = $committed->getAmount()->getCurrency();
                if (!$realCurrency->is($sourceCurrency)) {
                    return Utils::isMoneyNearlyEquals($committed->getAmount(), $hold->getAmount());
                }
            }
            return false;
        }
    }
}
