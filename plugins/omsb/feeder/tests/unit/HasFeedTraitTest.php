<?php namespace Omsb\Feeder\Tests\Unit;

use Omsb\Feeder\Models\Feed;
use Omsb\Feeder\Tests\Models\TestModel;
use PluginTestCase;
use Backend\Models\User as BackendUser;
use Illuminate\Support\Facades\Event;

/**
 * HasFeed Trait Unit Tests
 * 
 * Tests the core functionality of the HasFeed trait including:
 * - Automatic feed creation on model events
 * - Message template parsing with placeholders
 * - Action filtering via feedableActions
 * - Significant field change detection
 * - Relationship methods
 * - Custom placeholder injection
 */
class HasFeedTraitTest extends PluginTestCase
{
    protected $testModel;
    protected $testUser;

    public function setUp(): void
    {
        parent::setUp();

        // Create test backend user
        $this->testUser = BackendUser::create([
            'login' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_superuser' => false,
        ]);

        // Authenticate test user
        $this->actingAs($this->testUser);
    }

    public function tearDown(): void
    {
        // Clean up
        if ($this->testModel && $this->testModel->exists) {
            $this->testModel->deleteAllFeeds();
            $this->testModel->delete();
        }

        if ($this->testUser && $this->testUser->exists) {
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    /**
     * Test that feeds relationship is properly initialized
     */
    public function testFeedsRelationshipExists()
    {
        $this->testModel = new TestModel();
        
        $this->assertTrue(
            method_exists($this->testModel, 'feeds'),
            'HasFeed trait should define feeds() relationship method'
        );

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphMany::class,
            $this->testModel->feeds(),
            'feeds() should return MorphMany relationship'
        );
    }

    /**
     * Test automatic feed creation on model creation
     */
    public function testFeedCreatedOnModelCreate()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $feeds = $this->testModel->feeds()->get();

        $this->assertCount(1, $feeds, 'One feed should be created on model creation');
        $this->assertEquals('created', $feeds->first()->action_type);
        $this->assertEquals($this->testUser->id, $feeds->first()->user_id);
    }

    /**
     * Test feed creation on model update with significant field changes
     */
    public function testFeedCreatedOnSignificantUpdate()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        // Clear creation feed count
        $initialCount = $this->testModel->feeds()->count();

        // Update significant field
        $this->testModel->status = 'inactive';
        $this->testModel->save();

        $feedCount = $this->testModel->feeds()->count();

        $this->assertEquals(
            $initialCount + 1,
            $feedCount,
            'Feed should be created when significant field changes'
        );

        $latestFeed = $this->testModel->feeds()->latest()->first();
        $this->assertEquals('updated', $latestFeed->action_type);
    }

    /**
     * Test no feed creation on insignificant field update
     */
    public function testNoFeedCreatedOnInsignificantUpdate()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $initialCount = $this->testModel->feeds()->count();

        // Update non-significant field (assuming description is not in feedSignificantFields)
        $this->testModel->description = 'Updated description';
        $this->testModel->save();

        $feedCount = $this->testModel->feeds()->count();

        $this->assertEquals(
            $initialCount,
            $feedCount,
            'No feed should be created when only insignificant fields change'
        );
    }

    /**
     * Test feed creation on model deletion
     */
    public function testFeedCreatedOnModelDelete()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $modelId = $this->testModel->id;
        $this->testModel->delete();

        // Query feeds directly since model is deleted
        $feeds = Feed::where('feedable_type', TestModel::class)
            ->where('feedable_id', $modelId)
            ->get();

        $deleteFeed = $feeds->firstWhere('action_type', 'deleted');

        $this->assertNotNull($deleteFeed, 'Feed should be created on model deletion');
        $this->assertEquals('deleted', $deleteFeed->action_type);
    }

    /**
     * Test message template parsing with standard placeholders
     */
    public function testMessageTemplateParsingWithStandardPlaceholders()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $feed = $this->testModel->feeds()->first();

        $this->assertStringContainsString(
            'Test User',
            $feed->message,
            'Message should contain actor name'
        );

        $this->assertStringContainsString(
            'created',
            $feed->message,
            'Message should contain action type'
        );

        $this->assertStringContainsString(
            'Test Model',
            $feed->message,
            'Message should contain model name'
        );
    }

    /**
     * Test custom action recording via recordAction()
     */
    public function testCustomActionRecording()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $this->testModel->recordAction('approved', ['approver_notes' => 'Looks good']);

        $approvalFeed = $this->testModel->feeds()
            ->where('action_type', 'approved')
            ->first();

        $this->assertNotNull($approvalFeed, 'Custom action feed should be created');
        $this->assertEquals('approved', $approvalFeed->action_type);
        $this->assertArrayHasKey('approver_notes', $approvalFeed->metadata);
    }

    /**
     * Test action filtering via feedableActions
     */
    public function testActionFiltering()
    {
        // Create model with limited feedableActions
        $this->testModel = new TestModel();
        $this->testModel->feedableActions = ['created', 'deleted']; // Exclude 'updated'
        $this->testModel->name = 'Test Item';
        $this->testModel->code = 'TEST001';
        $this->testModel->status = 'active';
        $this->testModel->save();

        $initialCount = $this->testModel->feeds()->count();

        // Update should not create feed (not in feedableActions)
        $this->testModel->status = 'inactive';
        $this->testModel->save();

        $feedCount = $this->testModel->feeds()->count();

        $this->assertEquals(
            $initialCount,
            $feedCount,
            'Feed should not be created for filtered action types'
        );
    }

    /**
     * Test significant fields detection
     */
    public function testSignificantFieldsDetection()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        // Method should exist
        $this->assertTrue(
            method_exists($this->testModel, 'shouldCreateUpdateFeed'),
            'HasFeed trait should implement shouldCreateUpdateFeed()'
        );

        // Test with significant field change
        $this->testModel->status = 'inactive';
        $this->testModel->syncOriginal(); // Update original attributes

        $oldAttributes = ['status' => 'active', 'name' => 'Test Item'];
        $this->testModel->setRawAttributes(array_merge($this->testModel->getAttributes(), $oldAttributes), true);
        $this->testModel->status = 'inactive';

        // Reflection to test protected method
        $reflection = new \ReflectionClass($this->testModel);
        $method = $reflection->getMethod('shouldCreateUpdateFeed');
        $method->setAccessible(true);

        $result = $method->invoke($this->testModel);

        $this->assertTrue(
            $result,
            'shouldCreateUpdateFeed should return true when significant field changes'
        );
    }

    /**
     * Test getRecentFeeds() helper method
     */
    public function testGetRecentFeedsHelper()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        // Create multiple actions
        $this->testModel->recordAction('approved');
        $this->testModel->recordAction('completed');

        $recentFeeds = $this->testModel->getRecentFeeds(2);

        $this->assertCount(2, $recentFeeds, 'Should return requested number of recent feeds');
        $this->assertEquals('completed', $recentFeeds->first()->action_type, 'Should order by most recent');
    }

    /**
     * Test getFeedsByAction() filtering
     */
    public function testGetFeedsByActionFiltering()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $this->testModel->recordAction('approved');
        $this->testModel->recordAction('rejected');
        $this->testModel->recordAction('approved'); // Second approval

        $approvedFeeds = $this->testModel->getFeedsByAction('approved');

        $this->assertCount(2, $approvedFeeds, 'Should filter feeds by specific action type');
        $this->assertTrue(
            $approvedFeeds->every(fn($feed) => $feed->action_type === 'approved'),
            'All returned feeds should have the requested action type'
        );
    }

    /**
     * Test getFeedTimeline() formatting
     */
    public function testGetFeedTimelineFormatting()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $this->testModel->recordAction('approved');

        $timeline = $this->testModel->getFeedTimeline();

        $this->assertIsArray($timeline, 'Timeline should be an array');
        $this->assertNotEmpty($timeline, 'Timeline should not be empty');
        $this->assertArrayHasKey('action', $timeline[0], 'Timeline items should have action key');
        $this->assertArrayHasKey('message', $timeline[0], 'Timeline items should have message key');
        $this->assertArrayHasKey('timestamp', $timeline[0], 'Timeline items should have timestamp key');
        $this->assertArrayHasKey('user', $timeline[0], 'Timeline items should have user key');
    }

    /**
     * Test hasFeeds() and getFeedCount() utility methods
     */
    public function testFeedUtilityMethods()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $this->assertTrue($this->testModel->hasFeeds(), 'hasFeeds() should return true when feeds exist');
        $this->assertGreaterThan(0, $this->testModel->getFeedCount(), 'getFeedCount() should return feed count');

        // Test empty state
        $emptyModel = new TestModel();
        $this->assertFalse($emptyModel->hasFeeds(), 'hasFeeds() should return false for new model');
        $this->assertEquals(0, $emptyModel->getFeedCount(), 'getFeedCount() should return 0 for new model');
    }

    /**
     * Test custom placeholder injection via getFeedTemplatePlaceholders()
     */
    public function testCustomPlaceholderInjection()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        // Override template with custom placeholder
        $originalTemplate = $this->testModel->feedMessageTemplate;
        $this->testModel->feedMessageTemplate = '{actor} {action} {model} "{custom_code}"';

        $this->testModel->recordAction('custom_test');

        $feed = $this->testModel->feeds()->where('action_type', 'custom_test')->first();

        $this->assertStringContainsString(
            $this->testModel->code,
            $feed->message,
            'Message should contain custom placeholder value'
        );

        // Restore original template
        $this->testModel->feedMessageTemplate = $originalTemplate;
    }

    /**
     * Test feed metadata capture
     */
    public function testFeedMetadataCapture()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $customMetadata = [
            'reason' => 'Testing metadata',
            'priority' => 'high',
        ];

        $this->testModel->recordAction('approved', $customMetadata);

        $feed = $this->testModel->feeds()->where('action_type', 'approved')->first();

        $this->assertIsArray($feed->metadata, 'Metadata should be an array');
        $this->assertArrayHasKey('reason', $feed->metadata, 'Custom metadata should be stored');
        $this->assertEquals('Testing metadata', $feed->metadata['reason']);
    }

    /**
     * Test auto feed disabled functionality
     */
    public function testAutoFeedDisabled()
    {
        $this->testModel = new TestModel();
        $this->testModel->autoFeedEnabled = false;
        $this->testModel->name = 'Test Item';
        $this->testModel->code = 'TEST001';
        $this->testModel->status = 'active';
        $this->testModel->save();

        $feedCount = $this->testModel->feeds()->count();

        $this->assertEquals(0, $feedCount, 'No feeds should be created when autoFeedEnabled is false');
    }

    /**
     * Test feed message customization via getFeedMessageTemplate()
     */
    public function testFeedMessageTemplateCustomization()
    {
        $this->testModel = new TestModel();
        
        // Test that customization hook exists
        $this->assertTrue(
            method_exists($this->testModel, 'getFeedMessageTemplate'),
            'HasFeed trait should provide getFeedMessageTemplate() customization hook'
        );

        $template = $this->testModel->feedMessageTemplate;
        
        $this->assertIsString($template, 'Template should be a string');
        $this->assertNotEmpty($template, 'Template should not be empty');
    }

    /**
     * Test model name customization via getFeedModelName()
     */
    public function testFeedModelNameCustomization()
    {
        $this->testModel = new TestModel();

        $this->assertTrue(
            method_exists($this->testModel, 'getFeedModelName'),
            'HasFeed trait should provide getFeedModelName() customization hook'
        );

        $modelName = $this->testModel->getFeedModelName();

        $this->assertIsString($modelName, 'Model name should be a string');
        $this->assertEquals('Test Model', $modelName);
    }

    /**
     * Test model identifier customization via getFeedModelIdentifier()
     */
    public function testFeedModelIdentifierCustomization()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $this->assertTrue(
            method_exists($this->testModel, 'getFeedModelIdentifier'),
            'HasFeed trait should provide getFeedModelIdentifier() customization hook'
        );

        $identifier = $this->testModel->getFeedModelIdentifier();

        $this->assertIsString($identifier, 'Identifier should be a string');
    }

    /**
     * Test deleteAllFeeds() cleanup method
     */
    public function testDeleteAllFeeds()
    {
        $this->testModel = TestModel::create([
            'name' => 'Test Item',
            'code' => 'TEST001',
            'status' => 'active',
        ]);

        $this->testModel->recordAction('approved');
        $this->testModel->recordAction('completed');

        $this->assertGreaterThan(0, $this->testModel->getFeedCount());

        $this->testModel->deleteAllFeeds();

        $this->assertEquals(0, $this->testModel->getFeedCount(), 'All feeds should be deleted');
    }
}
