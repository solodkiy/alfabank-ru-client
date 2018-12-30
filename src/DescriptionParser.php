<?php
declare(strict_types = 1);


namespace Solodkiy\AlfaBankRu;

use Brick\DateTime\LocalDate;
use Brick\DateTime\Parser\DateTimeParseException;
use Brick\Money\Money;

class DescriptionParser
{
    public function extractCommitted($description): ?array
    {
        $regexp = '/
        ^
        (?<card>\d+\++\d+)\s+
        (?<code>[\dA-Z]+)?\ ?\\\\
        (?<description>.+?)\s+
        (?<commit_date>\d\d\.\d\d\.\d\d)\s+
        (?<hold_date>\d\d\.\d\d\.\d\d)\s+
        (?<sum>[\d.]+)\s+
        (?<currency>[A-Z]{3})\s+
        (\(Apple\ Pay.+?\)\s*)? 
        (?<extra_code>[A-Z\d]+)
        $
        /x';

        if (preg_match($regexp, $description, $m)) {
            $description = $m['description'];
            $sections = explode('\\', $description);
            $company = $sections[count($sections) - 1];
            $sum = $this->fixSum($m['sum']);

            $dateTime = \DateTimeImmutable::createFromFormat('d.m.y', $m['hold_date']);
            if (!$dateTime) {
                throw new DateTimeParseException();
            }

            return [
                'card' => $m['card'],
                'code' => $m['code'],
                'description' => $description,
                'hold_date' => LocalDate::fromDateTime($dateTime),
                'amount' => Money::of($sum, Utils::getCurrencyByCode($m['currency'])),
                'company' => $company,
            ];
        }
        return null;
    }

    public function extractHold($description): ?array
    {
        $regexp = '/
        ^
        ((?<code>[\dA-Z]+)_?\ )?
        ([A-Z]{2}\ )
        (?<company>([A-Z\d]+\ )?([^>]+))?>
        (?<city>.+)?\ (\d{2}\.\d{2}\.\d{2})\ (\d{2}\.\d{2}\.\d{2})\ (?<sum>[\d\.]+)\ (?<currency>[A-Z]{3})\ (?<card>[\d+]+)
        (\ \(Apple\ Pay.+?\))? 
        $
        /x';
        if (preg_match($regexp, $description, $m)) {
            $sum = $this->fixSum($m['sum']);
            return [
                'code' => $m['code'],
                'card' => $m['card'],
                'amount' => Money::of($sum, Utils::getCurrencyByCode($m['currency'])),
                'company' => $m['company'],
            ];
        }
        return null;
    }

    private function fixSum(string $sum) : string
    {
        if ($sum[0] === '.') {
            $sum = '0'. $sum;
        }

        return $sum;
    }
}
