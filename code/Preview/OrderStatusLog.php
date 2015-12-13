<?php namespace Milkyway\SS\Shop\OrderHistory\Preview;

/**
 * Milkyway Multimedia
 * OrderStatusLog.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use DataObjectPreviewInterface;
use OrderStatusLog as Record;

class OrderStatusLog implements DataObjectPreviewInterface
{
    protected $record;

    public function __construct(Record $record)
    {
        $this->record = $record;
    }

    public function getPreviewHTML()
    {
        if ($this->record->Sent) {
            return $this->record->Send_Body;
        }

        $email = $this->record->Email;

        singleton('require')->clear();
        $preview = $email->renderWith($email->getTemplate());
        singleton('require')->restore();

        return $preview;
    }
}