<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;

/**
 * Milkyway Multimedia
 * Order.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Object;
use DataExtension;
use FieldList;
use ReadonlyField;

use GridField;
use GridFieldConfig_RecordEditor;
use Milkyway\SS\GridFieldUtils\DisplayAsTimeline;
use Milkyway\SS\GridFieldUtils\AddNewInlineExtended;
use Milkyway\SS\GridFieldUtils\EditableRow;
use Milkyway\SS\GridFieldUtils\MinorActionsHolder;
use Milkyway\SS\GridFieldUtils\HelpButton;

use OrderLog;

class Order extends DataExtension
{
    private static $casting = [
        'BillingAddress' => 'HTMLText',
        'ShippingAddress' => 'HTMLText',
        'DispatchInformation' => 'HTMLText',
    ];

    protected $workingLogs = [];

    public function getState()
    {
        if ($log = $this->owner->LogsByStatusPriority()->filter('Status:not',
            (array)singleton($this->owner->OrderStatusLogs()->dataClass())->config()->ignore_status_as_state)->first()
        ) {
            return ReadonlyField::name_to_label($log->Status);
        } else {
            return $this->owner->Status;
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        $log = singleton($this->owner->OrderStatusLogs()->dataClass());
        $order = $this->owner;

        if (!$this->owner->IsCart()) {
            $state = $this->owner->State;

            if (isset($log->config()->status_list[$state])) {
                $state = $log->config()->status_list[$state]['icon'] . ' ' . $log->config()->status_list[$state]['title'];
            }

            $fields->replaceField(
                'Status',
                $state = ReadonlyField::create(
                    'READONLY-State',
                    'Status',
                    $state
                )
                    ->addExtraClass('important')
                    ->setDescription(
                        _t(
                            'Order.DESC-State',
                            'The status of your order is controlled by the order logging system. <a href="{logTab}" class="ss-tabset-goto">You can add a new log here.</a>',
                            [
                                'logTab' => singleton('director')->url('#tab-Root_Logs'),
                            ]
                        )
                    )
            );

            $state->dontEscape = true;
        }

        singleton('require')->include_font_css();
        $maxRecords = $log->config()->max_records_per_order;

        $fields->addFieldsToTab('Root.Logs', [
            GridField::create(
                'OrderStatusLogs',
                'History',
                $this->owner->LogsByStatusPriority(),
                $gfc = GridFieldConfig_RecordEditor::create($maxRecords)
                    ->removeComponentsByType('GridFieldAddNewButton')
                    ->removeComponentsByType('GridFieldEditButton')
                    ->removeComponentsByType('GridFieldDetailForm')
                    ->addComponents(
                        new DisplayAsTimeline,
                        $editable = new EditableRow,
                        new MinorActionsHolder('buttons-before-right'),
                        $addNew[] = $button = new AddNewInlineExtended('actions-buttons-before-right',
                            _t('OrderLog.ACTION-EMAIL', 'Send an email'),
                            $log->NotificationFields)
                    )
            ),
        ]);

        $button->urlSegment = 'sendAnEmail';
        $button->setItemEditFormCallback(function ($form) use ($order, $log) {
            $form->loadDataFrom([
                'Status' => 'Notified',
                'Send' => true,
                'Note' => _t('OrderLog.DEFAULT-NOTIFIED-Note', 'Email was sent to customer'),
                'Public' => true,
            ]);
        });

        $gfc->addComponent(
            (new HelpButton('buttons-before-left', _t('OrderLog.EMAIL_VARIABLES', 'Email helpers')))
                ->setTemplate('OrderStatusLog_EmailHelpers')
        );

        singleton('require')->css(basename(dirname(dirname(dirname(__FILE__)))) . '/css/admin.css');

        if (!$this->owner->OrderStatusLogs()->filter('Status', OrderLog::SHIPPED_STATUS)->exists()) {
            $gfc->addComponent(
                $minorActions[] = $addNew[] = $button = new AddNewInlineExtended(
                    'actions-buttons-before-right',
                    _t('OrderLog.ACTION-SHIP', 'Send shipping details'),
                    $log->ShippedToFields
                )
            );

            $shippedStatus = OrderLog::SHIPPED_STATUS;

            $button->urlSegment = 'sendShippingDetails';
            $button->setItemEditFormCallback(function ($form) use ($order, $log, $shippedStatus) {
                $form->loadDataFrom([
                    'Status' => $shippedStatus[0],
                    'Title'  => _t('OrderLog.DEFAULT-SHIPPING-Title', '$Order.Reference has been shipped'),
                    'Note'   => _t(
                        'OrderLog.DEFAULT-SHIPPING-Note',
                        '$Order.Reference[/b] has been shipped to the following address: $Order.ShippingAddress.Title'
                    ),
                    'Public' => true,

                    'Send'         => true,
                    'Send_Subject' => _t('OrderLog.DEFAULT-SHIPPING-Send_Subject',
                        '$Order.Reference has been shipped'),
                    'Send_Body'    => _t(
                        'OrderLog.DEFAULT-SHIPPING-Send_Body',
                        '[b]$Order.Reference[/b] has been shipped to the following address:' . "<br />\n"
                        . '[b]$Order.ShippingAddress.Title[/b]'
                        . "<br /><br />\n\n"
                        . '$Order.DispatchInformation'
                    ),
                ]);
            });
        }

        $gfc->addComponent(
            $addNew[] = $button = new AddNewInlineExtended('actions-buttons-before-right',
                _t('OrderLog.ACTION-QUERY', 'Log a customer query'))
        );

        $button->urlSegment = 'logQuery';
        $button->setItemEditFormCallback(function ($form) use ($order, $log) {
            $params = [
                'Status' => 'Query',
                'Send' => true,
                'Title' => _t('OrderLog.DEFAULT-QUERY-Title', 'A query was made by the customer'),
                'Send_Subject' => _t('OrderLog.DEFAULT-QUERY-Subject', 'Query concerning $Order.Reference'),
                'Public' => true,
            ];

            $dataFields = $form->Fields()->dataFields();

            if (isset($dataFields['Send_To']) && isset($dataFields['Send_From'])) {
                $params['Send_To'] = $dataFields['Send_From']->getAttribute('placeholder');
                $params['Send_From'] = $dataFields['Send_To']->getAttribute('placeholder');
            }

            $form->loadDataFrom($params);
        });

        $gfc->addComponent(
            $addNew[] = new AddNewInlineExtended('actions-buttons-before-right',
                _t('OrderLog.ACTION-DEFAULT', 'Detailed status update'))
        );

        if ($this->owner->OrderStatusLogs()->count() <= $maxRecords) {
            $gfc->removeComponentsByType('GridFieldPageCount');
            $gfc->removeComponentsByType('GridFieldPaginator');
        }

        foreach ($addNew as $button) {
            $button->prepend = true;
        }

        if ($dc = $gfc->getComponentByType('GridFieldDataColumns')) {
            $dc->setDisplayFields([
                'Title' => 'Status',
                'Created' => 'Date',
            ]);

            $dc->setFieldFormatting([
                'Title' => '<strong>$Title</strong> $DetailsForDataGrid',
                'Created' => '<strong>Logged $Created</strong>',
            ]);
        }

        foreach ($addNew as $button) {
            $cb = $button->getItemEditFormCallback();

            $button->setItemEditFormCallback(function (
                $form,
                $controller,
                $grid,
                $modelClass,
                $removeEditableColumnFields
            ) use ($order, $log, $cb) {
                $log->setEditFormWithParent($order, $form, $controller);

                if ($cb) {
                    $cb($form, $this, $grid, $modelClass, $removeEditableColumnFields);
                }
            });
        }
    }

    public function onStartOrder()
    {
        // Destroy working logs since the first log should always be the order started log
        foreach ($this->owner->OrderStatusLogs() as $log) {
            if ($log->exists()) {
                $log->delete();
                $log->destroy();
            }
        }
        $this->compileChangesAndLog(__FUNCTION__,
            ['Referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''], true);
    }

    public function onPlaceOrder()
    {
        $this->compileChangesAndLog(__FUNCTION__, [], true);
    }

    public function onPayment()
    {
        $this->compileChangesAndLog(__FUNCTION__, [], true);
    }

    public function onPaid()
    {
        $this->compileChangesAndLog(__FUNCTION__, [], true);
    }

    public function onStatusChange()
    {
        $this->compileChangesAndLog(__FUNCTION__, [], true);
    }

    public function onCancelled()
    {
        $this->compileChangesAndLog(__FUNCTION__, [], true);
    }

    public function onRecovered($method)
    {
        $this->compileChangesAndLog(__FUNCTION__,
            [
                'Referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                'Method' => _t('OrderSatus.RECOVERED-' . ucfirst($method), $method),
            ], true);
    }

    public function afterAdd($item, $buyable, $quantity, $filter)
    {
        $this->compileChangesAndLog('addedAnItem', ['OrderItem' => $item, 'Quantity' => $quantity]);
    }

    public function afterRemove($item, $buyable, $quantity, $filter)
    {
        $this->compileChangesAndLog('removedAnItem', ['OrderItem' => $item, 'Quantity' => 0]);
    }

    public function afterSetQuantity($item, $buyable, $quantity, $filter)
    {
        $this->compileChangesAndLog('changedItemQuantity', ['OrderItem' => $item, 'Quantity' => $quantity]);
    }

    public function onSetShippingAddress($address)
    {
        $this->compileChangesAndLog('changedShippingAddress', ['ShippingAddress' => $address]);
    }

    public function onUpdateShippingAddress($address)
    {
        $this->compileChangesAndLog('changedShippingAddress', ['ShippingAddress' => $address]);
    }

    public function onSetBillingAddress($address)
    {
        $this->compileChangesAndLog('changedBillingAddress', ['BillingAddress' => $address]);
    }

    public function onUpdateBillingAddress($address)
    {
        $this->compileChangesAndLog('changedBillingAddress', ['BillingAddress' => $address]);
    }

    public function onSetMember($member)
    {
        $this->compileChangesAndLog('changedMember', ['Member' => $member]);
    }

    public function onUpdateMember($member)
    {
        $this->compileChangesAndLog('changedMember', ['Member' => $member]);
    }

    public function onAfterWrite()
    {
        if ($this->owner->isChanged('Status')) {
            if (in_array($this->owner->Status, ['MemberCancelled', 'AdminCancelled'])) {
                $this->owner->extend('onCancelled');
            } else {
                $this->owner->extend('onStatusChange');
            }
        }

        if ($this->owner->isChanged('MemberID')) {
            $member = $this->owner->Member();
            $this->owner->extend('onSetMember', $member);
        }

        $hasOnes = array_merge($this->owner->has_one(), $this->owner->belongs_to());
        $changedRelations = [];

        foreach ($hasOnes as $relation => $class) {
            if ($this->owner->getComponent($relation)->isChanged()) {
                $changedRelations[$relation] = $this->owner->getComponent($relation);
            }
        }

        if (count($changedRelations) > 2) {
            $this->owner->extend('onUpdateComponents', $changedRelations);
        } elseif (!empty($changeRelations)) {
            $this->owner->extend('onUpdate' . key($changedRelations), reset($changedRelations));
        }
    }

    protected function compileChangesAndLog($event, $additionalObjects = [], $force = false)
    {
        if (!$this->owner->ID) {
            return;
        }

        $log = Object::create($this->owner->OrderStatusLogs()->dataClass());

        // Always record a status change
        if ($this->owner->isChanged('Status') || !in_array($event, (array)$log->config()->ignored_events)) {
            $logs = $this->owner->OrderStatusLogs();
            $max = $log->config()->max_records_per_order ?: 50;
            $count = $logs->filter('Status', OrderLog::GENERIC_STATUS)->count();

            // Clean if its over the max limit of history allowed
            if ($max && $count >= $max) {
                $logs->filter([
                    'Status' => OrderLog::GENERIC_STATUS,
                    'ID:not' => $logs->filter('Status',
                        OrderLog::GENERIC_STATUS)->sort('Created DESC')->limit($max)->column('ID'),
                ])->removeAll();
            }

            $log->OrderID = $this->owner->ID;

            $changes = $this->owner->getChangedFields(false, 2);

            foreach ($additionalObjects as $key => $object) {
                if (($object instanceof \DataObject) && $object->isChanged()) {
                    $changes[$key] = $object->getChangedFields(false, 2);
                } elseif ($object !== null) {
                    $changes[$key] = $object;
                }
            }

            if ($force || !empty($changes)) {
                $log->log($event, ['ChangeLog' => $changes, 'Automated' => true,]);

                if ($log->ID) {
                    $this->workingLogs[] = $log;
                }
            } else {
                if ($log->exists()) {
                    $log->delete();
                }

                $log->destroy();
            }
        }
    }

    public function LogsByStatusPriority()
    {
        $order = $this->owner;

        return $this->owner->OrderStatusLogs()->alterDataQuery(function ($query, $list) use ($order) {
            // Sort by status, so items with the same timeline will display in correct order
            $statuses = (array)singleton($order->OrderStatusLogs()->dataClass())->config()->order_status_by_completion;

            if (!empty($statuses)) {
                $sql = '(CASE "OrderLog"."Status"' . " \n";
                $i = 1;

                foreach ($statuses as $status) {
                    $sql .= 'WHEN \'' . $status . '\' THEN ' . "$i \n";
                    $i++;
                }

                $sql .= 'ELSE ' . "$i \n" . 'END)';

                $query->sort($sql, 'ASC', false);
            }
        });
    }

    public function getForEmail()
    {
        if ($billing = $this->owner->BillingAddress) {
            $name = implode(' ', array_filter([
                ucwords($billing->FirstName),
                ucwords($billing->Surname),
            ]));

            return $this->owner->LatestEmail ? $name ? $name . ' <' . $this->owner->LatestEmail . '>' : $this->owner->LatestEmail : '';
        } else {
            return $this->owner->LatestEmail ? $this->owner->Name ? $this->owner->Name . ' <' . $this->owner->LatestEmail . '>' : $this->owner->LatestEmail : '';
        }
    }

    public function Customer()
    {
        if ($this->owner->Member()->exists()) {
            return $this->owner->Member;
        }

        return $this->owner->BillingAddress();
    }
}
