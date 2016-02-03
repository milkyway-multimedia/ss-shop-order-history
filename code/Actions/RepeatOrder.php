<?php namespace Milkyway\SS\Shop\OrderHistory\Actions;

/**
 * Milkyway Multimedia
 * RepeatOrder.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\Shop\OrderHistory\Contracts\HasOrderFormActions;

use Extension;
use FormActionLink;
use Session;

use ShoppingCart;
use CartPage;

use Milkyway\SS\Shop\OrderHistory\Exceptions\ShoppingCartError;
use Exception;

class RepeatOrder extends Extension implements HasOrderFormActions
{
    private static $allowed_actions = [
        'repeat',
    ];

    protected $sessionVariable = 'Orders.Messages.OnRepeat';

    public function repeat()
    {
        try {
            $currentOrder = $this->owner->Order;
            $cart = ShoppingCart::curr();

            if($cart && $cart->Items()->exists()) {
                $cart->Items()->removeAll();
            }

            foreach($currentOrder->Items() as $item) {
                if($item->hasMethod('Product')) {
                    $buyable = $item->Product(true);
                }
                else {
                    $buyable = $item->Buyable();
                }

                ShoppingCart::singleton()->add($buyable, $item->Quantity);

                if($message = ShoppingCartError::get()) {
                    throw new ShoppingCartError(sprintf('%s: <strong>%s</strong>', rtrim($message, '.:'), $item->Buyable()->Title));
                }
            }

            if(!isset($newOrder)) {
                $newOrder = ShoppingCart::curr();
            }

            $newOrder->extend('onRepeatByCustomer', $currentOrder);
        } catch (Exception $e) {
            Session::set($this->sessionVariable, $e->getMessage());
            return $this->owner->redirectBack();
        }

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
