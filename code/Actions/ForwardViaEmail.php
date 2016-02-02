<?php namespace Milkyway\SS\Shop\OrderHistory\Actions;

/**
 * Milkyway Multimedia
 * ForwardViaEmail.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\Shop\OrderHistory\Contracts\HasOrderFormActions;

use Extension;
use Form;
use FormAction;
use FormActionLink;
use FieldList;
use EmailField;
use TextField;
use TextareaField;
use ReadonlyField;
use RequiredFields;
use Member;
use ShopConfig;
use Email;

class ForwardViaEmail extends Extension implements HasOrderFormActions
{
    private static $allowed_actions = [
        'forward-via-email',
        'forward_via_email',
        'ForwardViaEmailForm',
    ];

    public function forward_via_email()
    {
        return [
            'Title' => 'Forward via email',
            'Form'  => $this->ForwardViaEmailForm(),
        ];
    }

    public function ForwardViaEmailForm()
    {
        $order = $this->owner->Order;
        $email = $order->LatestEmail;

        $required = $email ? [] : ['Email'];

        $form = Form::create(
            $this->owner,
            __FUNCTION__,
            FieldList::create(
                ReadonlyField::create('READONLY_Reference', _t('Order.REFERENCE', 'Reference'), $order->Reference),
                $to = EmailField::create('To', _t('PublicOrderStatus.SEND_TO', 'Send to')),
                TextField::create('Cc', 'Cc'),
                $from = EmailField::create('From', 'From'),
                TextField::create('Subject', 'Subject',
                    _t('PublicOrderStatus.EMAIL_SUBJECT', 'Web Order - {reference}', null, [
                        'reference' => $order->Reference,
                    ])),
                TextareaField::create('Content',
                    _t('PublicOrderStatus.EMAIL_CONTENT', 'Content to append to top of email'))
            ),
            FieldList::create(
                FormAction::create('sendViaEmail', 'Send'),
                FormActionLink::create('action_returnToOrder', 'Return to order', $this->owner->Link())
            ),
            RequiredFields::create($required)
        );

        if ($email) {
            $to->setAttribute('placeholder', $email);
        }

        if ($member = Member::currentUser()) {
            $from->setAttribute('placeholder', $member->Email);
        }

        $this->owner->extend('update' . __FUNCTION__, $form);

        return $form;
    }

    public function sendViaEmail($data, $form, $r)
    {
        $email = $this->buildEmail($form->Data);
        $email->send();
        $this->owner->Order->extend('onForwardViaEmail', $email);

        $form->sessionMessage(_t('Order.EMAIL_SENT', 'Email has been sent to: {email}', [
            'email' => $email->To(),
        ]), "good alert alert-success");

        return $this->owner->redirectBack();
    }

    public function updateOrderActionForm($form, $order)
    {
        $form->Actions()->push(
            FormActionLink::create('action_forwardViaEmail', 'Forward via email',
                $this->owner->Link('forward-via-email'))
                ->setForm($form)
        );
    }

    protected function buildEmail($vars = [])
    {
        $order = $this->owner->Order;

        if (empty($vars['To'])) {
            $vars['To'] = $order->LatestEmail;
        }

        if (empty($vars['From'])) {
            $vars['From'] = ($member = Member::currentUser()) ? $member->Email : (ShopConfig::config()->email_from ? ShopConfig::config()->email_from : Email::config()->admin_email);
        }

        if (empty($vars['Subject'])) {
            $vars['Subject'] = _t('Order.EMAIL_SUBJECT', 'Order: {reference}', [
                'reference' => $order->Reference,
            ]);
        }

        singleton('require')->clear();

        $email = Email::create();
        $email->setTemplate(empty($vars['Template']) ? 'Order_ForwardedEmail' : $vars['Template']);
        $email->setFrom($vars['From']);
        $email->setTo($vars['To']);
        $email->setSubject($vars['Subject']);
        $email->populateTemplate(array_merge($vars, [
            'Order' => $order,
        ]));

//        echo $email->customise(array_merge($vars, [
//            'Order' => $order,
//        ]))->renderWith('Order_ForwardedEmail'); die;

        return $email;
    }
}
