<?php namespace Omsb\Inventory\Tests\Models;

use Omsb\Inventory\Models\InventoryLedger;
use Omsb\Inventory\Models\WarehouseItem;
use Omsb\Inventory\Models\Warehouse;
use Omsb\Procurement\Models\PurchaseableItem;
use Omsb\Procurement\Models\ItemCategory;
use Omsb\Organization\Models\UnitOfMeasure;
use Omsb\Organization\Models\Site;
use Omsb\Organization\Services\UOMNormalizationService;
use PluginTestCase;
use System\Classes\PluginManager;
use Carbon\Carbon;

/**
 * InventoryLedgerTest
 * 
 * Tests for InventoryLedger model with UOM audit trail
 */
class InventoryLedgerTest extends PluginTestCase
{
    protected $rollUom;
    protected $boxUom;
    protected $pack6Uom;
    protected $warehouseItem;
    protected $legacyUom;
    protected $uomService;

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

        $this->uomService = new UOMNormalizationService();

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

        // Create legacy inventory UOM
        $this->legacyUom = \Omsb\Inventory\Models\UnitOfMeasure::create([
            'code' => 'EA',
            'name' => 'Each',
            'uom_type' => 'count',
            'is_base_unit' => true,
            'is_active' => true
        ]);

        // Create test site and warehouse
        $site = Site::create([
            'code' => 'HQ',
            'name' => 'Head Quarters',
            'is_active' => true
        ]);

        $warehouse = Warehouse::create([
            'site_id' => $site->id,
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
            'is_active' => true,
            'allows_negative_stock' => false
        ]);

        // Create test category and purchaseable item
        $category = ItemCategory::create([
            'code' => 'TEST',
            'name' => 'Test Category',
            'is_active' => true
        ]);

        $purchaseableItem = PurchaseableItem::create([
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

        // Create warehouse item
        $this->warehouseItem = WarehouseItem::create([
            'purchaseable_item_id' => $purchaseableItem->id,
            'warehouse_id' => $warehouse->id,
            'base_uom_id' => $this->rollUom->id,
            'default_uom_id' => $this->legacyUom->id,
            'primary_inventory_uom_id' => $this->legacyUom->id,
            'quantity_on_hand' => 0,
            'cost_method' => 'FIFO',
            'is_active' => true
        ]);
    }

    /**
     * Test creating ledger entry with base UOM
     */
    public function testCreateLedgerEntryWithBaseUOM()
    {
        // Receive 2 boxes (144 rolls in base UOM)
        $transactionQty = 2; // boxes
        $normalized = $this->uomService->normalize($transactionQty, $this->boxUom);

        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => $normalized['base_quantity'], // 144 rolls
            'unit_cost' => 5.00,
            'reference_number' => 'GRN-001',
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'original_transaction_uom_id' => $this->boxUom->id,
            'original_transaction_quantity' => $transactionQty,
            'quantity_in_transaction_uom' => $transactionQty,
            'quantity_in_default_uom' => $normalized['base_quantity'],
            'conversion_factor_used' => 72
        ]);

        $this->assertNotNull($ledgerEntry->id);
        $this->assertEquals(144, $ledgerEntry->quantity_change);
        $this->assertEquals($this->rollUom->id, $ledgerEntry->base_uom_id);
        $this->assertEquals($this->boxUom->id, $ledgerEntry->original_transaction_uom_id);
        $this->assertEquals(2, $ledgerEntry->original_transaction_quantity);
    }

    /**
     * Test ledger entry updates warehouse item quantity
     */
    public function testLedgerEntryUpdatesWarehouseQuantity()
    {
        $this->warehouseItem->quantity_on_hand = 100;
        $this->warehouseItem->save();

        // Add 72 rolls (1 box)
        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => 72,
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'quantity_in_transaction_uom' => 72,
            'quantity_in_default_uom' => 72,
            'conversion_factor_used' => 1
        ]);

        $this->warehouseItem->refresh();
        $this->assertEquals(172, $this->warehouseItem->quantity_on_hand);
        $this->assertEquals(100, $ledgerEntry->quantity_before);
        $this->assertEquals(172, $ledgerEntry->quantity_after);
    }

    /**
     * Test ledger entry with transaction in different UOM
     */
    public function testLedgerEntryWithDifferentUOM()
    {
        // Receive 10 PACK6 (60 rolls in base UOM)
        $transactionQty = 10;
        $normalized = $this->uomService->normalize($transactionQty, $this->pack6Uom);

        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 2,
            'transaction_type' => 'receipt',
            'quantity_change' => $normalized['base_quantity'], // 60 rolls
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'original_transaction_uom_id' => $this->pack6Uom->id,
            'original_transaction_quantity' => $transactionQty,
            'quantity_in_transaction_uom' => $transactionQty,
            'quantity_in_default_uom' => $normalized['base_quantity'],
            'conversion_factor_used' => 6
        ]);

        $this->assertEquals(60, $ledgerEntry->quantity_change);
        $this->assertEquals(10, $ledgerEntry->original_transaction_quantity);
        $this->assertEquals($this->pack6Uom->id, $ledgerEntry->original_transaction_uom_id);
    }

    /**
     * Test ledger entry audit trail
     */
    public function testLedgerAuditTrail()
    {
        // Record transaction in BOX but store in ROLL
        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => 144, // 2 boxes in rolls
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'original_transaction_uom_id' => $this->boxUom->id,
            'original_transaction_quantity' => 2, // Original: 2 boxes
            'quantity_in_transaction_uom' => 2,
            'quantity_in_default_uom' => 144,
            'conversion_factor_used' => 72,
            'notes' => 'Received 2 boxes from vendor'
        ]);

        // Verify audit trail preserves original transaction details
        $this->assertEquals(144, $ledgerEntry->quantity_change); // Stored in base (ROLL)
        $this->assertEquals(2, $ledgerEntry->original_transaction_quantity); // Original (BOX)
        $this->assertEquals(72, $ledgerEntry->conversion_factor_used);
    }

    /**
     * Test ledger entry immutability
     */
    public function testLedgerEntryImmutability()
    {
        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => 100,
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'quantity_in_transaction_uom' => 100,
            'quantity_in_default_uom' => 100,
            'conversion_factor_used' => 1
        ]);

        $this->expectException(\Exception::class);
        
        // Ledger entries cannot be deleted
        $ledgerEntry->delete();
    }

    /**
     * Test ledger locking prevents modification
     */
    public function testLockedLedgerPreventModification()
    {
        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => 100,
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'quantity_in_transaction_uom' => 100,
            'quantity_in_default_uom' => 100,
            'conversion_factor_used' => 1,
            'is_locked' => false
        ]);

        // Lock the entry
        $ledgerEntry->lock();
        $this->assertTrue($ledgerEntry->is_locked);

        $this->expectException(\Exception::class);

        // Try to modify locked entry
        $ledgerEntry->notes = 'Cannot modify';
        $ledgerEntry->save();
    }

    /**
     * Test ledger entry with cost calculation
     */
    public function testLedgerEntryWithCostCalculation()
    {
        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => 100,
            'unit_cost' => 5.50,
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'quantity_in_transaction_uom' => 100,
            'quantity_in_default_uom' => 100,
            'conversion_factor_used' => 1
        ]);

        // Total cost should be auto-calculated
        $this->assertEquals(550, $ledgerEntry->total_cost); // 100 * 5.50
    }

    /**
     * Test ledger entry relationships
     */
    public function testLedgerRelationships()
    {
        $ledgerEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => 144,
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'original_transaction_uom_id' => $this->boxUom->id,
            'quantity_in_transaction_uom' => 2,
            'quantity_in_default_uom' => 144,
            'conversion_factor_used' => 72
        ]);

        // Test relationships
        $this->assertInstanceOf(WarehouseItem::class, $ledgerEntry->warehouse_item);
        $this->assertInstanceOf(UnitOfMeasure::class, $ledgerEntry->base_uom);
        $this->assertInstanceOf(UnitOfMeasure::class, $ledgerEntry->original_transaction_uom);
        $this->assertEquals('ROLL', $ledgerEntry->base_uom->code);
        $this->assertEquals('BOX', $ledgerEntry->original_transaction_uom->code);
    }

    /**
     * Test ledger entry direction attribute
     */
    public function testLedgerDirectionAttribute()
    {
        $receiptEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'GoodsReceiptNote',
            'document_id' => 1,
            'transaction_type' => 'receipt',
            'quantity_change' => 100,
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'quantity_in_transaction_uom' => 100,
            'quantity_in_default_uom' => 100,
            'conversion_factor_used' => 1
        ]);

        $this->assertEquals('IN', $receiptEntry->direction);
        $this->assertTrue($receiptEntry->isReceipt());

        $issueEntry = InventoryLedger::createEntry([
            'warehouse_item_id' => $this->warehouseItem->id,
            'base_uom_id' => $this->rollUom->id,
            'document_type' => 'MaterialRequestIssuance',
            'document_id' => 1,
            'transaction_type' => 'issue',
            'quantity_change' => -50,
            'transaction_date' => Carbon::now(),
            'transaction_uom_id' => $this->legacyUom->id,
            'quantity_in_transaction_uom' => 50,
            'quantity_in_default_uom' => 50,
            'conversion_factor_used' => 1
        ]);

        $this->assertEquals('OUT', $issueEntry->direction);
        $this->assertTrue($issueEntry->isIssue());
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
