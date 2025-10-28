# Test Coverage & Test Suggestions - Feeder Plugin

## Overview

This document provides a comprehensive test plan for the Feeder plugin, including test coverage analysis, proposed test cases, and testing infrastructure setup.

## Current Test Coverage

**Status:** ❌ **ZERO TEST COVERAGE**

**Evidence:**
- No `tests/` directory exists
- No `phpunit.xml` configuration
- No test cases implemented
- All testing is manual

**Impact:**
- High risk of regressions
- Difficult to refactor with confidence
- Onboarding new developers is harder
- No CI/CD pipeline possible

## Proposed Test Strategy

### Test Types

1. **Unit Tests** - Test individual methods and classes in isolation
2. **Integration Tests** - Test interactions between components
3. **Feature Tests** - Test complete user workflows
4. **Database Tests** - Test model relationships and queries

### Testing Framework

**Recommendation:** PHPUnit 9.x with OctoberCMS `PluginTestCase`

**Rationale:**
- Standard for OctoberCMS plugins
- Well-documented
- Supports database testing
- Easy integration with CI/CD

## Test Infrastructure Setup

### 1. Directory Structure

```
plugins/omsb/feeder/
├── tests/
│   ├── bootstrap.php
│   ├── fixtures/
│   │   ├── FeedFixture.php
│   │   └── UserFixture.php
│   ├── models/
│   │   └── FeedTest.php
│   ├── partials/
│   │   └── FeedSidebarTest.php
│   ├── integration/
│   │   └── FeedCreationTest.php
│   └── feature/
│       └── FeedImmutabilityTest.php
├── phpunit.xml
└── composer.json (updated with dev dependencies)
```

### 2. PHPUnit Configuration

**phpunit.xml:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         verbose="true"
         failOnWarning="true"
         failOnRisky="true"
         beStrictAboutOutputDuringTests="true">
    <testsuites>
        <testsuite name="Feeder Plugin Tests">
            <directory>./tests</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./models</directory>
            <directory suffix=".php">./classes</directory>
        </include>
        <exclude>
            <directory>./vendor</directory>
            <directory>./tests</directory>
        </exclude>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

### 3. Bootstrap File

**tests/bootstrap.php:**

```php
<?php

require __DIR__ . '/../../../tests/bootstrap.php';

// Load plugin-specific test utilities
require __DIR__ . '/fixtures/FeedFixture.php';
```

### 4. Composer Dev Dependencies

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "mockery/mockery": "^1.4",
        "fakerphp/faker": "^1.20"
    }
}
```

## Test Cases

### Unit Tests: Feed Model

**File:** `tests/models/FeedTest.php`

```php
<?php namespace Omsb\Feeder\Tests\Models;

use Omsb\Feeder\Models\Feed;
use Omsb\Procurement\Models\PurchaseRequest;
use Backend\Models\User;
use PluginTestCase;

class FeedTest extends PluginTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runPluginRefreshCommand('Omsb.Feeder');
        $this->runPluginRefreshCommand('Omsb.Procurement');
        $this->runPluginRefreshCommand('Backend.User');
    }
    
    /** @test */
    public function it_can_create_a_feed()
    {
        $pr = PurchaseRequest::create(['document_number' => 'PR001']);
        
        $feed = Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
        ]);
        
        $this->assertInstanceOf(Feed::class, $feed);
        $this->assertTrue($feed->exists);
        $this->assertDatabaseHas('omsb_feeder_feeds', [
            'action_type' => 'create',
            'feedable_id' => $pr->id,
        ]);
    }
    
    /** @test */
    public function it_requires_action_type()
    {
        $this->expectException(\ValidationException::class);
        
        Feed::create([
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
    }
    
    /** @test */
    public function it_requires_feedable_type()
    {
        $this->expectException(\ValidationException::class);
        
        Feed::create([
            'action_type' => 'create',
            'feedable_id' => 1,
        ]);
    }
    
    /** @test */
    public function it_requires_feedable_id()
    {
        $this->expectException(\ValidationException::class);
        
        Feed::create([
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
        ]);
    }
    
    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::first();
        
        $feed = Feed::create([
            'user_id' => $user->id,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        $this->assertInstanceOf(User::class, $feed->user);
        $this->assertEquals($user->id, $feed->user->id);
    }
    
    /** @test */
    public function it_can_have_null_user_for_system_actions()
    {
        $feed = Feed::create([
            'user_id' => null,
            'action_type' => 'system_sync',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        $this->assertNull($feed->user_id);
        $this->assertNull($feed->user);
    }
    
    /** @test */
    public function it_morphs_to_feedable_model()
    {
        $pr = PurchaseRequest::create(['document_number' => 'PR001']);
        
        $feed = Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
        ]);
        
        $this->assertInstanceOf(PurchaseRequest::class, $feed->feedable);
        $this->assertEquals($pr->id, $feed->feedable->id);
    }
    
    /** @test */
    public function it_stores_additional_data_as_json()
    {
        $additionalData = [
            'status_from' => 'draft',
            'status_to' => 'approved',
            'amount' => 1000.50,
        ];
        
        $feed = Feed::create([
            'user_id' => 1,
            'action_type' => 'approve',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
            'additional_data' => $additionalData,
        ]);
        
        $this->assertEquals($additionalData, $feed->additional_data);
        $this->assertIsArray($feed->additional_data);
    }
    
    /** @test */
    public function it_generates_description_attribute()
    {
        $user = User::first();
        $user->first_name = 'John';
        $user->last_name = 'Doe';
        $user->save();
        
        $feed = Feed::create([
            'user_id' => $user->id,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        $this->assertEquals('John Doe create PurchaseRequest', $feed->description);
    }
    
    /** @test */
    public function it_scopes_by_action_type()
    {
        Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        Feed::create([
            'user_id' => 1,
            'action_type' => 'approve',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        $approvals = Feed::actionType('approve')->get();
        
        $this->assertEquals(1, $approvals->count());
        $this->assertEquals('approve', $approvals->first()->action_type);
    }
    
    /** @test */
    public function it_scopes_by_feedable_type()
    {
        Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => 'Omsb\\Budget\\Models\\Budget',
            'feedable_id' => 1,
        ]);
        
        $prFeeds = Feed::feedableType(PurchaseRequest::class)->get();
        
        $this->assertEquals(1, $prFeeds->count());
    }
    
    /** @test */
    public function it_scopes_by_user()
    {
        Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        Feed::create([
            'user_id' => 2,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 2,
        ]);
        
        $user1Feeds = Feed::byUser(1)->get();
        
        $this->assertEquals(1, $user1Feeds->count());
        $this->assertEquals(1, $user1Feeds->first()->user_id);
    }
    
    /** @test */
    public function it_retrieves_feeds_for_document()
    {
        $pr = PurchaseRequest::create(['document_number' => 'PR001']);
        
        Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
        ]);
        
        Feed::create([
            'user_id' => 1,
            'action_type' => 'update',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
        ]);
        
        $feeds = Feed::getForDocument(PurchaseRequest::class, $pr->id);
        
        $this->assertEquals(2, $feeds->count());
        $this->assertEquals('update', $feeds->first()->action_type); // Newest first
    }
    
    /** @test */
    public function it_limits_feeds_for_document()
    {
        $pr = PurchaseRequest::create(['document_number' => 'PR001']);
        
        for ($i = 1; $i <= 100; $i++) {
            Feed::create([
                'user_id' => 1,
                'action_type' => 'update',
                'feedable_type' => PurchaseRequest::class,
                'feedable_id' => $pr->id,
            ]);
        }
        
        $feeds = Feed::getForDocument(PurchaseRequest::class, $pr->id, 10);
        
        $this->assertEquals(10, $feeds->count());
    }
}
```

### Immutability Tests

**File:** `tests/feature/FeedImmutabilityTest.php`

```php
<?php namespace Omsb\Feeder\Tests\Feature;

use Omsb\Feeder\Models\Feed;
use Omsb\Procurement\Models\PurchaseRequest;
use PluginTestCase;

class FeedImmutabilityTest extends PluginTestCase
{
    /** @test */
    public function it_prevents_updating_existing_feed()
    {
        $feed = Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Feed records cannot be modified once created.');
        
        $feed->action_type = 'update';
        $feed->save();
    }
    
    /** @test */
    public function it_prevents_deleting_feed()
    {
        $feed = Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        $result = $feed->delete();
        
        $this->assertFalse($result);
        $this->assertDatabaseHas('omsb_feeder_feeds', ['id' => $feed->id]);
    }
    
    /** @test */
    public function it_allows_creating_new_feed()
    {
        $feed = Feed::create([
            'user_id' => 1,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => 1,
        ]);
        
        $this->assertTrue($feed->exists);
        $this->assertDatabaseHas('omsb_feeder_feeds', ['id' => $feed->id]);
    }
}
```

### Integration Tests

**File:** `tests/integration/FeedCreationTest.php`

```php
<?php namespace Omsb\Feeder\Tests\Integration;

use Omsb\Feeder\Models\Feed;
use Omsb\Procurement\Models\PurchaseRequest;
use Backend\Models\User;
use PluginTestCase;
use BackendAuth;

class FeedCreationTest extends PluginTestCase
{
    /** @test */
    public function it_creates_feed_when_document_is_created()
    {
        $user = User::first();
        BackendAuth::login($user);
        
        $pr = PurchaseRequest::create(['document_number' => 'PR001']);
        
        Feed::create([
            'user_id' => BackendAuth::getUser()->id,
            'action_type' => 'create',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
        ]);
        
        $this->assertDatabaseHas('omsb_feeder_feeds', [
            'action_type' => 'create',
            'feedable_id' => $pr->id,
            'user_id' => $user->id,
        ]);
    }
    
    /** @test */
    public function it_creates_feed_with_status_transition()
    {
        $pr = PurchaseRequest::create(['document_number' => 'PR001', 'status' => 'draft']);
        
        $pr->status = 'approved';
        $pr->save();
        
        $feed = Feed::create([
            'user_id' => 1,
            'action_type' => 'approve',
            'feedable_type' => PurchaseRequest::class,
            'feedable_id' => $pr->id,
            'additional_data' => [
                'status_from' => 'draft',
                'status_to' => 'approved',
            ],
        ]);
        
        $this->assertEquals('draft', $feed->additional_data['status_from']);
        $this->assertEquals('approved', $feed->additional_data['status_to']);
    }
}
```

## Test Coverage Goals

### Minimum Acceptable Coverage (Phase 1)
- **Line Coverage:** 70%
- **Branch Coverage:** 60%
- **Method Coverage:** 80%

### Target Coverage (Phase 2)
- **Line Coverage:** 85%
- **Branch Coverage:** 75%
- **Method Coverage:** 90%

### Ideal Coverage (Phase 3)
- **Line Coverage:** 95%
- **Branch Coverage:** 90%
- **Method Coverage:** 100%

## Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage report
vendor/bin/phpunit --coverage-html coverage/

# Run specific test file
vendor/bin/phpunit tests/models/FeedTest.php

# Run specific test method
vendor/bin/phpunit --filter testFeedCreation

# Run with verbose output
vendor/bin/phpunit --verbose

# Run with colors
vendor/bin/phpunit --colors=always
```

## CI/CD Integration

### GitHub Actions Workflow

**.github/workflows/tests.yml:**

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: october_test
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mbstring, gd, mysql, curl, json
          coverage: xdebug
      
      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run Tests
        run: vendor/bin/phpunit --coverage-clover coverage.xml
      
      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
```

## Test Effort Estimate

| Test Category | Test Cases | Effort (Hours) |
|---------------|------------|----------------|
| Model Unit Tests | 20 | 8 |
| Immutability Tests | 5 | 2 |
| Relationship Tests | 8 | 3 |
| Query Scope Tests | 6 | 2 |
| Integration Tests | 10 | 5 |
| Partial Tests | 5 | 3 |
| **Total** | **54** | **23** |

## Conclusion

Implementing this test suite will:
- Provide 70%+ code coverage
- Ensure immutability enforcement
- Verify all relationships
- Enable confident refactoring
- Support CI/CD integration

**Recommendation:** Implement in 3 phases over 3 weeks (1 phase per week).

---

**Previous:** [← Code Review](07_code_review.md) | **Next:** [Improvements →](09_improvements.md)
