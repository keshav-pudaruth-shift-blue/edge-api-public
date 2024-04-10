<?php

namespace App\Services;

class OPRAservice
{
    public const TRADE_TYPE_NORMAL = 'normal';
    public const TRADE_TYPE_SWEEP = 'sweep';
    public const TRADE_TYPE_AUCTION = 'auction';

    public const TRADE_TYPE_FLOOR = 'floor';

    public const TRADE_LEG_SINGLE = 'single';

    public const TRADE_LEG_MULTI = 'multi';

    public const TRADE_ACTION_DEFER = 'defer';

    public const TRADE_ACTION_CANCEL = 'cancel';

    public const TRADE_ACTION_IGNORE = 'ignore';

    public function parseOptionTradeCondition(int $condition): array
    {
        //Parse opra trade condition into buy/sell
        switch($condition) {
            //Normal
            case 2:
            case 6:
            case 7:
            case 13:
            case 18:
            case 21:
                return [
                    'type' => self::TRADE_TYPE_NORMAL,
                    'leg' => self::TRADE_LEG_SINGLE,
                    'action' => 'defer'
                ];
            //Sweep
            case 95:
                return [
                    'type' => self::TRADE_TYPE_SWEEP,
                    'leg' => self::TRADE_LEG_SINGLE,
                    'action' => 'defer'
                ];
            //Cancel
            case 40:
            case 41;
            case 42:
            case 43:
                return [
                    'type' => self::TRADE_TYPE_NORMAL,
                    'leg' => self::TRADE_LEG_SINGLE,
                    'action' => 'cancel'
                ];
            case 114:
            case 115:
            case 116:
            case 117:
            case 124:
            case 125:
            case 126:
            case 128:
                return [
                    'type' => self::TRADE_TYPE_AUCTION,
                    'leg' => self::TRADE_LEG_SINGLE,
                    'action' => 'ignore'
                ];
            case 118:
                return [
                    'type' => self::TRADE_TYPE_FLOOR,
                    'leg' => self::TRADE_LEG_SINGLE,
                    'action' => 'defer'
                ];
            case 120:
            case 123:
            case 127:
            case 130:
            case 136:
                return [
                    'type' => self::TRADE_TYPE_NORMAL,
                    'leg' => self::TRADE_LEG_MULTI,
                    'action' => 'defer'
                ];
            case 121:
            case 131:
                return [
                    'type' => self::TRADE_TYPE_AUCTION,
                    'leg' => self::TRADE_LEG_MULTI,
                    'action' => 'ignore'
                ];
            case 122:
            case 129:
            case 132:
            case 133:
            case 137:
            case 144:
                return [
                    'type' => self::TRADE_TYPE_FLOOR,
                    'leg' => self::TRADE_LEG_MULTI,
                    'action' => 'defer'
                ];
            default:
                return [
                    'type' => self::TRADE_TYPE_NORMAL,
                    'leg' => self::TRADE_LEG_SINGLE,
                    'action' => 'ignore'
                ];
        }
    }


    /**
     * @param int $size
     * @param int $bidSize
     * @param int $askSize
     * @param $price
     * @param int $tickIndex
     * @param array $ticks
     * @return string
     */
    public function derivePurchaseAction(int $size, int $bidSize, int $askSize, $price, int $tickIndex, array $ticks): string
    {
        //Trade Size Rule
        // Trades with a trade size equal to either the size of the ask or the bid quote is due to limit orders
        //placed by sophisticated customers. In such a situation, the customer buys at
        //the prevailing bid and sells at the prevailing ask.

        if ($size === $bidSize) {
            return 'Buy';
        } elseif ($size === $askSize) {
            return 'Sell';
        } else {
            // Depth Rule
            // we classify midspread trades as buyer-initiated, if the ask size exceeds the bid size,
            // and as seller initiated, if the bid size is higher than the ask size
            if ($askSize > $bidSize) {
                return 'Buy';
            } elseif ($askSize < $bidSize) {
                return 'Sell';
            } else {
                //If the ask size matches the bid size, midspread
                //trades still cannot be classified by this approach, and we use the reverse tick test to classify such trades
                //Classifies a trade as a buy (sell) if its trade price is above (below) the closest
                //different price of a following trade.
                if (isset($ticks[$tickIndex + 1])) {
                    if ($price > $ticks[$tickIndex + 1][4]) {
                        return 'Buy';
                    } elseif ($price < $ticks[$tickIndex + 1][4]) {
                        return 'Sell';
                    }
                }

                return 'Mid';
            }
        }
    }

    /**
     * @param $price
     * @param $bid
     * @param $ask
     * @return string
     */
    public function derivePriceAction($price, $bid, $ask): string
    {
        if ($price > $bid && $price < $ask) {
            return 'Midpoint';
        } elseif ($price < $bid) {
            return 'Below bid';
        } elseif ($price > $ask) {
            return 'Above ask';
        } elseif ($price == $bid) {
            return 'At bid';
        } elseif ($price == $ask) {
            return 'At ask';
        } else {
            return 'Unknown';
        }
    }
}
