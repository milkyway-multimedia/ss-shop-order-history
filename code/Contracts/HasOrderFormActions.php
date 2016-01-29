<?php namespace Milkyway\SS\Shop\OrderHistory\Contracts;

/**
 * Milkyway Multimedia
 * HasOrderFormActions.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

interface HasOrderFormActions
{
    public function updateOrderActionForm($form, $order);
}
