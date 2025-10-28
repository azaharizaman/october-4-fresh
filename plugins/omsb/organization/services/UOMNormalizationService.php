<?php namespace Omsb\Organization\Services;

use Omsb\Organization\Models\UnitOfMeasure;
use Omsb\Organization\Models\UOMConversion;
use ValidationException;

/**
 * UOMNormalizationService
 * 
 * Centralized service for UOM normalization and multi-UOM conversions.
 * Handles complex scenarios like "1 Box + 11 Packs of 6 + 10 Rolls".
 */
class UOMNormalizationService
{
    /**
     * Normalize a quantity to its base UOM
     * 
     * @param float $quantity Quantity in source UOM
     * @param UnitOfMeasure|int $uom Source UOM (model or ID)
     * @return array ['base_quantity' => float, 'base_uom' => UnitOfMeasure, 'source_uom' => UnitOfMeasure]
     * @throws ValidationException
     */
    public function normalize($quantity, $uom): array
    {
        if (is_int($uom)) {
            $uom = UnitOfMeasure::find($uom);
        }

        if (!$uom) {
            throw new ValidationException(['uom' => 'Invalid UOM provided']);
        }

        $baseUom = $uom->getUltimateBaseUom();
        $baseQuantity = $uom->normalizeToBase($quantity);

        return [
            'base_quantity' => $baseQuantity,
            'base_uom' => $baseUom,
            'source_uom' => $uom,
            'precision' => $baseUom->decimal_places ?? 6
        ];
    }

    /**
     * Denormalize a base quantity to target UOM
     * 
     * @param float $baseQuantity Quantity in base UOM
     * @param UnitOfMeasure|int $baseUom Base UOM (model or ID)
     * @param UnitOfMeasure|int $targetUom Target UOM (model or ID)
     * @return float Quantity in target UOM
     * @throws ValidationException
     */
    public function denormalize($baseQuantity, $baseUom, $targetUom): float
    {
        if (is_int($baseUom)) {
            $baseUom = UnitOfMeasure::find($baseUom);
        }
        if (is_int($targetUom)) {
            $targetUom = UnitOfMeasure::find($targetUom);
        }

        if (!$baseUom || !$targetUom) {
            throw new ValidationException(['uom' => 'Invalid UOM provided']);
        }

        // Validate they share same base
        if ($baseUom->getUltimateBaseUom()->id !== $targetUom->getUltimateBaseUom()->id) {
            throw new ValidationException(['uom' => 'Cannot convert between incompatible UOM types']);
        }

        return $targetUom->denormalizeFromBase($baseQuantity);
    }

    /**
     * Normalize multiple UOM quantities and sum them
     * 
     * Example: ["BOX" => 1, "PACK6" => 11, "ROLL" => 10]
     * Returns total in base UOM (ROLL): 1*72 + 11*6 + 10*1 = 148 ROLLS
     * 
     * @param array $quantities Associative array [uom_code => quantity, ...]
     * @return array ['total_base_quantity' => float, 'base_uom' => UnitOfMeasure, 'breakdown' => array]
     * @throws ValidationException
     */
    public function normalizeMultiple(array $quantities): array
    {
        if (empty($quantities)) {
            throw new ValidationException(['quantities' => 'No quantities provided']);
        }

        $breakdown = [];
        $totalBaseQuantity = 0;
        $baseUom = null;

        foreach ($quantities as $uomCode => $quantity) {
            if ($quantity <= 0) {
                continue; // Skip zero or negative quantities
            }

            $uom = UnitOfMeasure::where('code', $uomCode)
                ->active()
                ->first();

            if (!$uom) {
                throw new ValidationException(['uom' => "UOM '$uomCode' not found or inactive"]);
            }

            // First iteration establishes the base UOM
            if ($baseUom === null) {
                $baseUom = $uom->getUltimateBaseUom();
            } else {
                // Validate all UOMs share same base
                if ($uom->getUltimateBaseUom()->id !== $baseUom->id) {
                    throw new ValidationException([
                        'uom' => "UOM '$uomCode' is incompatible (different base unit)"
                    ]);
                }
            }

            $normalized = $this->normalize($quantity, $uom);
            $totalBaseQuantity += $normalized['base_quantity'];

            $breakdown[] = [
                'uom_code' => $uomCode,
                'uom_name' => $uom->name,
                'quantity' => $quantity,
                'base_quantity' => $normalized['base_quantity'],
                'conversion_factor' => $uom->conversion_to_base_factor ?? 1
            ];
        }

        if ($baseUom === null) {
            throw new ValidationException(['quantities' => 'No valid quantities provided']);
        }

        return [
            'total_base_quantity' => $totalBaseQuantity,
            'base_uom' => $baseUom,
            'base_uom_code' => $baseUom->code,
            'breakdown' => $breakdown,
            'precision' => $baseUom->decimal_places ?? 6
        ];
    }

    /**
     * Breakdown a base quantity into multiple UOMs (largest to smallest)
     * 
     * Example: 148 ROLLS -> 2 BOX + 0 PACK6 + 4 ROLL
     * (2*72 + 0*6 + 4*1 = 148)
     * 
     * @param float $baseQuantity Quantity in base UOM
     * @param UnitOfMeasure|int $baseUom Base UOM
     * @param array $targetUomCodes Array of UOM codes to break down into (ordered largest to smallest)
     * @return array Breakdown with quantities per UOM
     * @throws ValidationException
     */
    public function breakdownQuantity($baseQuantity, $baseUom, array $targetUomCodes): array
    {
        if (is_int($baseUom)) {
            $baseUom = UnitOfMeasure::find($baseUom);
        }

        if (!$baseUom) {
            throw new ValidationException(['uom' => 'Invalid base UOM provided']);
        }

        // Load target UOMs and sort by conversion factor (descending)
        $targetUoms = UnitOfMeasure::whereIn('code', $targetUomCodes)
            ->active()
            ->get()
            ->filter(function ($uom) use ($baseUom) {
                return $uom->getUltimateBaseUom()->id === $baseUom->id;
            })
            ->sortByDesc(function ($uom) {
                return $uom->conversion_to_base_factor ?? 1;
            });

        if ($targetUoms->isEmpty()) {
            throw new ValidationException(['uom' => 'No compatible target UOMs found']);
        }

        $remaining = $baseQuantity;
        $breakdown = [];

        foreach ($targetUoms as $uom) {
            $factor = $uom->conversion_to_base_factor ?? 1;
            $quantity = floor($remaining / $factor);
            
            $breakdown[] = [
                'uom_code' => $uom->code,
                'uom_name' => $uom->name,
                'quantity' => $quantity,
                'base_quantity_represented' => $quantity * $factor,
                'conversion_factor' => $factor
            ];

            $remaining -= ($quantity * $factor);
        }

        return [
            'total_base_quantity' => $baseQuantity,
            'base_uom' => $baseUom,
            'breakdown' => $breakdown,
            'remaining_base_units' => round($remaining, $baseUom->decimal_places ?? 6)
        ];
    }

    /**
     * Convert quantity between two UOMs
     * 
     * @param float $quantity Quantity in source UOM
     * @param UnitOfMeasure|int|string $fromUom Source UOM (model, ID, or code)
     * @param UnitOfMeasure|int|string $toUom Target UOM (model, ID, or code)
     * @return float Converted quantity
     * @throws ValidationException
     */
    public function convert($quantity, $fromUom, $toUom): float
    {
        $fromUom = $this->resolveUom($fromUom);
        $toUom = $this->resolveUom($toUom);

        if (!$fromUom || !$toUom) {
            throw new ValidationException(['uom' => 'Invalid UOM provided']);
        }

        $converted = $fromUom->convertQuantityTo($quantity, $toUom);

        if ($converted === null) {
            throw new ValidationException([
                'uom' => "Cannot convert from {$fromUom->code} to {$toUom->code} (incompatible types)"
            ]);
        }

        return $converted;
    }

    /**
     * Get conversion factor between two UOMs
     * 
     * @param UnitOfMeasure|int|string $fromUom Source UOM
     * @param UnitOfMeasure|int|string $toUom Target UOM
     * @return float|null Conversion factor or null if incompatible
     */
    public function getConversionFactor($fromUom, $toUom): ?float
    {
        $fromUom = $this->resolveUom($fromUom);
        $toUom = $this->resolveUom($toUom);

        if (!$fromUom || !$toUom) {
            return null;
        }

        return $fromUom->getConversionFactorTo($toUom);
    }

    /**
     * Validate if two UOMs are compatible (share same base)
     * 
     * @param UnitOfMeasure|int|string $uom1 First UOM
     * @param UnitOfMeasure|int|string $uom2 Second UOM
     * @return bool True if compatible
     */
    public function areCompatible($uom1, $uom2): bool
    {
        $uom1 = $this->resolveUom($uom1);
        $uom2 = $this->resolveUom($uom2);

        if (!$uom1 || !$uom2) {
            return false;
        }

        return $uom1->getUltimateBaseUom()->id === $uom2->getUltimateBaseUom()->id;
    }

    /**
     * Helper: Resolve UOM from various input types
     * 
     * @param mixed $uom UnitOfMeasure model, int ID, or string code
     * @return UnitOfMeasure|null
     */
    protected function resolveUom($uom): ?UnitOfMeasure
    {
        if ($uom instanceof UnitOfMeasure) {
            return $uom;
        }

        if (is_int($uom)) {
            return UnitOfMeasure::find($uom);
        }

        if (is_string($uom)) {
            return UnitOfMeasure::where('code', $uom)->active()->first();
        }

        return null;
    }

    /**
     * Format quantity with UOM for display
     * 
     * @param float $quantity
     * @param UnitOfMeasure|int|string $uom
     * @param bool $includeSymbol Include UOM symbol instead of code
     * @return string Formatted display (e.g., "148 ROLL" or "148 roll")
     */
    public function formatQuantity($quantity, $uom, bool $includeSymbol = false): string
    {
        $uom = $this->resolveUom($uom);

        if (!$uom) {
            return number_format($quantity, 2) . ' (unknown UOM)';
        }

        $precision = $uom->decimal_places ?? 2;
        $formatted = number_format($quantity, $precision);
        $unit = $includeSymbol && $uom->symbol ? $uom->symbol : $uom->code;

        return "$formatted $unit";
    }
}
