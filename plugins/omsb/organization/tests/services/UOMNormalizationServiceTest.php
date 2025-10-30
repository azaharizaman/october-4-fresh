<?php namespace Omsb\Organization\Tests\Services;

use Omsb\Organization\Services\UOMNormalizationService;
use Omsb\Organization\Models\UnitOfMeasure;
use PluginTestCase;
use System\Classes\PluginManager;
use ValidationException;

/**
 * UOMNormalizationServiceTest
 * 
 * Comprehensive tests for UOM normalization service
 */
class UOMNormalizationServiceTest extends PluginTestCase
{
    protected $service;
    protected $rollUom;
    protected $pack6Uom;
    protected $pack12Uom;
    protected $boxUom;
    protected $drumUom;

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

        $this->service = new UOMNormalizationService();

        // Create base UOM hierarchy for tissue paper
        // ROLL (base) -> PACK6, PACK12, BOX, DRUM
        $this->rollUom = UnitOfMeasure::create([
            'code' => 'ROLL',
            'name' => 'Roll',
            'symbol' => 'roll',
            'uom_type' => 'count',
            'base_uom_id' => null,
            'conversion_to_base_factor' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);

        $this->pack6Uom = UnitOfMeasure::create([
            'code' => 'PACK6',
            'name' => 'Pack of 6',
            'symbol' => 'pk6',
            'uom_type' => 'count',
            'base_uom_id' => $this->rollUom->id,
            'conversion_to_base_factor' => 6,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);

        $this->pack12Uom = UnitOfMeasure::create([
            'code' => 'PACK12',
            'name' => 'Pack of 12',
            'symbol' => 'pk12',
            'uom_type' => 'count',
            'base_uom_id' => $this->rollUom->id,
            'conversion_to_base_factor' => 12,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);

        $this->boxUom = UnitOfMeasure::create([
            'code' => 'BOX',
            'name' => 'Box',
            'symbol' => 'box',
            'uom_type' => 'count',
            'base_uom_id' => $this->rollUom->id,
            'conversion_to_base_factor' => 72,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);

        $this->drumUom = UnitOfMeasure::create([
            'code' => 'DRUM',
            'name' => 'Drum',
            'symbol' => 'drm',
            'uom_type' => 'count',
            'base_uom_id' => $this->rollUom->id,
            'conversion_to_base_factor' => 144,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0
        ]);
    }

    /**
     * Test basic normalization to base UOM
     */
    public function testBasicNormalization()
    {
        // Normalize 2 boxes to rolls
        $result = $this->service->normalize(2, $this->boxUom);

        $this->assertEquals(144, $result['base_quantity']);
        $this->assertEquals($this->rollUom->id, $result['base_uom']->id);
        $this->assertEquals($this->boxUom->id, $result['source_uom']->id);
        $this->assertEquals(0, $result['precision']);
    }

    /**
     * Test normalization with base UOM
     */
    public function testNormalizationWithBaseUom()
    {
        // Normalize 10 rolls (already base)
        $result = $this->service->normalize(10, $this->rollUom);

        $this->assertEquals(10, $result['base_quantity']);
        $this->assertEquals($this->rollUom->id, $result['base_uom']->id);
    }

    /**
     * Test normalization with ID parameter
     */
    public function testNormalizationWithUomId()
    {
        // Pass UOM ID instead of model
        $result = $this->service->normalize(3, $this->pack6Uom->id);

        $this->assertEquals(18, $result['base_quantity']); // 3 * 6
        $this->assertEquals($this->rollUom->id, $result['base_uom']->id);
    }

    /**
     * Test denormalization from base to target UOM
     */
    public function testDenormalization()
    {
        // Convert 144 rolls to boxes
        $boxes = $this->service->denormalize(144, $this->rollUom, $this->boxUom);

        $this->assertEquals(2, $boxes);
    }

    /**
     * Test denormalization to packs
     */
    public function testDenormalizationToPacks()
    {
        // Convert 216 rolls to pack6
        $packs = $this->service->denormalize(216, $this->rollUom, $this->pack6Uom);

        $this->assertEquals(36, $packs); // 216 / 6
    }

    /**
     * Test multi-UOM normalization (physical count scenario)
     */
    public function testMultiUOMNormalization()
    {
        // Physical count: 1 BOX + 11 PACK6 + 10 ROLL
        $count = [
            'BOX' => 1,
            'PACK6' => 11,
            'ROLL' => 10
        ];

        $result = $this->service->normalizeMultiple($count);

        // 1*72 + 11*6 + 10*1 = 148 rolls
        $this->assertEquals(148, $result['total_base_quantity']);
        $this->assertEquals($this->rollUom->id, $result['base_uom']->id);
        $this->assertEquals('ROLL', $result['base_uom_code']);
        $this->assertCount(3, $result['breakdown']);

        // Verify breakdown
        $breakdown = collect($result['breakdown'])->keyBy('uom_code');
        $this->assertEquals(72, $breakdown['BOX']['base_quantity']);
        $this->assertEquals(66, $breakdown['PACK6']['base_quantity']);
        $this->assertEquals(10, $breakdown['ROLL']['base_quantity']);
    }

    /**
     * Test breakdown quantity into multiple UOMs
     */
    public function testBreakdownQuantity()
    {
        // Break 148 rolls into BOX, PACK6, ROLL
        $result = $this->service->breakdownQuantity(
            148,
            $this->rollUom,
            ['BOX', 'PACK6', 'ROLL']
        );

        $this->assertEquals(148, $result['total_base_quantity']);
        
        // Should give: 2 BOX (144 rolls) + 0 PACK6 + 4 ROLL
        $breakdown = collect($result['breakdown'])->keyBy('uom_code');
        $this->assertEquals(2, $breakdown['BOX']['quantity']);
        $this->assertEquals(0, $breakdown['PACK6']['quantity']);
        $this->assertEquals(4, $breakdown['ROLL']['quantity']);
        $this->assertEquals(0, $result['remaining_base_units']);
    }

    /**
     * Test UOM conversion
     */
    public function testConvert()
    {
        // Convert 3 boxes to rolls
        $rolls = $this->service->convert(3, 'BOX', 'ROLL');
        $this->assertEquals(216, $rolls);

        // Convert 216 rolls to pack6
        $packs = $this->service->convert(216, 'ROLL', 'PACK6');
        $this->assertEquals(36, $packs);

        // Convert 1 drum to boxes
        $boxes = $this->service->convert(1, 'DRUM', 'BOX');
        $this->assertEquals(2, $boxes);
    }

    /**
     * Test conversion factor calculation
     */
    public function testGetConversionFactor()
    {
        $factor = $this->service->getConversionFactor('DRUM', 'PACK6');
        $this->assertEquals(24, $factor); // 1 DRUM = 144 rolls = 24 PACK6

        $factor = $this->service->getConversionFactor('BOX', 'PACK12');
        $this->assertEquals(6, $factor); // 1 BOX = 72 rolls = 6 PACK12
    }

    /**
     * Test UOM compatibility check
     */
    public function testAreCompatible()
    {
        // Same hierarchy - compatible
        $this->assertTrue($this->service->areCompatible('BOX', 'ROLL'));
        $this->assertTrue($this->service->areCompatible('DRUM', 'PACK6'));

        // Create incompatible UOM (weight)
        $kgUom = UnitOfMeasure::create([
            'code' => 'KG',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'uom_type' => 'weight',
            'base_uom_id' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 2
        ]);

        // Different hierarchy - incompatible
        $this->assertFalse($this->service->areCompatible('KG', 'ROLL'));
    }

    /**
     * Test incompatible conversion throws exception
     */
    public function testIncompatibleConversionThrowsException()
    {
        $this->expectException(ValidationException::class);

        // Create incompatible UOM
        $kgUom = UnitOfMeasure::create([
            'code' => 'KG',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'uom_type' => 'weight',
            'base_uom_id' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 2
        ]);

        $this->service->convert(10, 'KG', 'ROLL');
    }

    /**
     * Test quantity formatting
     */
    public function testFormatQuantity()
    {
        $formatted = $this->service->formatQuantity(148, $this->rollUom);
        $this->assertEquals('148 ROLL', $formatted);

        $formatted = $this->service->formatQuantity(148.5, $this->rollUom, true);
        $this->assertEquals('149 roll', $formatted); // Using symbol
    }

    /**
     * Test multi-UOM with zero quantities
     */
    public function testMultiUOMWithZeroQuantities()
    {
        $count = [
            'BOX' => 0,
            'PACK6' => 5,
            'ROLL' => 0
        ];

        $result = $this->service->normalizeMultiple($count);

        $this->assertEquals(30, $result['total_base_quantity']); // 5 * 6
        $this->assertCount(1, $result['breakdown']); // Only PACK6 counted
    }

    /**
     * Test empty quantity array throws exception
     */
    public function testEmptyQuantityArrayThrowsException()
    {
        $this->expectException(ValidationException::class);
        
        $this->service->normalizeMultiple([]);
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
