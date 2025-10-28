<?php namespace Omsb\Procurement\Tests\Integration;

use Omsb\Procurement\Models\PurchaseableItem;
use Omsb\Procurement\Models\PurchaseRequest;
use Omsb\Procurement\Models\Vendor;
use Omsb\Feeder\Models\Feed;
use Omsb\Organization\Models\Site;
use Omsb\Organization\Models\Staff;
use PluginTestCase;
use Backend\Models\User as BackendUser;

/**
 * HasFeed Integration Tests for Procurement Plugin
 * 
 * Tests real-world usage of HasFeed trait with Procurement models:
 * - PurchaseableItem
 * - PurchaseRequest
 * - Vendor
 */
class HasFeedIntegrationTest extends PluginTestCase
{
    protected $testUser;
    protected $testSite;
    protected $testStaff;

    public function setUp(): void
    {
        parent::setUp();

        // Create test backend user
        $this->testUser = BackendUser::create([
            'login' => 'integrationtest',
            'email' => 'integration@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'first_name' => 'Integration',
            'last_name' => 'Tester',
            'is_superuser' => false,
        ]);

        // Create test site
        $this->testSite = Site::create([
            'site_code' => 'INT-TEST',
            'name' => 'Integration Test Site',
            'is_active' => true,
        ]);

        // Authenticate
        $this->actingAs($this->testUser);
    }

    public function tearDown(): void
    {
        // Clean up test data
        PurchaseableItem::where('code', 'LIKE', 'INT-TEST-%')->each(function ($item) {
            $item->deleteAllFeeds();
            $item->forceDelete();
        });

        PurchaseRequest::where('notes', 'LIKE', 'Integration test%')->each(function ($pr) {
            $pr->deleteAllFeeds();
            $pr->forceDelete();
        });

        Vendor::where('code', 'LIKE', 'INT-VENDOR-%')->each(function ($vendor) {
            $vendor->deleteAllFeeds();
            $vendor->forceDelete();
        });

        if ($this->testSite) {
            $this->testSite->forceDelete();
        }

        if ($this->testUser) {
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    /**
     * Test PurchaseableItem feed creation on catalog operations
     */
    public function testPurchaseableItemFeedCreation()
    {
        $item = PurchaseableItem::create([
            'code' => 'INT-TEST-ITEM-001',
            'name' => 'Integration Test Item',
            'item_type' => 'consumable',
            'is_inventory_item' => true,
            'is_active' => true,
        ]);

        // Verify creation feed
        $this->assertTrue($item->hasFeeds(), 'PurchaseableItem should have feeds after creation');
        
        $creationFeed = $item->getFeedsByAction('created')->first();
        $this->assertNotNull($creationFeed);
        $this->assertStringContainsString('Integration Test Item', $creationFeed->message);
        $this->assertStringContainsString('INT-TEST-ITEM-001', $creationFeed->message);

        // Test status change (significant field)
        $initialCount = $item->getFeedCount();
        $item->is_active = false;
        $item->save();

        $this->assertGreaterThan($initialCount, $item->getFeedCount(), 'Feed should be created on status change');

        // Test custom action (discontinue)
        $item->recordAction('discontinued', ['reason' => 'End of life']);
        
        $discontinueFeed = $item->getFeedsByAction('discontinued')->first();
        $this->assertNotNull($discontinueFeed);
        $this->assertArrayHasKey('reason', $discontinueFeed->metadata);

        // Cleanup
        $item->deleteAllFeeds();
        $item->forceDelete();
    }

    /**
     * Test PurchaseRequest feed creation through workflow
     */
    public function testPurchaseRequestWorkflowFeeds()
    {
        $pr = PurchaseRequest::create([
            'site_id' => $this->testSite->id,
            'request_date' => now(),
            'required_date' => now()->addDays(7),
            'priority' => 'normal',
            'status' => 'draft',
            'total_amount' => 1000.00,
            'notes' => 'Integration test PR',
        ]);

        // Verify creation feed
        $this->assertTrue($pr->hasFeeds());
        
        $creationFeed = $pr->feeds()->where('action_type', 'created')->first();
        $this->assertNotNull($creationFeed);
        $this->assertStringContainsString('Purchase Request', $creationFeed->message);

        // Test workflow progression
        $pr->recordAction('submitted', ['submitted_to' => 'Manager']);
        $submitFeed = $pr->getFeedsByAction('submitted')->first();
        $this->assertNotNull($submitFeed);
        $this->assertArrayHasKey('submitted_to', $submitFeed->metadata);

        $pr->recordAction('approved', [
            'approver' => 'Manager',
            'approval_date' => now()->format('Y-m-d H:i:s'),
        ]);
        $approveFeed = $pr->getFeedsByAction('approved')->first();
        $this->assertNotNull($approveFeed);

        // Test feed timeline
        $timeline = $pr->getFeedTimeline();
        $this->assertCount(3, $timeline, 'Timeline should have 3 entries: created, submitted, approved');
        $this->assertEquals('approved', $timeline[0]['action'], 'Most recent should be approval');

        // Cleanup
        $pr->deleteAllFeeds();
        $pr->forceDelete();
    }

    /**
     * Test Vendor feed creation and lifecycle tracking
     */
    public function testVendorLifecycleFeeds()
    {
        $vendor = Vendor::create([
            'code' => 'INT-VENDOR-001',
            'name' => 'Integration Test Vendor',
            'status' => 'active',
            'is_approved' => false,
            'contact_email' => 'vendor@test.com',
        ]);

        // Verify creation feed
        $this->assertTrue($vendor->hasFeeds());
        
        $creationFeed = $vendor->feeds()->first();
        $this->assertStringContainsString('Integration Test Vendor', $creationFeed->message);
        $this->assertStringContainsString('INT-VENDOR-001', $creationFeed->message);

        // Test approval process
        $vendor->recordAction('approved', [
            'approved_by' => 'Procurement Manager',
            'approval_notes' => 'Verified credentials',
        ]);
        
        $approvalFeed = $vendor->getFeedsByAction('approved')->first();
        $this->assertNotNull($approvalFeed);
        $this->assertEquals('approved', $approvalFeed->action_type);

        // Test suspension
        $vendor->status = 'suspended';
        $vendor->save();
        $vendor->recordAction('suspended', ['reason' => 'Quality issues']);

        $suspensionFeed = $vendor->getFeedsByAction('suspended')->first();
        $this->assertNotNull($suspensionFeed);
        $this->assertArrayHasKey('reason', $suspensionFeed->metadata);

        // Verify feed count
        $this->assertGreaterThanOrEqual(3, $vendor->getFeedCount());

        // Test recent feeds limit
        $recentFeeds = $vendor->getRecentFeeds(2);
        $this->assertCount(2, $recentFeeds);

        // Cleanup
        $vendor->deleteAllFeeds();
        $vendor->forceDelete();
    }

    /**
     * Test feed message templates with model-specific placeholders
     */
    public function testModelSpecificMessageTemplates()
    {
        $item = PurchaseableItem::create([
            'code' => 'INT-TEST-TPL-001',
            'name' => 'Template Test Item',
            'item_type' => 'equipment',
            'is_inventory_item' => false,
        ]);

        $feed = $item->feeds()->first();
        
        // Verify placeholders resolved correctly
        $this->assertStringContainsString('Integration Tester', $feed->message, 'Actor placeholder should resolve');
        $this->assertStringContainsString('created', $feed->message, 'Action placeholder should resolve');
        $this->assertStringContainsString('Template Test Item', $feed->message, 'Name placeholder should resolve');
        $this->assertStringContainsString('INT-TEST-TPL-001', $feed->message, 'Code placeholder should resolve');

        // Cleanup
        $item->deleteAllFeeds();
        $item->forceDelete();
    }

    /**
     * Test feed metadata includes model context
     */
    public function testFeedMetadataContext()
    {
        $pr = PurchaseRequest::create([
            'site_id' => $this->testSite->id,
            'request_date' => now(),
            'required_date' => now()->addDays(14),
            'priority' => 'urgent',
            'status' => 'draft',
            'total_amount' => 5000.00,
            'notes' => 'Integration test metadata check',
        ]);

        $feed = $pr->feeds()->first();

        // Metadata should be an array
        $this->assertIsArray($feed->metadata);
        
        // Should contain model class info
        $this->assertArrayHasKey('model_class', $feed->metadata);
        $this->assertEquals(PurchaseRequest::class, $feed->metadata['model_class']);

        // Cleanup
        $pr->deleteAllFeeds();
        $pr->forceDelete();
    }

    /**
     * Test feeds persist after model soft delete
     */
    public function testFeedsPersistAfterSoftDelete()
    {
        $vendor = Vendor::create([
            'code' => 'INT-VENDOR-DEL-001',
            'name' => 'Soft Delete Test Vendor',
            'status' => 'active',
            'contact_email' => 'delete@test.com',
        ]);

        $vendorId = $vendor->id;
        $feedCount = $vendor->getFeedCount();

        // Soft delete
        $vendor->delete();

        // Feeds should still exist
        $feeds = Feed::where('feedable_type', Vendor::class)
            ->where('feedable_id', $vendorId)
            ->get();

        $this->assertGreaterThanOrEqual($feedCount, $feeds->count(), 'Feeds should persist after soft delete');

        // Cleanup
        Feed::where('feedable_type', Vendor::class)
            ->where('feedable_id', $vendorId)
            ->delete();
        
        $vendor->forceDelete();
    }

    /**
     * Test concurrent feeds from multiple users
     */
    public function testMultiUserFeedCreation()
    {
        // Create second user
        $user2 = BackendUser::create([
            'login' => 'integrationtest2',
            'email' => 'integration2@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'first_name' => 'Second',
            'last_name' => 'Tester',
            'is_superuser' => false,
        ]);

        $item = PurchaseableItem::create([
            'code' => 'INT-TEST-MULTI-001',
            'name' => 'Multi User Test',
            'item_type' => 'spare_part',
            'is_inventory_item' => true,
        ]);

        // First user creates
        $creationFeed = $item->feeds()->first();
        $this->assertEquals($this->testUser->id, $creationFeed->user_id);

        // Switch to second user
        $this->actingAs($user2);
        
        $item->recordAction('reactivated', ['reactivated_by' => 'Second Tester']);
        
        $reactivateFeed = $item->getFeedsByAction('reactivated')->first();
        $this->assertEquals($user2->id, $reactivateFeed->user_id);

        // Cleanup
        $item->deleteAllFeeds();
        $item->forceDelete();
        $user2->delete();
    }

    /**
     * Test feed action filtering
     */
    public function testFeedActionFiltering()
    {
        $vendor = Vendor::create([
            'code' => 'INT-VENDOR-FILTER-001',
            'name' => 'Filter Test Vendor',
            'status' => 'active',
            'contact_email' => 'filter@test.com',
        ]);

        // Generate multiple action types
        $vendor->recordAction('approved');
        $vendor->recordAction('suspended');
        $vendor->recordAction('reactivated');

        // Filter by specific action
        $approvedFeeds = $vendor->getFeedsByAction('approved');
        $this->assertCount(1, $approvedFeeds);
        $this->assertEquals('approved', $approvedFeeds->first()->action_type);

        // Verify other actions exist
        $this->assertGreaterThan(1, $vendor->getFeedCount());

        // Cleanup
        $vendor->deleteAllFeeds();
        $vendor->forceDelete();
    }
}
