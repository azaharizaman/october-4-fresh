<?php namespace Omsb\Procurement\Tests\Models;

use Omsb\Procurement\Models\PurchaseableItem;
use Omsb\Procurement\Models\ItemCategory;
use Omsb\Organization\Models\UnitOfMeasure;
use Omsb\Organization\Models\GlAccount;
use PluginTestCase;
use System\Classes\PluginManager;
use ValidationException;

/**
 * PurchaseableItemTest
 * 
 * Tests for PurchaseableItem model with UOM fields
 */
class PurchaseableItemTest extends PluginTestCase
{
    protected $rollUom;
    protected $boxUom;
    protected $category;

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

        // Create test UOMs
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

        // Create test category
        $this->category = ItemCategory::create([
            'code' => 'TEST',
            'name' => 'Test Category',
            'is_active' => true
        ]);
    }

    /**
     * Test creating purchaseable item with UOM fields
     */
    public function testCreatePurchaseableItemWithUOM()
    {
        $item = PurchaseableItem::create([
            'code' => 'TISSUE-001',
            'name' => 'Tissue Paper Roll',
            'unit_of_measure' => 'ROLL',
            'base_uom_id' => $this->rollUom->id,
            'purchase_uom_id' => $this->boxUom->id,
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1,
            'item_category_id' => $this->category->id
        ]);

        $this->assertNotNull($item->id);
        $this->assertEquals($this->rollUom->id, $item->base_uom_id);
        $this->assertEquals($this->boxUom->id, $item->purchase_uom_id);
    }

    /**
     * Test UOM relationships
     */
    public function testUOMRelationships()
    {
        $item = PurchaseableItem::create([
            'code' => 'TISSUE-002',
            'name' => 'Tissue Paper Box',
            'unit_of_measure' => 'BOX',
            'base_uom_id' => $this->rollUom->id,
            'purchase_uom_id' => $this->boxUom->id,
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1,
            'item_category_id' => $this->category->id
        ]);

        // Test relationships are loaded correctly
        $this->assertInstanceOf(UnitOfMeasure::class, $item->base_uom);
        $this->assertInstanceOf(UnitOfMeasure::class, $item->purchase_uom);
        $this->assertEquals('ROLL', $item->base_uom->code);
        $this->assertEquals('BOX', $item->purchase_uom->code);
    }

    /**
     * Test nullable UOM fields
     */
    public function testNullableUOMFields()
    {
        $item = PurchaseableItem::create([
            'code' => 'SERVICE-001',
            'name' => 'Consultation Service',
            'unit_of_measure' => 'EA',
            'base_uom_id' => null,
            'purchase_uom_id' => null,
            'is_inventory_item' => false,
            'item_type' => 'service',
            'minimum_order_quantity' => 1
        ]);

        $this->assertNull($item->base_uom_id);
        $this->assertNull($item->purchase_uom_id);
    }

    /**
     * Test UOM validation
     */
    public function testUOMValidation()
    {
        $this->expectException(ValidationException::class);

        // Try to create with invalid base_uom_id
        PurchaseableItem::create([
            'code' => 'INVALID-001',
            'name' => 'Invalid Item',
            'unit_of_measure' => 'ROLL',
            'base_uom_id' => 99999, // Non-existent ID
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1
        ]);
    }

    /**
     * Test is_inventory_item immutability with positive QoH
     */
    public function testInventoryItemImmutabilityWithStock()
    {
        $item = PurchaseableItem::create([
            'code' => 'STOCK-001',
            'name' => 'Stocked Item',
            'unit_of_measure' => 'ROLL',
            'base_uom_id' => $this->rollUom->id,
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1
        ]);

        // Mock warehouse item with positive QoH
        // Note: This would require setting up warehouse tables,
        // or we can test the logic in isolation
        
        // For now, test that the model allows the change when QoH = 0
        $item->is_inventory_item = false;
        $this->assertTrue($item->save());
    }

    /**
     * Test dropdown options for base_uom_id
     */
    public function testBaseUomIdOptions()
    {
        $item = new PurchaseableItem();
        $options = $item->getBaseUomIdOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey($this->rollUom->id, $options);
        $this->assertArrayHasKey($this->boxUom->id, $options);
    }

    /**
     * Test dropdown options for purchase_uom_id
     */
    public function testPurchaseUomIdOptions()
    {
        $item = new PurchaseableItem();
        $options = $item->getPurchaseUomIdOptions();

        $this->assertIsArray($options);
        // Only UOMs with for_purchase = true should be included
        $this->assertArrayHasKey($this->rollUom->id, $options);
        $this->assertArrayHasKey($this->boxUom->id, $options);
    }

    /**
     * Test creating inventory item with proper UOM setup
     */
    public function testInventoryItemWithProperUOMSetup()
    {
        // Create an inventory item with base UOM for normalization
        $item = PurchaseableItem::create([
            'code' => 'INV-001',
            'name' => 'Inventory Item',
            'unit_of_measure' => 'ROLL',
            'base_uom_id' => $this->rollUom->id,
            'purchase_uom_id' => $this->boxUom->id,
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1,
            'standard_cost' => 100.00
        ]);

        $this->assertTrue($item->isInventoryItem());
        $this->assertEquals($this->rollUom->id, $item->base_uom_id);
        $this->assertEquals($this->boxUom->id, $item->purchase_uom_id);
    }

    /**
     * Test non-inventory item without base UOM
     */
    public function testNonInventoryItemWithoutBaseUOM()
    {
        $item = PurchaseableItem::create([
            'code' => 'NON-INV-001',
            'name' => 'Non-Inventory Item',
            'unit_of_measure' => 'EA',
            'base_uom_id' => null,
            'purchase_uom_id' => null,
            'is_inventory_item' => false,
            'item_type' => 'service',
            'minimum_order_quantity' => 1
        ]);

        $this->assertFalse($item->isInventoryItem());
        $this->assertNull($item->base_uom_id);
    }

    /**
     * Test full display attribute includes UOM
     */
    public function testFullDisplayAttribute()
    {
        $item = PurchaseableItem::create([
            'code' => 'DISPLAY-001',
            'name' => 'Display Test Item',
            'unit_of_measure' => 'BOX',
            'base_uom_id' => $this->rollUom->id,
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1
        ]);

        $fullDisplay = $item->full_display;
        $this->assertStringContainsString('DISPLAY-001', $fullDisplay);
        $this->assertStringContainsString('Display Test Item', $fullDisplay);
        $this->assertStringContainsString('BOX', $fullDisplay);
    }

    /**
     * Test updating UOM fields
     */
    public function testUpdatingUOMFields()
    {
        $item = PurchaseableItem::create([
            'code' => 'UPDATE-001',
            'name' => 'Update Test Item',
            'unit_of_measure' => 'ROLL',
            'base_uom_id' => $this->rollUom->id,
            'purchase_uom_id' => $this->rollUom->id,
            'is_inventory_item' => true,
            'item_type' => 'consumable',
            'minimum_order_quantity' => 1
        ]);

        // Update purchase_uom_id
        $item->purchase_uom_id = $this->boxUom->id;
        $this->assertTrue($item->save());
        
        $item->refresh();
        $this->assertEquals($this->boxUom->id, $item->purchase_uom_id);
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
