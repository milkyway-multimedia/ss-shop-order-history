<?php namespace Milkyway\SS\Shop\OrderHistory\Overrides;

/**
 * Milkyway Multimedia
 * OrderStatusLog.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use DataObject;
use Permission;

class OrderStatusLog extends \OrderStatusLog
{
    public function __construct($record = null, $isSingleton = false, $model = null)
    {
        if (!$record) {
            $record = [
                'ID'              => 0,
                'ClassName'       => 'OrderStatusLog',
                'RecordClassName' => 'OrderStatusLog',
            ];
        }

        parent::__construct($record, $isSingleton, $model);
    }

    public function setClassName($className)
    {
        $class = $this->class;
        $this->class = 'OrderStatusLog';
        if($className == __CLASS__) {
            $className = 'OrderStatusLog';
        }
        $response = parent::setClassName($className);
        $this->class = $class;

        return $response;
    }

    public function onBeforeWrite()
    {
        DataObject::onBeforeWrite();
        if (!$this->AuthorID && $m = \Member::currentUser()) {
            $this->AuthorID = $m->ID;
        }
        if (!$this->Title) {
            $this->Title = "Order Update";
        }

        if(!$this->ClassName) {
            $this->ClassName = 'OrderStatusLog';
        }
    }

    public function canDelete($member = null)
    {
        return DataObject::canDelete($member);
    }

    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return singleton('OrdersAdmin')->canView($member);
    }

    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('ADMIN') || singleton('OrdersAdmin')->canView($member);
    }

    public function canCreate($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('ADMIN') || singleton('OrdersAdmin')->canView($member);
    }
}