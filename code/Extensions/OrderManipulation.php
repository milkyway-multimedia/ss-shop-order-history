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

class OrderManipulation extends Extension
{
    private static $allowed_actions = [
        'orders',
    ];

    protected $handler;
    protected $order;

    /*
     * Yup, we are hijacking the order action
     */
    public function beforeCallActionHandler($request, &$action = '') {
        if($action != 'order' || !$request->param('OtherID')) {
            return;
        }

        $this->order = $this->owner->orderfromid();

        if(!$this->order || !$this->handler()->hasAction($request->param('OtherID'))) {
            return;
        }

        $action = 'orders';
    }

    public function orders() {
        if(!$this->order) {
            return $this->owner->httpError(404, "Order could not be found");
        }

        return $this->handler();
    }

    protected function handler() {
        if(!$this->handler) {
            $this->handler = $this->handler = Object::create('Milkyway\SS\Shop\OrderHistory\Actions\Handler', $this->owner, $this->order);
        }

        return $this->handler;
    }
}
