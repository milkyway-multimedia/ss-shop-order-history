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

	private static $status_mapping_for_events = [
		'onStartOrder' => 'Started',
		'onPlaceOrder' => 'Placed',
		'onPayment' => 'Processing',
		'onPaid' => 'Paid',
	];

	private static $ignore_events = [];

	/** @var int You can end up with a lot of logs if you are not careful.
	 * This only applies to automatic logs, logs entered manually in the CMS will always be saved */
	private static $max_records_per_order = 50;

	public function log($event, $order, $params = [], $write = true) {
		$log = \OrderStatusLog::create();
		$log->OrderID = $order->ID;

		if(isset($log->config()->status_mapping_for_events[$event]))
			$log->Status = _t('OrderHistory.STATUS-'.$event, $log->config()->status_mapping_for_events[$event]);
		else
			$log->Status = _t('OrderHistory.STATUS-updated', 'Updated');

		$log->castedUpdate($params);

		if($write) {
			if($order->OrderStatusLogs()->count() <= $log->config()->max_records_per_order)
				$log->write();
			else
				return null;
		}

		return $log;
	}

	public function setChangeLog($data) {
		$this->owner->Changes = serialize($data);
	}

	public function getChangeLog() {
		return unserialize($this->owner->Changes);
	}
} 