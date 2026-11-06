<?php

namespace App\Services\Import;

use App\Services\Import\Parsers\SparkasseCamtV2Parser;

/**
 * Registry of supported bank formats. Adding a bank = adding a parser class here.
 */
class BankFormats
{
    /** @var list<class-string<BankStatementParser>> */
    private const PARSERS = [
        SparkasseCamtV2Parser::class,
    ];

    /**
     * @return list<BankStatementParser>
     */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), self::PARSERS);
    }

    public function find(string $key): ?BankStatementParser
    {
        foreach ($this->all() as $parser) {
            if ($parser->key() === $key) {
                return $parser;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(fn (BankStatementParser $p) => $p->key(), $this->all());
    }
}
