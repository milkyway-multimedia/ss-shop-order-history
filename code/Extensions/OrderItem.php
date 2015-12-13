<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;

/**
 * Milkyway Multimedia
 * OrderItem.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use DataExtension;

class OrderItem extends DataExtension
{
    private static $db = [
        'Placed_BuyableHash' => 'Text',
    ];

    function onPlacement()
    {
        if ($buyable = $this->owner->Buyable()) {
            $this->owner->Placed_BuyableHash = serialize($buyable);
        }
    }

    public function getPlaced_Buyable()
    {
        return unserialize($this->owner->Placed_BuyableHash);
    }
} 