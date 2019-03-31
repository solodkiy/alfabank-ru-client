<?php
declare(strict_types = 1);

namespace Solodkiy\AlfaBankRu;

use Brick\DateTime\LocalDate;
use Brick\DateTime\Parser\DateTimeParseException;
use Brick\Money\Money;
use Solodkiy\AlfaBankRu\Model\Transaction;
use Solodkiy\AlfaBankRu\Model\TransactionsCollection;
use Solodkiy\AlfaBankRu\Model\TransactionType;
use Webmozart\Assert\Assert;

class CsvLoader
{
    /**
     * @param string|resource $fileResource
     * @return TransactionsCollection
     */
    public function loadFromFile($fileResource): TransactionsCollection
    {
        if (is_string($fileResource) && file_exists($fileResource)) {
            $fileResource = Utils::smartFileHandleCreate($fileResource);
        }
        return $this->loadFromResource($fileResource);
    }

    /**
     * @param string $csvContent
     * @return TransactionsCollection
     */
    public function loadFromString(string $csvContent): TransactionsCollection
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);
        return $this->loadFromResource($stream);
    }

    private function loadFromResource($resource): TransactionsCollection
    {
        Assert::resource($resource);
        // head
        fgetcsv($resource, 0, ';');

        $collection = new TransactionsCollection();
        while (true) {
            $line = fgetcsv($resource, 0, ';');
            if (!$line) {
                break;
            }
            $transaction = $this->createTransaction($line);
            $collection->addTransaction($transaction);
        }
        $collection->freeze();

        return $collection;
    }

    /**
     * @param array $line
     * @return Transaction
     * @throws \RuntimeException
     */
    private function createTransaction(array $line) : Transaction
    {
        try {
            $transaction = new Transaction();

            $account = $line[1];
            $transaction->setAccount($account);

            $dateTime = \DateTimeImmutable::createFromFormat('d.m.y', $line[3]);
            if (!$dateTime) {
                throw new DateTimeParseException();
            }
            $date = LocalDate::fromDateTime($dateTime);
            $transaction->setDate($date);

            $type = $line[6] === '0' ? TransactionType::OUT() : TransactionType::IN();
            $transaction->setType($type);

            $num = ($line[6] !== '0' ? $line[6] : $line[7]);
            $num = str_replace(',', '.', $num);
            $currency = Utils::getCurrencyByCode($line[2]);
            $amount = Money::of($num, $currency);
            $transaction->setAmount($amount);


            $reference = $line[4];
            if ($reference !== '' && $reference !== 'HOLD') {
                $transaction->setCommitted($reference);
            }

            $transaction->setDescription($line[5]);

            $transaction->freeze();
            return $transaction;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error create transaction from line '.var_export($line, true), 0, $e);
        }
    }
}
