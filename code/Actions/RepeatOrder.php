<?php namespace Milkyway\SS\Shop\OrderHistory\Actions;

/**
 * Milkyway Multimedia
 * RepeatOrder.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\Shop\OrderHistory\Contracts\HasOrderFormActions;

use Object;
use Extension;
use FormActionLink;
use Session;

use ShoppingCart;
use CartPage;

class RepeatOrder extends Extension implements HasOrderFormActions
{
    private static $allowed_actions = [
        'repeat',
    ];

    protected $sessionVariable = 'Orders.Messages.OnRepeat';

    public function repeat()
    {
        $currentOrder = $this->owner->Order;
        $cart = ShoppingCart::curr();

        if($cart && $cart->Items()->exists()) {
            $newOrder = Object::create($currentOrder->get()->dataClass());
            $newOrder->write();
            ShoppingCart::singleton()->setCurrent($newOrder);
        }

        foreach($currentOrder->Items() as $item) {
            if($item->hasMethod('Product')) {
                $buyable = $item->Product(true);
            }
            else {
                $buyable = $item->Buyable();
            }

            ShoppingCart::singleton()->add($buyable, $item->Quantity);

            if(ShoppingCart::singleton()->getMessageType() == 'bad' && $message = ShoppingCart::singleton()->getMessage()) {
                Session::set($this->sessionVariable, sprintf('%s: <strong>%s</strong>', rtrim($message, '.:'), $item->Buyable()->Title));
                return $this->owner->redirectBack();
            }
        }

        if(!isset($newOrder)) {
            $newOrder = ShoppingCart::curr();
        }

        $newOrder->extend('onRepeatByCustomer', $currentOrder);

        return $this->owner->redirect(CartPage::find_link());
    }

    public function updateOrderActionForm($form, $order)
    {
        $form->Actions()->push(
            $button = FormActionLink::create('action_repeat', 'Repeat as new order', $this->owner->Link('repeat'))
                ->setForm($form)
        );

        if($message = Session::get($this->sessionVariable)) {
            $form->sessionMessage(implode('. ', array_filter([$form->Message, $message])), $form->MessageType, false);
            Session::clear($this->sessionVariable);
        }

        if(($cart = ShoppingCart::curr()) && $cart->Items()->exists()) {
            singleton('require')->javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
            singleton('require')->add($this->dir(). '/js/repeatable.js', 'last');

            $button->setAttribute('data-confirm', _t('OrderLog.REPEAT-CONFIRMATION', 'This will clear the current shopping cart. Continue?'));
        }
    }

    protected function dir() {
        return basename(dirname(dirname(dirname(__FILE__))));
    }
}
