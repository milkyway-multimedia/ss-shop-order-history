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
	 * This only applies to automatic logs (such as record updates), logs entered manually in the CMS will always be saved */
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

	public function canView($member = null) {
		if($this->owner->Order()->canView($member))
			return true;
	}

	public function canCreate($member = null) {
		if($this->owner->Order()->canEdit($member))
			return true;
	}

	public function getTimelineIcon() {
		switch($this->owner->Status) {
			case 'Started':
				return '<i class="fa fa-star-o order-statusIcon--started"></i>';
				break;
			case 'Processing':
				return '<i class="fa fa-refresh order-statusIcon--processing"></i>';
				break;
			case 'Placed':
				return '<i class="fa fa-check order-statusIcon--placed"></i>';
				break;
			case 'Paid':
				$extraClass = '';

				if(($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Status' => 'Captured', 'Created:LessThan' => $this->owner->Created])->first())) {
					switch($lastPayment->Gateway) {
						case 'PayPal_Express':
							$extraClass = 'fa-paypal';
							break;
						default:
							$extraClass = 'fa-' . strtolower(str_replace(' ', '', $lastPayment->Gateway));
							break;
					}
				}

				return '<i class="fa fa-money ' . $extraClass . ' order-statusIcon--paid"></i>';
				break;
		}

		return '<i class="fa order-statusIcon--minor icon-timeline--minor"></i>';
	}

	public function getDetails($separator = ' - ') {
		$details = [];

		switch($this->owner->Status) {
			case 'Started':
				break;
			case 'Processing':
				break;
			case 'Placed':
				break;
			case 'Paid':
				if(($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Status' => 'Captured', 'Created:LessThan' => $this->owner->Created])->first())) {
					$details[] = 'via ' . \GatewayInfo::nice_title($lastPayment->Gateway);
					$details[] = 'charged ' . \GatewayInfo::nice_title($lastPayment->obj('Money')->Nice());

					if($gatewayMessage = \GatewayMessage::get()->filter(['PaymentID' => $lastPayment->ID, 'Reference:not' => ''])->first()) {
						if($gatewayMessage->Reference)
							$details[] = 'Reference: ' . $gatewayMessage->Reference;
					}
				}
				break;
		}

		return implode($separator, $details);
	}
} 