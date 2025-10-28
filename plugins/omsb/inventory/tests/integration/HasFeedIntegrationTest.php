<?php namespace Omsb\Inventory\Tests\Integration;

use Omsb\Inventory\Models\Warehouse;
use Omsb\Inventory\Models\WarehouseItem;
use Omsb\Inventory\Models\Mrn;
use Omsb\Inventory\Models\Mri;
use Omsb\Inventory\Models\StockTransfer;
use Omsb\Inventory\Models\StockAdjustment;
use Omsb\Inventory\Models\PhysicalCount;
use Omsb\Inventory\Models\InventoryValuation;
use Omsb\Feeder\Models\Feed;
use Omsb\Organization\Models\Site;
use Omsb\Procurement\Models\PurchaseableItem;
use PluginTestCase;
use Backend\Models\User as BackendUser;

/**
 * HasFeed Integration Tests for Inventory Plugin
 * 
 * Tests real-world usage of HasFeed trait with Inventory models:
 * - Warehouse
 * - WarehouseItem
 * - Mrn (Material Received Note)
 * - Mri (Material Request Issuance)
 * - StockTransfer
 * - StockAdjustment
 * - PhysicalCount
 * - InventoryValuation
 */
class HasFeedIntegrationTest extends PluginTestCase
{
    protected $testUser;
    protected $testSite;
    protected $testWarehouse;
    protected $testItem;

    public function setUp(): void
    {
        parent::setUp();

        // Create test backend user
        $this->testUser = BackendUser::create([
            'login' => 'invintegration',
            'email' => 'invinit@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'first_name' => 'Inventory',
            'last_name' => 'Tester',
            'is_superuser' => false,
        ]);

        // Create test site
        $this->testSite = Site::create([
            'site_code' => 'INV-TEST',
            'name' => 'Inventory Test Site',
            'is_active' => true,
        ]);

        // Create test warehouse
        $this->testWarehouse = Warehouse::create([
            'site_id' => $this->testSite->id,
            'code' => 'INV-WH-001',
            'name' => 'Integration Test Warehouse',
            'status' => 'active',
            'is_receiving_warehouse' => true,
        ]);

        // Create test purchaseable item
        $this->testItem = PurchaseableItem::create([
            'code' => 'INV-ITEM-001',
            'name' => 'Inventory Test Item',
            'item_type' => 'consumable',
            'is_inventory_item' => true,
        ]);

        // Authenticate
        $this->actingAs($this->testUser);
    }

    public function tearDown(): void
    {
        // Clean up test data
        WarehouseItem::where('warehouse_id', $this->testWarehouse->id)->each(function ($item) {
            $item->deleteAllFeeds();
            $item->forceDelete();
        });

        Mrn::where('notes', 'LIKE', 'Integration test%')->each(function ($mrn) {
            $mrn->deleteAllFeeds();
            $mrn->forceDelete();
        });

        Mri::where('notes', 'LIKE', 'Integration test%')->each(function ($mri) {
            $mri->deleteAllFeeds();
            $mri->forceDelete();
        });

        StockTransfer::where('notes', 'LIKE', 'Integration test%')->each(function ($transfer) {
            $transfer->deleteAllFeeds();
            $transfer->forceDelete();
        });

        StockAdjustment::where('notes', 'LIKE', 'Integration test%')->each(function ($adj) {
            $adj->deleteAllFeeds();
            $adj->forceDelete();
        });

        PhysicalCount::where('notes', 'LIKE', 'Integration test%')->each(function ($count) {
            $count->deleteAllFeeds();
            $count->forceDelete();
        });

        if ($this->testWarehouse) {
            $this->testWarehouse->deleteAllFeeds();
            $this->testWarehouse->forceDelete();
        }

        if ($this->testItem) {
            $this->testItem->deleteAllFeeds();
            $this->testItem->forceDelete();
        }

        if ($this->testSite) {
            $this->testSite->forceDelete();
        }

        if ($this->testUser) {
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    /**
     * Test Warehouse feed creation and status changes
     */
    public function testWarehouseFeedCreation()
    {
        $warehouse = Warehouse::create([
            'site_id' => $this->testSite->id,
            'code' => 'INV-WH-TEST-001',
            'name' => 'Feed Test Warehouse',
            'status' => 'active',
            'is_receiving_warehouse' => false,
        ]);

        // Verify creation feed
        $this->assertTrue($warehouse->hasFeeds());
        
        $creationFeed = $warehouse->feeds()->first();
        $this->assertStringContainsString('Feed Test Warehouse', $creationFeed->message);
        $this->assertStringContainsString('INV-WH-TEST-001', $creationFeed->message);

        // Test status change
        $warehouse->status = 'maintenance';
        $warehouse->save();
        $warehouse->recordAction('maintenance', ['reason' => 'Scheduled maintenance']);

        $maintenanceFeed = $warehouse->getFeedsByAction('maintenance')->first();
        $this->assertNotNull($maintenanceFeed);
        $this->assertArrayHasKey('reason', $maintenanceFeed->metadata);

        // Cleanup
        $warehouse->deleteAllFeeds();
        $warehouse->forceDelete();
    }

    /**
     * Test WarehouseItem feed with custom placeholders
     */
    public function testWarehouseItemCustomPlaceholderFeed()
    {
        $warehouseItem = WarehouseItem::create([
            'warehouse_id' => $this->testWarehouse->id,
            'purchaseable_item_id' => $this->testItem->id,
            'quantity_on_hand' => 100,
            'minimum_stock_level' => 20,
            'is_active' => true,
        ]);

        // Verify creation feed
        $this->assertTrue($warehouseItem->hasFeeds());
        
        $feed = $warehouseItem->feeds()->first();
        
        // Should contain purchaseable item name via custom placeholder
        $this->assertStringContainsString('Inventory Test Item', $feed->message);

        // Test reorder trigger action
        $warehouseItem->recordAction('reorder_triggered', [
            'current_qty' => 15,
            'minimum_qty' => 20,
        ]);

        $reorderFeed = $warehouseItem->getFeedsByAction('reorder_triggered')->first();
        $this->assertNotNull($reorderFeed);
        $this->assertArrayHasKey('current_qty', $reorderFeed->metadata);

        // Cleanup
        $warehouseItem->deleteAllFeeds();
        $warehouseItem->forceDelete();
    }

    /**
     * Test MRN workflow feeds
     */
    public function testMrnWorkflowFeeds()
    {
        $mrn = Mrn::create([
            'warehouse_id' => $this->testWarehouse->id,
            'received_date' => now(),
            'status' => 'draft',
            'total_received_value' => 1500.00,
            'notes' => 'Integration test MRN',
        ]);

        // Verify creation feed
        $this->assertTrue($mrn->hasFeeds());

        // Test workflow progression
        $mrn->recordAction('submitted');
        $mrn->recordAction('approved', ['approved_by' => 'Warehouse Manager']);
        $mrn->recordAction('completed');

        $timeline = $mrn->getFeedTimeline();
        $this->assertCount(4, $timeline, 'Should have 4 feeds: created, submitted, approved, completed');

        // Verify approved feed
        $approvedFeed = $mrn->getFeedsByAction('approved')->first();
        $this->assertNotNull($approvedFeed);
        $this->assertArrayHasKey('approved_by', $approvedFeed->metadata);

        // Cleanup
        $mrn->deleteAllFeeds();
        $mrn->forceDelete();
    }

    /**
     * Test MRI issuance workflow feeds
     */
    public function testMriIssuanceWorkflowFeeds()
    {
        $mri = Mri::create([
            'warehouse_id' => $this->testWarehouse->id,
            'issue_date' => now(),
            'issue_purpose' => 'department_usage',
            'status' => 'draft',
            'total_issue_value' => 800.00,
            'notes' => 'Integration test MRI',
        ]);

        // Verify creation feed
        $this->assertTrue($mri->hasFeeds());

        // Test workflow
        $mri->recordAction('submitted', ['requested_by' => 'Department Head']);
        $mri->recordAction('approved');
        $mri->recordAction('completed', ['issued_to' => 'Engineering Dept']);

        // Verify completion feed
        $completedFeed = $mri->getFeedsByAction('completed')->first();
        $this->assertNotNull($completedFeed);
        $this->assertArrayHasKey('issued_to', $completedFeed->metadata);

        // Cleanup
        $mri->deleteAllFeeds();
        $mri->forceDelete();
    }

    /**
     * Test StockTransfer inter-warehouse movement feeds
     */
    public function testStockTransferMovementFeeds()
    {
        // Create destination warehouse
        $destWarehouse = Warehouse::create([
            'site_id' => $this->testSite->id,
            'code' => 'INV-WH-DEST-001',
            'name' => 'Destination Warehouse',
            'status' => 'active',
        ]);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $this->testWarehouse->id,
            'to_warehouse_id' => $destWarehouse->id,
            'transfer_date' => now(),
            'status' => 'draft',
            'total_transfer_value' => 2000.00,
            'notes' => 'Integration test transfer',
        ]);

        // Verify creation feed
        $this->assertTrue($transfer->hasFeeds());

        // Test transfer workflow
        $transfer->recordAction('approved');
        $transfer->recordAction('shipped', ['shipping_ref' => 'SHIP-001']);
        $transfer->recordAction('received', ['received_by' => 'Warehouse Staff']);

        // Verify shipped feed
        $shippedFeed = $transfer->getFeedsByAction('shipped')->first();
        $this->assertNotNull($shippedFeed);
        $this->assertArrayHasKey('shipping_ref', $shippedFeed->metadata);

        // Cleanup
        $transfer->deleteAllFeeds();
        $transfer->forceDelete();
        $destWarehouse->deleteAllFeeds();
        $destWarehouse->forceDelete();
    }

    /**
     * Test StockAdjustment quantity correction feeds
     */
    public function testStockAdjustmentCorrectionFeeds()
    {
        $adjustment = StockAdjustment::create([
            'warehouse_id' => $this->testWarehouse->id,
            'adjustment_date' => now(),
            'reason_code' => 'damage',
            'status' => 'draft',
            'total_value_impact' => -150.00,
            'notes' => 'Integration test adjustment',
        ]);

        // Verify creation feed
        $this->assertTrue($adjustment->hasFeeds());

        // Test adjustment workflow
        $adjustment->recordAction('submitted', ['submitted_reason' => 'Damaged goods found']);
        $adjustment->recordAction('approved', [
            'approved_by' => 'Inventory Manager',
            'approval_notes' => 'Valid adjustment',
        ]);
        $adjustment->recordAction('completed');

        // Verify workflow
        $timeline = $adjustment->getFeedTimeline();
        $this->assertCount(4, $timeline);

        // Cleanup
        $adjustment->deleteAllFeeds();
        $adjustment->forceDelete();
    }

    /**
     * Test PhysicalCount cycle counting feeds
     */
    public function testPhysicalCountCycleCountFeeds()
    {
        $count = PhysicalCount::create([
            'warehouse_id' => $this->testWarehouse->id,
            'count_date' => now(),
            'status' => 'draft',
            'total_items_counted' => 0,
            'variance_count' => 0,
            'notes' => 'Integration test count',
        ]);

        // Verify creation feed
        $this->assertTrue($count->hasFeeds());

        // Test counting workflow
        $count->recordAction('initiated', ['started_by' => 'Counter Team']);
        
        $count->total_items_counted = 150;
        $count->variance_count = 5;
        $count->save();
        
        $count->recordAction('variance_review', [
            'variances_found' => 5,
            'review_status' => 'investigating',
        ]);
        
        $count->recordAction('completed', ['completed_by' => 'Inventory Controller']);

        // Verify variance review feed (domain-specific action)
        $varianceFeed = $count->getFeedsByAction('variance_review')->first();
        $this->assertNotNull($varianceFeed);
        $this->assertArrayHasKey('variances_found', $varianceFeed->metadata);

        // Cleanup
        $count->deleteAllFeeds();
        $count->forceDelete();
    }

    /**
     * Test InventoryValuation report generation feeds
     */
    public function testInventoryValuationReportFeeds()
    {
        $valuation = InventoryValuation::create([
            'warehouse_id' => $this->testWarehouse->id,
            'valuation_date' => now(),
            'valuation_method' => 'FIFO',
            'status' => 'draft',
            'total_valuation_amount' => 0,
        ]);

        // Verify creation feed
        $this->assertTrue($valuation->hasFeeds());

        // Test valuation workflow
        $valuation->recordAction('initiated', ['initiated_by' => 'Finance Team']);
        
        $valuation->total_valuation_amount = 50000.00;
        $valuation->status = 'completed';
        $valuation->save();
        
        $valuation->recordAction('completed', [
            'total_value' => 50000.00,
            'items_valued' => 200,
        ]);

        // Verify feeds
        $timeline = $valuation->getFeedTimeline();
        $this->assertGreaterThanOrEqual(3, count($timeline));

        // Cleanup
        $valuation->deleteAllFeeds();
        $valuation->forceDelete();
    }

    /**
     * Test significant field tracking for inventory quantities
     */
    public function testInventoryQuantityChangeTracking()
    {
        $warehouseItem = WarehouseItem::create([
            'warehouse_id' => $this->testWarehouse->id,
            'purchaseable_item_id' => $this->testItem->id,
            'quantity_on_hand' => 100,
            'minimum_stock_level' => 20,
            'is_active' => true,
        ]);

        $initialCount = $warehouseItem->getFeedCount();

        // Update significant field (quantity_on_hand)
        $warehouseItem->quantity_on_hand = 50;
        $warehouseItem->save();

        // Should create update feed
        $this->assertGreaterThan($initialCount, $warehouseItem->getFeedCount());

        // Cleanup
        $warehouseItem->deleteAllFeeds();
        $warehouseItem->forceDelete();
    }

    /**
     * Test feed persistence across document lifecycle
     */
    public function testFeedPersistenceAcrossLifecycle()
    {
        $mrn = Mrn::create([
            'warehouse_id' => $this->testWarehouse->id,
            'received_date' => now(),
            'status' => 'draft',
            'total_received_value' => 3000.00,
            'notes' => 'Integration test persistence',
        ]);

        // Generate feeds through full lifecycle
        $mrn->recordAction('submitted');
        $mrn->recordAction('approved');
        $mrn->status = 'completed';
        $mrn->save();
        $mrn->recordAction('completed');

        $mrnId = $mrn->id;
        $expectedFeedCount = $mrn->getFeedCount();

        // Soft delete
        $mrn->delete();

        // Feeds should persist
        $feeds = Feed::where('feedable_type', Mrn::class)
            ->where('feedable_id', $mrnId)
            ->get();

        $this->assertEquals($expectedFeedCount, $feeds->count(), 'All feeds should persist after soft delete');

        // Cleanup
        Feed::where('feedable_type', Mrn::class)
            ->where('feedable_id', $mrnId)
            ->delete();
        
        $mrn->forceDelete();
    }
}
