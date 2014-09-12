<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;
/**
 * Milkyway Multimedia
 * OrderStatusLog.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */


class OrderStatusLog extends \DataExtension {
	private static $singular_name = 'Status Entry';

	private static $db = [
		'Status' => 'Varchar',

		// The versioned table is too messy with relations... Store changes on here instead
		'Changes' => 'Text',
	];

	private static $default_sort = 'Created DESC';

	private static $status_mapping_for_events = [
		'onStartOrder' => 'Started',
		'onPlaceOrder' => 'Placed',
		'onPayment' => 'Processing',
		'onPaid' => 'Paid',
		'onCancelled' => 'Cancelled',
	];

	private static $ignore_events = [];

	/** @var int You can end up with a lot of logs if you are not careful.
	 * This only applies to automatic logs, logs entered manually in the CMS will always be saved */
	private static $max_records_per_order = 50;

	// This is the generic status of an order, and is reserved for automatic updates
	const GENERIC_STATUS = 'Updated';

	public function log($event, $params = [], $write = true) {
		if(isset($this->owner->config()->status_mapping_for_events[$event]))
			$this->owner->Status = _t('OrderHistory.STATUS-'.$event, $this->owner->config()->status_mapping_for_events[$event]);
		else
			$this->owner->Status = self::GENERIC_STATUS;

		$this->owner->castedUpdate($params);

		if($write)
			$this->owner->write();

		return $this->owner;
	}

	public function setChangeLog($data) {
		$this->owner->Changes = serialize($data);
	}

	public function getChangeLog() {
		return unserialize($this->owner->Changes);
	}

	public function onBeforeWrite() {
		if(!$this->owner->Title)
			$this->owner->Title = $this->owner->Status;
	}
} 