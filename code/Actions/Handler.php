<?php namespace Milkyway\SS\Shop\OrderHistory\Actions;

/**
 * Milkyway Multimedia
 * Handler.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Controller;

class Handler extends \Controller
{
    private static $allowed_actions = [
        'index',
    ];

    private static $url_handlers = [
        '$ID/$Action!' => '$Action',
        ''         => 'index',
    ];

    protected $controller;
    protected $order;

    public function __construct($controller, $order)
    {
        $this->controller = $controller;
        $this->order = $order;
        parent::__construct();
    }

    public function getOrder() {
        return $this->order;
    }

    /*
     * Display default order view if no action has been used
     */
    public function index($r)
    {
        return $this->controller->redirect($this->order->Link());
    }

    public function Link($action = '') {
        return Controller::join_links($this->order->Link(), $action);
    }

    public function getViewer($action) {
        return $this->controller->getViewer('orders_' . $action);
    }
}
