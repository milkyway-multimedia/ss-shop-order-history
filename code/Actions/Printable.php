<?php namespace Milkyway\SS\Shop\OrderHistory\Actions;

/**
 * Milkyway Multimedia
 * Printable.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\Shop\OrderHistory\Contracts\HasOrderFormActions;

use Extension;
use FormActionLink;

class Printable extends Extension implements HasOrderFormActions
{
    private static $allowed_actions = [
        'printable',
    ];

    public function printable()
    {
        $dir = $this->dir();

        $this->owner->Order->extend('onPrintByCustomer');

        singleton('require')->clear();
        singleton('require')->css($dir. '/css/printable.css');
        singleton('require')->unblock(THIRDPARTY_DIR . '/jquery/jquery.js');
        singleton('require')->javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
        singleton('require')->add($dir. '/js/printable.js', 'last');

        $this->owner->extend('onPrint', $dir);

        return $this->owner->Order->customise([
            'asWebPage' => true,
        ])->renderWith(['Order_printable']);
    }

    public function updateOrderActionForm($form, $order)
    {
        singleton('require')->javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
        singleton('require')->add($this->dir(). '/js/printable.js', 'last');

        $form->Actions()->push(
            FormActionLink::create('action_print', 'Print', $this->owner->Link('printable'))
                ->setForm($form)
                ->setAttribute('data-print-url', $this->owner->Link('printable'))
        );
    }

    protected function dir() {
        return basename(dirname(dirname(dirname(__FILE__))));
    }
}
