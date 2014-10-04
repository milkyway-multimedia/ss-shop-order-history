<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;

/**
 * Milkyway Multimedia
 * OrderStatusLog.php
 *
 * @package reggardocolaianni.com
 * @author  Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */


class OrderStatusLog extends \DataExtension
{
	private static $singular_name = 'Status Entry';

	private static $db = [
		'Status'  => 'Varchar',

		// The versioned table is too messy with relations... Store changes on here instead
		'Changes' => 'Text',
	];

	private static $default_sort = 'Created DESC';

	private static $status_mapping_for_events = [
		'onStartOrder' => 'Started',
		'onPlaceOrder' => 'Placed',
		'onPayment'    => 'Processing',
		'onPaid'       => 'Paid',
		'onCancelled'  => 'Cancelled',
		'onRecovered'  => 'Restarted',
	];

	private static $ignore_events = [];

	/** @var int You can end up with a lot of logs if you are not careful.
	 * This only applies to automatic logs (such as record updates), logs entered manually in the CMS will always be
	 * saved */
	private static $max_records_per_order = 50;

	// This is the generic status of an order, and is reserved for automatic updates
	const GENERIC_STATUS = 'Updated';

	// These are reserved statuses that change the mode of the Order, hence cannot be added by the user
	const RESERVED_STATUS = 'Started,Completed,Cancelled';

	public function updateCMSFields(\FieldList $fields)
	{
		$fields->removeByName('Status');
		$fields->removeByName('AuthorID');
		$fields->removeByName('Changes');

		$fields->insertBefore(\Select2Field::create('Status', 'Status', '', \OrderStatusLog::get()->filter('Status:not', explode(',', self::RESERVED_STATUS))->sort('Status', 'ASC')->alterDataQuery(function ($query, $list) {
			$query->groupby('Status');
		}), ['Status:StartsWith'], 'Status', 'Status')
			->setMinimumSearchLength(0)
			->setEmptyString('You select from below, or create a new status')
			->setDescription(_t('OrderStatusLog.DESC-Status', 'Note: Updated is a special status. If there are more than {limit} logs for an order, it will automatically delete statuses classes as Updated, so use with caution.', ['limit' => $this->owner->config()->max_records_per_order])), 'Title');
	}

	public function log($event, $params = [], $write = true)
	{
		if (isset($this->owner->config()->status_mapping_for_events[$event]))
			$this->owner->Status = _t('OrderHistory.STATUS-' . $event, $this->owner->config()->status_mapping_for_events[$event]);
		else
			$this->owner->Status = self::GENERIC_STATUS;

		$this->owner->castedUpdate($params);

		if ($write)
			$this->owner->write();

		return $this->owner;
	}

	public function setChangeLog($data)
	{
		$this->owner->Changes = serialize($data);
	}

	public function getChangeLog()
	{
		return unserialize($this->owner->Changes);
	}

	public function onBeforeWrite()
	{
		if (!$this->owner->Title)
			$this->owner->Title = $this->owner->Status;
	}

	public function canView($member = null)
	{
		if (singleton('OrdersAdmin')->canView($member) || $this->owner->Order()->canView($member))
			return true;
	}

	public function canCreate($member = null)
	{
		if (singleton('OrdersAdmin')->canView($member))
			return true;
	}

	public function getTimelineIcon()
	{
		switch ($this->owner->Status) {
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

				if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Status' => 'Captured', 'Created:LessThan' => $this->owner->Created])->first())) {
					switch ($lastPayment->Gateway) {
						case 'PayPal_Express':
							$extraClass = 'fa-paypal';
							break;
						default:
							$extraClass = 'fa-cc-' . strtolower(str_replace(' ', '', $lastPayment->Gateway)) . ' fa-' . strtolower(str_replace(' ', '', $lastPayment->Gateway));
							break;
					}
				}

				return '<i class="fa fa-money ' . $extraClass . ' order-statusIcon--paid"></i>';
				break;
		}

		return '<i class="fa order-statusIcon--minor icon-timeline--minor"></i>';
	}

	public function getDetailsForDataGrid($separator = ' - ')
	{
		$details = [];

		switch ($this->owner->Status) {
			case 'Updated':
				$log = $this->owner->ChangeLog;
				$separator = '<br/>';

				if (isset($log['OrderItem'])) {
					if (is_array($log['OrderItem']) && isset($log['OrderItem']['Quantity']) && isset($log['Quantity'])) {
						if (isset($log['OrderItem']['_brandnew']))
							$details[] = sprintf('Added %s of %s', $log['Quantity'], isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item', $log['Quantity']);
						elseif ($log['Quantity'])
							$details[] = sprintf('Set %s to %s', isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item', $log['Quantity'], $log['Quantity']);
						else
							$details[] = sprintf('Removed %s', isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item');
					} elseif (is_object($log['OrderItem']) && ($log['OrderItem'] instanceof \OrderItem) && isset($log['Quantity'])) {
						if (isset($log['OrderItem']->_brandnew))
							$details[] = sprintf('Added %s of %s', $log['Quantity'], $log['OrderItem']->TableTitle());
						elseif ($log['Quantity'])
							$details[] = sprintf('Set %s to %s', $log['OrderItem']->TableTitle(), $log['Quantity']);
						else
							$details[] = sprintf('Removed %s', $log['OrderItem']->TableTitle());
					}
				}

				if (isset($log['ShippingAddress']) && $log['ShippingAddress']) {
					$details[] = 'Ship to: ' . implode(', ', array_filter([$log['ShippingAddress']->Name, $log['ShippingAddress']->toString()]));

					if (!$this->owner->Order()->SeparateBillingAddress)
						$details[] = 'Bill to: ' . implode(', ', array_filter([$log['ShippingAddress']->Name, $log['ShippingAddress']->toString()]));
				}

				if (isset($log['BillingAddress']) && $log['BillingAddress']) {
					$details[] = 'Bill to: ' . implode(', ', array_filter([$log['BillingAddress']->Name, $log['BillingAddress']->toString()]));
				}

				if (isset($log['Member'])) {
					$details[] = 'Member: ' . $log['Member']->Name;
				}

				$allowed = ['IPAddress', 'Reference', 'SeparateBillingAddress', 'Notes', 'Total', 'Referrer'];
				$log = array_intersect_key($log, array_flip($allowed));

				if (count($log)) {
					foreach ($log as $field => $trans) {
						if (is_array($trans) && array_key_exists('before', $trans))
							$details[] = $this->owner->Order()->fieldLabel($field) . ' changed from ' . ($trans['before'] ?: '<em class="orderStatusLog-detail--none">none</em>') . ' to ' . $trans['after'];
						elseif (is_string($trans))
							$details[] = $this->owner->Order()->fieldLabel($field) . ': ' . $trans;
					}
				}

				break;
			case 'Processing':
				if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Created:LessThan' => $this->owner->Created])->first())) {
					$details[] = 'Via ' . \GatewayInfo::nice_title($lastPayment->Gateway);
					$details[] = 'Charging ' . \GatewayInfo::nice_title($lastPayment->obj('Money')->Nice());

					if ($gatewayMessage = \GatewayMessage::get()->filter(['PaymentID' => $lastPayment->ID, 'Reference:not' => ''])->first()) {
						if ($gatewayMessage->Reference)
							$details[] = 'Reference: ' . $gatewayMessage->Reference;
					}
				}

				break;
			case 'Paid':
				if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Status' => 'Captured', 'Created:LessThan' => $this->owner->Created])->first())) {
					$details[] = 'Via ' . \GatewayInfo::nice_title($lastPayment->Gateway);
					$details[] = 'Charged ' . \GatewayInfo::nice_title($lastPayment->obj('Money')->Nice());

					if ($gatewayMessage = \GatewayMessage::get()->filter(['PaymentID' => $lastPayment->ID, 'Reference:not' => ''])->first()) {
						if ($gatewayMessage->Reference)
							$details[] = 'Reference: ' . $gatewayMessage->Reference;
					}
				}
				break;
		}

		$details = [count($details) ? $separator . implode($separator, $details) : ''];

		if ($this->owner->Sent)
			$details[] = 'Notified customer on: ' . $this->owner->Sent;

		if ($this->owner->Author()->exists())
			$details[] = 'Author: ' . $this->owner->Author()->Name;

		return implode('<br/>', $details);
	}
} 