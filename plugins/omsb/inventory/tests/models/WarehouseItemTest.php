<?php namespace Omsb\Inventory\Tests\Models;

use Omsb\Inventory\Models\WarehouseItem;
use Omsb\Inventory\Models\Warehouse;
use Omsb\Procurement\Models\PurchaseableItem;
use Omsb\Procurement\Models\ItemCategory;
use Omsb\Organization\Models\UnitOfMeasure;
use Omsb\Organization\Models\Site;
use PluginTestCase;
use System\Classes\PluginManager;
use ValidationException;

/**
 * WarehouseItemTest
 * 
 * Tests for WarehouseItem model with base UOM normalization
 */
class WarehouseItemTest extends PluginTestCase
{
    protected $rollUom;
    protected $boxUom;
    protected $pack6Uom;
    protected $warehouse;
    protected $purchaseableItem;
    protected $legacyUom;

    /**
     * Set up the test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        // Register all plugins
        $pluginManager = PluginManager::instance();
        $pluginManager->registerAll(true);
        $pluginManager->bootAll(true);

        // Create test UOMs in Organization plugin
        $this->rollUom = UnitOfMeasure::create([
            'code' => 'ROLL',
            'name' => 'Roll',
            'uom_type' => 'count',
            'base_uom_id' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);

        $this->pack6Uom = UnitOfMeasure::create([
            'code' => 'PACK6',
            'name' => 'Pack of 6',
            'uom_type' => 'count',
            'base_uom_id' => $this->rollUom->id,
            'conversion_to_base_factor' => 6,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);

        $this->boxUom = UnitOfMeasure::create([
            'code' => 'BOX',
            'name' => 'Box',
            'uom_type' => 'count',
            'base_uom_id' => $this->rollUom->id,
            'conversion_to_base_factor' => 72,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);

        // Create legacy inventory UOM for backward compatibility testing
        $this->legacyUom = \Omsb\Inventory\Models\UnitOfMeasure::create([
            'code' => 'EA',
            'name' => 'Each',
            'uom_type' => 'count',
            'is_base_unit' => true,
            'is_active' => true
        ]);

        // Create test site
        $site = Site::create([
            'code' => 'HQ',
            'name' => 'Head Quarters',
            'is_active' => true
        ]);

        // Create test warehouse
        $this->warehouse = Warehouse::create([
            'site_id' => $site->id,
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
            'is_active' => true,
            'allows_negative_stock' => false
        ]);

        // Create test category
        $category = ItemCategory::create([
            'code' => 'TEST',
            'name' => 'Test Category',
            'is_active' => true
        ]);

        // Create test purchaseable item
        $this->purchaseableItem = PurchaseableItem::create([
            'code' => 'TISSUE-001',
            'name' => 'Tissue Paper',
            'unit_of_measure' => 'ROLL',
            'base_uom_id' => $this->rollUom->id,
            'purchase_uom_id' => $this->boxUom->id,
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1,
            'item_category_id' => $category->id
        ]);
    }

    /**
     * Test creating warehouse item with base UOM
     */
    public function testCreateWarehouseItemWithBaseUOM()
    {
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'display_uom_id' => $this->boxUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 144, // 2 boxes in base unit (ROLL)
            'quantity_reserved' => 0,
            'minimum_stock_level' => 72,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        $this->assertNotNull($warehouseItem->id);
        $this->assertEquals($this->rollUom->id, $warehouseItem->base_uom_id);
        $this->assertEquals($this->boxUom->id, $warehouseItem->display_uom_id);
        $this->assertEquals(144, $warehouseItem->quantity_on_hand);
    }

    /**
     * Test base UOM relationship
     */
    public function testBaseUOMRelationship()
    {
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'display_uom_id' => $this->pack6Uom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 60, // 10 packs in base unit
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        $this->assertInstanceOf(UnitOfMeasure::class, $warehouseItem->base_uom);
        $this->assertEquals('ROLL', $warehouseItem->base_uom->code);
        
        $this->assertInstanceOf(UnitOfMeasure::class, $warehouseItem->display_uom);
        $this->assertEquals('PACK6', $warehouseItem->display_uom->code);
    }

    /**
     * Test quantity on hand is always in base UOM
     */
    public function testQuantityOnHandInBaseUOM()
    {
        // Create warehouse item with quantity in base UOM
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 216, // 3 boxes = 216 rolls
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        // Quantity should remain in base UOM (ROLL)
        $this->assertEquals(216, $warehouseItem->quantity_on_hand);
        
        // If we want to display in BOX, we would use UOMNormalizationService
        // to convert 216 rolls to 3 boxes
    }

    /**
     * Test warehouse-item uniqueness constraint
     */
    public function testWarehouseItemUniqueness()
    {
        // Create first warehouse item
        WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 100,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        $this->expectException(ValidationException::class);

        // Try to create duplicate
        WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 200,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);
    }

    /**
     * Test available quantity calculation
     */
    public function testAvailableQuantity()
    {
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 30,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        $this->assertEquals(70, $warehouseItem->available_quantity);
    }

    /**
     * Test adjust quantity method
     */
    public function testAdjustQuantity()
    {
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 100,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        // Add stock
        $warehouseItem->adjustQuantity(50);
        $this->assertEquals(150, $warehouseItem->quantity_on_hand);

        // Remove stock
        $warehouseItem->adjustQuantity(-30);
        $this->assertEquals(120, $warehouseItem->quantity_on_hand);
    }

    /**
     * Test prevent negative stock
     */
    public function testPreventNegativeStock()
    {
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 50,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        // Try to remove more than available
        $result = $warehouseItem->adjustQuantity(-100, false); // Pass false for allows_negative_stock
        $this->assertFalse($result);
        $this->assertEquals(50, $warehouseItem->quantity_on_hand); // Should remain unchanged
    }

    /**
     * Test dropdown options for base_uom_id
     */
    public function testBaseUomIdOptions()
    {
        $warehouseItem = new WarehouseItem();
        $options = $warehouseItem->getBaseUomIdOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey($this->rollUom->id, $options);
        // Only approved, active, inventory UOMs should be included
    }

    /**
     * Test dropdown options for display_uom_id
     */
    public function testDisplayUomIdOptions()
    {
        $warehouseItem = new WarehouseItem();
        $options = $warehouseItem->getDisplayUomIdOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey($this->boxUom->id, $options);
    }

    /**
     * Test below minimum stock level
     */
    public function testBelowMinimumStockLevel()
    {
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 50,
            'minimum_stock_level' => 100,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        $this->assertTrue($warehouseItem->isBelowMinimum());
    }

    /**
     * Test reserve quantity
     */
    public function testReserveQuantity()
    {
        $warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $this->purchaseableItem->id,
            'warehouse_id' => $this->warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);

        // Reserve 30 units
        $result = $warehouseItem->reserveQuantity(30);
        $this->assertTrue($result);
        $this->assertEquals(30, $warehouseItem->quantity_reserved);
        $this->assertEquals(70, $warehouseItem->available_quantity);
    }

    /**
     * Tear down the test environment
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $pluginManager = PluginManager::instance();
        $pluginManager->unregisterAll();
    }
}
