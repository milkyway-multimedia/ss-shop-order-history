<?php namespace Milkyway\SS\Shop\OrderHistory\Exceptions;

/**
 * Milkyway Multimedia
 * ShoppingCartError.php
 *
 * Since the shopping cart doesn't really tell you when an error occured,
 * I have added an exception to dry up code in some of the front end actions
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Exception;

use ShoppingCart;

class ShoppingCartError extends Exception
{
    public static function get() {
        if(ShoppingCart::singleton()->getMessageType() != 'good') {
            return ShoppingCart::singleton()->getMessage();
        }
        else {
            return '';
        }
    }
}
