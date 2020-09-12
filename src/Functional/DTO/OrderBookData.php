<?php

/**
 * @author Marc MOREAU <moreau.marc.web@gmail.com>
 * @license https://github.com/MockingMagician/coinbase-pro-sdk/blob/master/LICENSE.md MIT
 * @link https://github.com/MockingMagician/coinbase-pro-sdk/blob/master/README.md
 */

namespace MockingMagician\CoinbaseProSdk\Functional\DTO;

use MockingMagician\CoinbaseProSdk\Contracts\DTO\OrderBookDataInterface;

class OrderBookData extends AbstractCreator implements OrderBookDataInterface
{
    /**
     * @var int
     */
    private $sequence;
    /**
     * @var array
     */
    private $bids;
    /**
     * @var array
     */
    private $asks;

    public function __construct(
        int $sequence,
        array $bids,
        array $asks
    ) {
        $this->sequence = $sequence;
        $this->bids = $bids;
        $this->asks = $asks;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getBids(): array
    {
        return $this->bids;
    }

    public function getAsks(): array
    {
        return $this->asks;
    }

    public static function createFromArray(array $array, ...$divers)
    {
        $bids = [];
        foreach ($array['bids'] as $k => $v) {
            $array['bids'][$k] = OrderBookDetailsData::createFromArray($v, $divers);
        }
        foreach ($array['asks'] as $k => $v) {
            $array['asks'][$k] = OrderBookDetailsData::createFromArray($v, $divers);
        }

        return new self($array['sequence'], $array['bids'], $array['asks']);
    }

    public static function createFromJson(string $json, ...$divers)
    {
        return self::createFromArray(json_decode($json, true));
    }
}
