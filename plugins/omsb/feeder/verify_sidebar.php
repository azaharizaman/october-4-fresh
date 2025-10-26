<?php
/**
 * Feed Sidebar Verification Script
 * 
 * This script demonstrates how the feed sidebar partial renders with sample data.
 * Run this from the OctoberCMS root to verify the partial is working correctly.
 * 
 * Usage:
 * php plugins/omsb/feeder/verify_sidebar.php
 */

// Bootstrap OctoberCMS
require __DIR__ . '/../../../bootstrap/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use Omsb\Feeder\Models\Feed;
use Backend\Models\User;
use Carbon\Carbon;

echo "\n==========================================\n";
echo "Feed Sidebar Partial Verification\n";
echo "==========================================\n\n";

// 1. Check if Feed model exists and is accessible
echo "1. Checking Feed model...\n";
try {
    $feedClass = Feed::class;
    echo "   ✓ Feed model found: $feedClass\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Check if the partial file exists
echo "\n2. Checking partial file...\n";
$partialPath = __DIR__ . '/partials/_feed_sidebar.htm';
if (file_exists($partialPath)) {
    echo "   ✓ Partial file exists at: $partialPath\n";
    $fileSize = filesize($partialPath);
    echo "   ✓ File size: " . number_format($fileSize) . " bytes\n";
} else {
    echo "   ✗ Partial file not found at: $partialPath\n";
    exit(1);
}

// 3. Check if getForDocument method exists
echo "\n3. Checking Feed::getForDocument() method...\n";
if (method_exists(Feed::class, 'getForDocument')) {
    echo "   ✓ getForDocument() method exists\n";
    
    // Test method signature
    try {
        $reflection = new ReflectionMethod(Feed::class, 'getForDocument');
        $params = $reflection->getParameters();
        echo "   ✓ Method parameters:\n";
        foreach ($params as $param) {
            $paramName = $param->getName();
            $paramType = $param->hasType() ? $param->getType() : 'mixed';
            $defaultValue = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : 'required';
            echo "     - \$$paramName ($paramType) = $defaultValue\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error reflecting method: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ getForDocument() method not found\n";
}

// 4. Demonstrate sample data structure
echo "\n4. Sample feed data structure:\n";
$sampleFeeds = [
    [
        'id' => 1,
        'user_id' => 1,
        'action_type' => 'create',
        'feedable_type' => 'Omsb\\Procurement\\Models\\PurchaseRequest',
        'feedable_id' => 190,
        'title' => null,
        'body' => null,
        'additional_data' => [
            'document_number' => 'PR\\SRT\\2025\\00190',
            'total_amount' => 1057.00,
        ],
        'created_at' => Carbon::now()->subMonths(1),
        'user' => [
            'first_name' => 'Siti',
            'last_name' => 'Nurbaya',
        ],
    ],
    [
        'id' => 2,
        'user_id' => 2,
        'action_type' => 'update',
        'feedable_type' => 'Omsb\\Procurement\\Models\\PurchaseRequest',
        'feedable_id' => 190,
        'title' => 'Purchase Request Updated',
        'body' => 'Updated vendor and pricing information',
        'additional_data' => [
            'document_number' => 'PR\\SRT\\2025\\00190',
        ],
        'created_at' => Carbon::now()->subMonths(1)->addDays(5),
        'user' => [
            'first_name' => 'Dayang',
            'last_name' => 'Maznah',
        ],
    ],
    [
        'id' => 3,
        'user_id' => 2,
        'action_type' => 'approve',
        'feedable_type' => 'Omsb\\Procurement\\Models\\PurchaseRequest',
        'feedable_id' => 190,
        'title' => 'Purchase Request Approved',
        'body' => null,
        'additional_data' => [
            'document_number' => 'PR\\SRT\\2025\\00190',
            'total_amount' => 1057.00,
            'currency' => 'MYR',
            'status_from' => 'submitted',
            'status_to' => 'approved',
        ],
        'created_at' => Carbon::now()->subMonths(1)->addDays(7),
        'user' => [
            'first_name' => 'Dayang',
            'last_name' => 'Maznah',
        ],
    ],
];

echo "   Sample feeds structure:\n";
foreach ($sampleFeeds as $feed) {
    echo "   - ID: {$feed['id']}, Action: {$feed['action_type']}, User: {$feed['user']['first_name']} {$feed['user']['last_name']}\n";
    echo "     Created: " . $feed['created_at']->diffForHumans() . "\n";
    if ($feed['additional_data']) {
        echo "     Metadata: " . json_encode($feed['additional_data'], JSON_PRETTY_PRINT) . "\n";
    }
}

// 5. Check database connection (optional, might fail if DB not configured)
echo "\n5. Checking database connection...\n";
try {
    $feedCount = Feed::count();
    echo "   ✓ Database connected\n";
    echo "   ✓ Total feeds in database: $feedCount\n";
    
    if ($feedCount > 0) {
        $latestFeed = Feed::with('user')->orderBy('created_at', 'desc')->first();
        echo "   ✓ Latest feed:\n";
        echo "     - Action: {$latestFeed->action_type}\n";
        echo "     - User: " . ($latestFeed->user ? $latestFeed->user->first_name . ' ' . $latestFeed->user->last_name : 'System') . "\n";
        echo "     - Created: " . $latestFeed->created_at->diffForHumans() . "\n";
    }
} catch (Exception $e) {
    echo "   ! Database not available or not configured: " . $e->getMessage() . "\n";
    echo "   (This is expected if database is not set up yet)\n";
}

// 6. Check partial syntax
echo "\n6. Checking partial syntax...\n";
$partialContent = file_get_contents($partialPath);

// Check for required variables
$requiredVars = ['feedableType', 'feedableId'];
$missingVars = [];
foreach ($requiredVars as $var) {
    if (strpos($partialContent, "\$$var") === false) {
        $missingVars[] = $var;
    }
}

if (empty($missingVars)) {
    echo "   ✓ All required variables are referenced in partial\n";
} else {
    echo "   ✗ Missing variable references: " . implode(', ', $missingVars) . "\n";
}

// Check for key functions
$functions = ['getUserInitials', 'getAvatarColor', 'formatTimestamp', 'getActionBadgeClass', 'formatActionType'];
$missingFunctions = [];
foreach ($functions as $func) {
    if (strpos($partialContent, "function $func") === false) {
        $missingFunctions[] = $func;
    }
}

if (empty($missingFunctions)) {
    echo "   ✓ All helper functions are defined\n";
} else {
    echo "   ✗ Missing functions: " . implode(', ', $missingFunctions) . "\n";
}

// Check for CSS
if (strpos($partialContent, '<style>') !== false && strpos($partialContent, '</style>') !== false) {
    echo "   ✓ CSS styles are included\n";
} else {
    echo "   ✗ CSS styles not found\n";
}

// 7. Integration instructions
echo "\n==========================================\n";
echo "Integration Instructions:\n";
echo "==========================================\n\n";
echo "To use the feed sidebar partial in your controller:\n\n";
echo "1. In your controller view (e.g., update.php):\n";
echo "   <?= \$this->makePartial('\$/omsb/feeder/partials/_feed_sidebar.htm', [\n";
echo "       'feedableType' => get_class(\$formModel),\n";
echo "       'feedableId' => \$formModel->id,\n";
echo "   ]) ?>\n\n";
echo "2. Create feed entries in your service layer:\n";
echo "   Feed::create([\n";
echo "       'user_id' => BackendAuth::getUser()->id,\n";
echo "       'action_type' => 'create',\n";
echo "       'feedable_type' => PurchaseRequest::class,\n";
echo "       'feedable_id' => \$pr->id,\n";
echo "   ]);\n\n";
echo "3. See USAGE_EXAMPLE.md for complete integration examples\n\n";

echo "==========================================\n";
echo "Verification complete!\n";
echo "==========================================\n\n";
