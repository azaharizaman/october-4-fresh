<?php namespace Omsb\Procurement;

use Backend;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/4.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    public $require = ['Omsb.Organization', 'Omsb.Registrar', 'Omsb.Workflow', 'Omsb.Feeder'];
    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Procurement',
            'description' => 'Core procurement operations managing the complete purchase lifecycle from requisition to payment, with master catalog of all purchaseable items',
            'author' => 'Omsb',
            'icon' => 'icon-shopping-cart'
        ];
    }

    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        //
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        //
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'Omsb\Procurement\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'omsb.procurement.access_all' => [
                'tab' => 'Procurement',
                'label' => 'Full Access to Procurement Management'
            ],
            'omsb.procurement.manage_items' => [
                'tab' => 'Procurement',
                'label' => 'Manage Purchaseable Items'
            ],
            'omsb.procurement.manage_categories' => [
                'tab' => 'Procurement',
                'label' => 'Manage Item Categories'
            ],
            'omsb.procurement.manage_vendors' => [
                'tab' => 'Procurement',
                'label' => 'Manage Vendors'
            ],
            'omsb.procurement.purchase_requests' => [
                'tab' => 'Procurement',
                'label' => 'Manage Purchase Requests'
            ],
            'omsb.procurement.purchase_orders' => [
                'tab' => 'Procurement',
                'label' => 'Manage Purchase Orders'
            ],
            'omsb.procurement.vendor_quotations' => [
                'tab' => 'Procurement',
                'label' => 'Manage Vendor Quotations'
            ],
            'omsb.procurement.goods_receipt' => [
                'tab' => 'Procurement',
                'label' => 'Manage Goods Receipt Notes'
            ],
            'omsb.procurement.delivery_orders' => [
                'tab' => 'Procurement',
                'label' => 'Manage Delivery Orders'
            ],
            'omsb.procurement.view_reports' => [
                'tab' => 'Procurement',
                'label' => 'View Procurement Reports'
            ]
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'procurement' => [
                'label' => 'Procurement',
                'url' => Backend::url('omsb/procurement/purchaseableitems'),
                'icon' => 'icon-shopping-cart',
                'permissions' => ['omsb.procurement.*'],
                'order' => 200,
                'sideMenu' => [
                    'purchaseable_items' => [
                        'label' => 'Purchaseable Items',
                        'icon' => 'icon-list',
                        'url' => Backend::url('omsb/procurement/purchaseableitems'),
                        'permissions' => ['omsb.procurement.manage_items']
                    ],
                    'item_categories' => [
                        'label' => 'Item Categories',
                        'icon' => 'icon-tags',
                        'url' => Backend::url('omsb/procurement/itemcategories'),
                        'permissions' => ['omsb.procurement.manage_categories']
                    ],
                    'vendors' => [
                        'label' => 'Vendors',
                        'icon' => 'icon-truck',
                        'url' => Backend::url('omsb/procurement/vendors'),
                        'permissions' => ['omsb.procurement.manage_vendors']
                    ],
                    'separator1' => [
                        'label' => 'OPERATIONS',
                        'counter' => false
                    ],
                    'purchase_requests' => [
                        'label' => 'Purchase Requests',
                        'icon' => 'icon-file-text',
                        'url' => Backend::url('omsb/procurement/purchaserequests'),
                        'permissions' => ['omsb.procurement.purchase_requests']
                    ],
                    'vendor_quotations' => [
                        'label' => 'Vendor Quotations',
                        'icon' => 'icon-quote-left',
                        'url' => Backend::url('omsb/procurement/vendorquotations'),
                        'permissions' => ['omsb.procurement.vendor_quotations']
                    ],
                    'purchase_orders' => [
                        'label' => 'Purchase Orders',
                        'icon' => 'icon-shopping-bag',
                        'url' => Backend::url('omsb/procurement/purchaseorders'),
                        'permissions' => ['omsb.procurement.purchase_orders']
                    ],
                    'goods_receipt_notes' => [
                        'label' => 'Goods Receipt Notes',
                        'icon' => 'icon-download',
                        'url' => Backend::url('omsb/procurement/goodsreceiptnotes'),
                        'permissions' => ['omsb.procurement.goods_receipt']
                    ],
                    'delivery_orders' => [
                        'label' => 'Delivery Orders',
                        'icon' => 'icon-check-square',
                        'url' => Backend::url('omsb/procurement/deliveryorders'),
                        'permissions' => ['omsb.procurement.delivery_orders']
                    ],
                    'separator2' => [
                        'label' => 'REPORTS',
                        'counter' => false
                    ],
                    'reports' => [
                        'label' => 'Procurement Reports',
                        'icon' => 'icon-bar-chart',
                        'url' => Backend::url('omsb/procurement/reports'),
                        'permissions' => ['omsb.procurement.view_reports']
                    ]
                ]
            ]
        ];
    }
}
