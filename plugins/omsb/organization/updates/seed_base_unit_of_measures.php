<?php namespace Omsb\Organization\Updates;

use Omsb\Organization\Models\UnitOfMeasure;
use October\Rain\Database\Updates\Seeder;
use BackendAuth;

/**
 * SeedBaseUnitOfMeasures Seeder
 * 
 * Seeds common organizational UOMs with base normalization hierarchy.
 * 
 * Example hierarchy for tissue paper:
 * - ROLL (base unit)
 * - PACK6 = 6 ROLLS
 * - PACK12 = 12 ROLLS  
 * - BOX = 72 ROLLS (12 packs of 6)
 * - DRUM = 144 ROLLS (2 boxes)
 */
class SeedBaseUnitOfMeasures extends Seeder
{
    public function run()
    {
        // ================================
        // COUNT TYPE - ROLLS (Base Unit)
        // ================================
        $roll = UnitOfMeasure::create([
            'code' => 'ROLL',
            'name' => 'Roll',
            'symbol' => 'roll',
            'uom_type' => 'count',
            'base_uom_id' => null, // This IS the base
            'conversion_to_base_factor' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0,
            'description' => 'Single roll (base unit for tissue paper)'
        ]);

        // Pack of 6 Rolls
        UnitOfMeasure::create([
            'code' => 'PACK6',
            'name' => 'Pack of 6',
            'symbol' => 'pk6',
            'uom_type' => 'count',
            'base_uom_id' => $roll->id,
            'conversion_to_base_factor' => 6, // 1 PACK6 = 6 ROLLS
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0,
            'description' => 'Shrink-wrapped pack of 6 rolls'
        ]);

        // Pack of 12 Rolls
        UnitOfMeasure::create([
            'code' => 'PACK12',
            'name' => 'Pack of 12',
            'symbol' => 'pk12',
            'uom_type' => 'count',
            'base_uom_id' => $roll->id,
            'conversion_to_base_factor' => 12, // 1 PACK12 = 12 ROLLS
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0,
            'description' => 'Shrink-wrapped pack of 12 rolls'
        ]);

        // Box (12 packs of 6 = 72 rolls)
        UnitOfMeasure::create([
            'code' => 'BOX',
            'name' => 'Box',
            'symbol' => 'box',
            'uom_type' => 'count',
            'base_uom_id' => $roll->id,
            'conversion_to_base_factor' => 72, // 1 BOX = 72 ROLLS
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0,
            'description' => 'Cardboard box containing 12 packs of 6 rolls (72 rolls total)'
        ]);

        // Drum (2 boxes = 144 rolls)
        UnitOfMeasure::create([
            'code' => 'DRUM',
            'name' => 'Drum',
            'symbol' => 'drum',
            'uom_type' => 'count',
            'base_uom_id' => $roll->id,
            'conversion_to_base_factor' => 144, // 1 DRUM = 144 ROLLS
            'for_purchase' => true,
            'for_inventory' => false, // Not typically used in inventory
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0,
            'description' => 'Large drum containing 2 boxes (144 rolls total)'
        ]);

        // ================================
        // COUNT TYPE - GENERIC
        // ================================
        UnitOfMeasure::create([
            'code' => 'EA',
            'name' => 'Each',
            'symbol' => 'ea',
            'uom_type' => 'count',
            'base_uom_id' => null, // Base unit
            'conversion_to_base_factor' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0,
            'description' => 'Single unit'
        ]);

        $dozen = UnitOfMeasure::create([
            'code' => 'DOZ',
            'name' => 'Dozen',
            'symbol' => 'doz',
            'uom_type' => 'count',
            'base_uom_id' => null, // Can also be a base
            'conversion_to_base_factor' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 0,
            'description' => '12 units'
        ]);

        // ================================
        // WEIGHT TYPE
        // ================================
        $gram = UnitOfMeasure::create([
            'code' => 'G',
            'name' => 'Gram',
            'symbol' => 'g',
            'uom_type' => 'weight',
            'base_uom_id' => null, // Base unit for weight
            'conversion_to_base_factor' => null,
            'for_purchase' => false,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 2,
            'description' => 'Base unit for weight measurements'
        ]);

        UnitOfMeasure::create([
            'code' => 'KG',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'uom_type' => 'weight',
            'base_uom_id' => $gram->id,
            'conversion_to_base_factor' => 1000, // 1 KG = 1000 G
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 3,
            'description' => '1000 grams'
        ]);

        UnitOfMeasure::create([
            'code' => 'MT',
            'name' => 'Metric Ton',
            'symbol' => 'mt',
            'uom_type' => 'weight',
            'base_uom_id' => $gram->id,
            'conversion_to_base_factor' => 1000000, // 1 MT = 1,000,000 G
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 3,
            'description' => '1000 kilograms'
        ]);

        // ================================
        // VOLUME TYPE
        // ================================
        $liter = UnitOfMeasure::create([
            'code' => 'L',
            'name' => 'Liter',
            'symbol' => 'l',
            'uom_type' => 'volume',
            'base_uom_id' => null, // Base unit for volume
            'conversion_to_base_factor' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 2,
            'description' => 'Base unit for volume measurements'
        ]);

        UnitOfMeasure::create([
            'code' => 'ML',
            'name' => 'Milliliter',
            'symbol' => 'ml',
            'uom_type' => 'volume',
            'base_uom_id' => $liter->id,
            'conversion_to_base_factor' => 0.001, // 1 ML = 0.001 L
            'for_purchase' => false,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 2,
            'description' => '0.001 liters'
        ]);

        // ================================
        // LENGTH TYPE
        // ================================
        $meter = UnitOfMeasure::create([
            'code' => 'M',
            'name' => 'Meter',
            'symbol' => 'm',
            'uom_type' => 'length',
            'base_uom_id' => null, // Base unit for length
            'conversion_to_base_factor' => null,
            'for_purchase' => true,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 2,
            'description' => 'Base unit for length measurements'
        ]);

        UnitOfMeasure::create([
            'code' => 'CM',
            'name' => 'Centimeter',
            'symbol' => 'cm',
            'uom_type' => 'length',
            'base_uom_id' => $meter->id,
            'conversion_to_base_factor' => 0.01, // 1 CM = 0.01 M
            'for_purchase' => false,
            'for_inventory' => true,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 2,
            'description' => '0.01 meters'
        ]);

        UnitOfMeasure::create([
            'code' => 'KM',
            'name' => 'Kilometer',
            'symbol' => 'km',
            'uom_type' => 'length',
            'base_uom_id' => $meter->id,
            'conversion_to_base_factor' => 1000, // 1 KM = 1000 M
            'for_purchase' => false,
            'for_inventory' => false,
            'is_approved' => true,
            'is_active' => true,
            'decimal_places' => 3,
            'description' => '1000 meters'
        ]);

        $this->command->info('Seeded ' . UnitOfMeasure::count() . ' base unit of measures');
    }
}
