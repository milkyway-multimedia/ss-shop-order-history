<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;

/**
 * Milkyway Multimedia
 * OrderManipulation.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\Shop\OrderHistory\Contracts\HasOrderFormActions;
use Object;
use Extension;

class OrderFormActions extends Extension
{
    protected $handler;

    public function updateActionsForm($order) {
        foreach($this->handler($order)->getExtensionInstances() as $action) {
            if($action instanceof HasOrderFormActions) {
                $owner = $action->getOwner();
                $action->setOwner($this->handler($order));
                $action->updateOrderActionForm($this->owner, $order);
                $action->setOwner($owner);
            }
        }
    }

    protected function handler($order) {
        if(!$this->handler) {
            $this->handler = $this->handler = Object::create('Milkyway\SS\Shop\OrderHistory\Actions\Handler', $this->owner->Controller, $order);
        }

        return $this->handler;
    }
}
