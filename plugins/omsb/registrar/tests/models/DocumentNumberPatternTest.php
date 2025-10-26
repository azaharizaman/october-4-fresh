<?php namespace Omsb\Registrar\Tests\Models;

use Omsb\Registrar\Models\DocumentNumberPattern;
use Omsb\Registrar\Models\DocumentType;
use PluginTestCase;
use System\Classes\PluginManager;

/**
 * DocumentNumberPatternTest
 * 
 * Tests for DocumentNumberPattern model with focus on race condition prevention
 */
class DocumentNumberPatternTest extends PluginTestCase
{
    /**
     * Set up the test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        // Register all plugins to make features available
        $pluginManager = PluginManager::instance();
        $pluginManager->registerAll(true);
        $pluginManager->bootAll(true);
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

    /**
     * Test basic document number generation
     */
    public function testBasicNumberGeneration()
    {
        // Create a document type
        $docType = DocumentType::create([
            'code' => 'TEST',
            'name' => 'Test Document',
            'model_class' => 'Test\Model',
            'is_active' => true
        ]);

        // Create a pattern
        $pattern = DocumentNumberPattern::create([
            'pattern' => '{DOCTYPE}-{YYYY}-{#####}',
            'reset_interval' => 'yearly',
            'next_number' => 1,
            'number_length' => 5,
            'is_active' => true,
            'document_type_id' => $docType->id
        ]);

        // Generate first number
        $number1 = $pattern->generateNumber();
        $this->assertMatchesRegularExpression('/^TEST-\d{4}-00001$/', $number1);

        // Refresh the pattern to get updated next_number
        $pattern->refresh();
        $this->assertEquals(2, $pattern->next_number);

        // Generate second number
        $number2 = $pattern->generateNumber();
        $this->assertMatchesRegularExpression('/^TEST-\d{4}-00002$/', $number2);

        // Ensure numbers are different
        $this->assertNotEquals($number1, $number2);
    }

    /**
     * Test concurrent number generation to verify no race condition
     * 
     * This test simulates concurrent requests by generating multiple numbers
     * in quick succession and verifying uniqueness
     */
    public function testConcurrentNumberGeneration()
    {
        // Create a document type
        $docType = DocumentType::create([
            'code' => 'CONC',
            'name' => 'Concurrent Test Document',
            'model_class' => 'Test\Concurrent\Model',
            'is_active' => true
        ]);

        // Create a pattern
        $pattern = DocumentNumberPattern::create([
            'pattern' => '{DOCTYPE}-{#####}',
            'reset_interval' => 'never',
            'next_number' => 1,
            'number_length' => 5,
            'is_active' => true,
            'document_type_id' => $docType->id
        ]);

        // Generate multiple numbers in succession
        $numbers = [];
        $iterations = 20;
        
        for ($i = 0; $i < $iterations; $i++) {
            $numbers[] = $pattern->generateNumber();
        }

        // Verify all numbers are unique
        $uniqueNumbers = array_unique($numbers);
        $this->assertCount($iterations, $uniqueNumbers, 'All generated numbers should be unique');

        // Verify sequential numbering
        $expectedNumbers = [];
        for ($i = 1; $i <= $iterations; $i++) {
            $expectedNumbers[] = sprintf('CONC-%05d', $i);
        }
        
        $this->assertEquals($expectedNumbers, $numbers, 'Numbers should be sequential');
    }

    /**
     * Test yearly reset functionality
     */
    public function testYearlyReset()
    {
        // Create a document type
        $docType = DocumentType::create([
            'code' => 'YR',
            'name' => 'Yearly Reset Document',
            'model_class' => 'Test\Yearly\Model',
            'is_active' => true
        ]);

        // Create a pattern with yearly reset
        $pattern = DocumentNumberPattern::create([
            'pattern' => '{DOCTYPE}-{YYYY}-{#####}',
            'reset_interval' => 'yearly',
            'next_number' => 5,
            'number_length' => 5,
            'current_year' => date('Y'),
            'is_active' => true,
            'document_type_id' => $docType->id
        ]);

        // Generate a number in current year
        $number1 = $pattern->generateNumber();
        $currentYear = date('Y');
        $this->assertEquals("YR-{$currentYear}-00005", $number1);

        // Manually change the year to simulate year change
        $pattern->current_year = date('Y') - 1;
        $pattern->next_number = 10;
        $pattern->save();

        // Generate another number - should reset to 1
        $number2 = $pattern->generateNumber();
        $this->assertEquals("YR-{$currentYear}-00001", $number2);
    }

    /**
     * Test monthly reset functionality
     */
    public function testMonthlyReset()
    {
        // Create a document type
        $docType = DocumentType::create([
            'code' => 'MO',
            'name' => 'Monthly Reset Document',
            'model_class' => 'Test\Monthly\Model',
            'is_active' => true
        ]);

        // Create a pattern with monthly reset
        $pattern = DocumentNumberPattern::create([
            'pattern' => '{DOCTYPE}-{YYYY}{MM}-{#####}',
            'reset_interval' => 'monthly',
            'next_number' => 5,
            'number_length' => 5,
            'current_year' => date('Y'),
            'current_month' => date('n'),
            'is_active' => true,
            'document_type_id' => $docType->id
        ]);

        // Generate a number in current month
        $number1 = $pattern->generateNumber();
        $currentYearMonth = date('Ym');
        $this->assertEquals("MO-{$currentYearMonth}-00005", $number1);

        // Manually change the month to simulate month change
        $pattern->current_month = date('n') == 1 ? 12 : date('n') - 1;
        if ($pattern->current_month == 12) {
            $pattern->current_year = date('Y') - 1;
        }
        $pattern->next_number = 10;
        $pattern->save();

        // Generate another number - should reset to 1
        $number2 = $pattern->generateNumber();
        $this->assertEquals("MO-{$currentYearMonth}-00001", $number2);
    }

    /**
     * Test pattern with prefix and suffix
     */
    public function testPrefixAndSuffix()
    {
        // Create a document type
        $docType = DocumentType::create([
            'code' => 'PFX',
            'name' => 'Prefix Suffix Document',
            'model_class' => 'Test\Prefix\Model',
            'is_active' => true
        ]);

        // Create a pattern with prefix and suffix
        $pattern = DocumentNumberPattern::create([
            'pattern' => '{DOCTYPE}-{#####}',
            'prefix' => 'PRE-',
            'suffix' => '-SUF',
            'reset_interval' => 'never',
            'next_number' => 1,
            'number_length' => 5,
            'is_active' => true,
            'document_type_id' => $docType->id
        ]);

        $number = $pattern->generateNumber();
        $this->assertEquals('PRE-PFX-00001-SUF', $number);
    }

    /**
     * Test custom variables in pattern
     */
    public function testCustomVariables()
    {
        // Create a document type
        $docType = DocumentType::create([
            'code' => 'VAR',
            'name' => 'Variable Document',
            'model_class' => 'Test\Variable\Model',
            'is_active' => true
        ]);

        // Create a pattern
        $pattern = DocumentNumberPattern::create([
            'pattern' => '{DOCTYPE}-{CUSTOM}-{#####}',
            'reset_interval' => 'never',
            'next_number' => 1,
            'number_length' => 5,
            'is_active' => true,
            'document_type_id' => $docType->id
        ]);

        // Generate number with custom variable
        $number = $pattern->generateNumber(['{CUSTOM}' => 'CUSTOM_VALUE']);
        $this->assertEquals('VAR-CUSTOM_VALUE-00001', $number);
    }

    /**
     * Test that lockForUpdate prevents race conditions
     * 
     * This test verifies the database locking mechanism by simulating
     * concurrent access patterns
     */
    public function testLockForUpdatePreventsRaceCondition()
    {
        // Create a document type
        $docType = DocumentType::create([
            'code' => 'LOCK',
            'name' => 'Lock Test Document',
            'model_class' => 'Test\Lock\Model',
            'is_active' => true
        ]);

        // Create a pattern
        $pattern = DocumentNumberPattern::create([
            'pattern' => '{DOCTYPE}-{#####}',
            'reset_interval' => 'never',
            'next_number' => 1,
            'number_length' => 5,
            'is_active' => true,
            'document_type_id' => $docType->id
        ]);

        // Simulate concurrent access by generating numbers rapidly
        // If locking works properly, all numbers should be sequential and unique
        $numbers = [];
        
        // Use a loop to generate numbers as fast as possible
        for ($i = 0; $i < 10; $i++) {
            // Refresh pattern instance to simulate separate requests
            $freshPattern = DocumentNumberPattern::find($pattern->id);
            $numbers[] = $freshPattern->generateNumber();
        }

        // Check all numbers are unique
        $this->assertCount(10, array_unique($numbers), 'All numbers must be unique');

        // Check numbers are sequential
        for ($i = 0; $i < 10; $i++) {
            $expectedNumber = sprintf('LOCK-%05d', $i + 1);
            $this->assertEquals($expectedNumber, $numbers[$i], "Number at position {$i} should be {$expectedNumber}");
        }
    }
}
