<?php namespace Omsb\Inventory;

use Backend;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/4.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    public $require = ['Omsb.Organization', 'Omsb.Registrar', 'Omsb.Workflow', 'Omsb.Feeder', 'Omsb.Procurement'];
    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Inventory',
            'description' => 'Complete inventory management system with warehouses, stock movements, physical counts, and valuations',
            'author' => 'Omsb',
            'icon' => 'icon-cubes'
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
        return [];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'omsb.inventory.access_all' => [
                'tab' => 'Inventory',
                'label' => 'Full Access to Inventory Management'
            ],
            'omsb.inventory.manage_warehouses' => [
                'tab' => 'Inventory',
                'label' => 'Manage Warehouses'
            ],
            'omsb.inventory.manage_items' => [
                'tab' => 'Inventory',
                'label' => 'Manage Warehouse Items'
            ],
            'omsb.inventory.manage_uom' => [
                'tab' => 'Inventory',
                'label' => 'Manage Units of Measure'
            ],
            'omsb.inventory.goods_receipt' => [
                'tab' => 'Inventory',
                'label' => 'Manage Goods Receipt (MRN)'
            ],
            'omsb.inventory.material_issue' => [
                'tab' => 'Inventory',
                'label' => 'Manage Material Issue (MRI)'
            ],
            'omsb.inventory.stock_adjustment' => [
                'tab' => 'Inventory',
                'label' => 'Manage Stock Adjustments'
            ],
            'omsb.inventory.stock_transfer' => [
                'tab' => 'Inventory',
                'label' => 'Manage Stock Transfers'
            ],
            'omsb.inventory.physical_count' => [
                'tab' => 'Inventory',
                'label' => 'Manage Physical Counts'
            ],
            'omsb.inventory.stock_reservation' => [
                'tab' => 'Inventory',
                'label' => 'Manage Stock Reservations'
            ],
            'omsb.inventory.lot_serial_tracking' => [
                'tab' => 'Inventory',
                'label' => 'Manage Lot/Batch & Serial Tracking'
            ],
            'omsb.inventory.inventory_ledger' => [
                'tab' => 'Inventory',
                'label' => 'View Inventory Ledger'
            ],
            'omsb.inventory.inventory_periods' => [
                'tab' => 'Inventory',
                'label' => 'Manage Inventory Periods'
            ],
            'omsb.inventory.inventory_valuation' => [
                'tab' => 'Inventory',
                'label' => 'Manage Inventory Valuations'
            ]
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'inventory' => [
                'label' => 'Inventory',
                'url' => Backend::url('omsb/inventory/warehouses'),
                'icon' => 'icon-cubes',
                'permissions' => ['omsb.inventory.*'],
                'order' => 300,
                'sideMenu' => [
                    'warehouses' => [
                        'label' => 'Warehouses',
                        'icon' => 'icon-warehouse',
                        'url' => Backend::url('omsb/inventory/warehouses'),
                        'permissions' => ['omsb.inventory.manage_warehouses']
                    ],
                    'warehouse_items' => [
                        'label' => 'Stock Items',
                        'icon' => 'icon-box',
                        'url' => Backend::url('omsb/inventory/warehouseitems'),
                        'permissions' => ['omsb.inventory.manage_items']
                    ],
                    'separator1' => [
                        'label' => 'OPERATIONS',
                        'counter' => false
                    ],
                    'mrns' => [
                        'label' => 'Goods Receipt (MRN)',
                        'icon' => 'icon-sign-in',
                        'url' => Backend::url('omsb/inventory/mrns'),
                        'permissions' => ['omsb.inventory.goods_receipt']
                    ],
                    'mris' => [
                        'label' => 'Material Issue (MRI)',
                        'icon' => 'icon-sign-out',
                        'url' => Backend::url('omsb/inventory/mris'),
                        'permissions' => ['omsb.inventory.material_issue']
                    ],
                    'stock_adjustments' => [
                        'label' => 'Stock Adjustments',
                        'icon' => 'icon-sliders',
                        'url' => Backend::url('omsb/inventory/stockadjustments'),
                        'permissions' => ['omsb.inventory.stock_adjustment']
                    ],
                    'stock_transfers' => [
                        'label' => 'Stock Transfers',
                        'icon' => 'icon-exchange',
                        'url' => Backend::url('omsb/inventory/stocktransfers'),
                        'permissions' => ['omsb.inventory.stock_transfer']
                    ],
                    'physical_counts' => [
                        'label' => 'Physical Counts',
                        'icon' => 'icon-list-ol',
                        'url' => Backend::url('omsb/inventory/physicalcounts'),
                        'permissions' => ['omsb.inventory.physical_count']
                    ],
                    'separator2' => [
                        'label' => 'MANAGEMENT',
                        'counter' => false
                    ],
                    'stock_reservations' => [
                        'label' => 'Stock Reservations',
                        'icon' => 'icon-bookmark',
                        'url' => Backend::url('omsb/inventory/stockreservations'),
                        'permissions' => ['omsb.inventory.stock_reservation']
                    ],
                    'lot_batches' => [
                        'label' => 'Lot/Batch Tracking',
                        'icon' => 'icon-barcode',
                        'url' => Backend::url('omsb/inventory/lotbatches'),
                        'permissions' => ['omsb.inventory.lot_serial_tracking']
                    ],
                    'serial_numbers' => [
                        'label' => 'Serial Numbers',
                        'icon' => 'icon-qrcode',
                        'url' => Backend::url('omsb/inventory/serialnumbers'),
                        'permissions' => ['omsb.inventory.lot_serial_tracking']
                    ],
                    'separator3' => [
                        'label' => 'REPORTING',
                        'counter' => false
                    ],
                    'inventory_ledger' => [
                        'label' => 'Inventory Ledger',
                        'icon' => 'icon-book',
                        'url' => Backend::url('omsb/inventory/inventoryledgers'),
                        'permissions' => ['omsb.inventory.inventory_ledger']
                    ],
                    'inventory_periods' => [
                        'label' => 'Inventory Periods',
                        'icon' => 'icon-calendar',
                        'url' => Backend::url('omsb/inventory/inventoryperiods'),
                        'permissions' => ['omsb.inventory.inventory_periods']
                    ],
                    'inventory_valuations' => [
                        'label' => 'Inventory Valuations',
                        'icon' => 'icon-money',
                        'url' => Backend::url('omsb/inventory/inventoryvaluations'),
                        'permissions' => ['omsb.inventory.inventory_valuation']
                    ],
                    'separator4' => [
                        'label' => 'CONFIGURATION',
                        'counter' => false
                    ],
                    'unit_of_measures' => [
                        'label' => 'Units of Measure',
                        'icon' => 'icon-balance-scale',
                        'url' => Backend::url('omsb/inventory/unitofmeasures'),
                        'permissions' => ['omsb.inventory.manage_uom']
                    ],
                    'uom_conversions' => [
                        'label' => 'UOM Conversions',
                        'icon' => 'icon-random',
                        'url' => Backend::url('omsb/inventory/uomconversions'),
                        'permissions' => ['omsb.inventory.manage_uom']
                    ]
                ]
            ]
        ];
    }
}
