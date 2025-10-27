<?php namespace Omsb\Registrar\Services;

use ValidationException;
use Backend\Facades\BackendAuth;
use Omsb\Organization\Models\Site;
use Omsb\Registrar\Models\DocumentType;
use Omsb\Registrar\Models\DocumentRegistry;

/**
 * DocumentNumberingService
 * 
 * Core service for generating controlled document numbers with anti-fraud protection.
 * Handles complex numbering patterns, site codes, modifiers, and collision detection.
 * 
 * This service ensures:
 * - No duplicate document numbers can be issued
 * - Proper sequence integrity based on reset cycles
 * - Support for complex patterns like PO-HQ-2025-00123(SBW)
 * - Fraud prevention through database-level constraints
 */
class DocumentNumberingService
{
    /**
     * Generate next document number for given type
     * 
     * @param string $documentTypeCode
     * @param string|null $siteCode
     * @param array $modifiers
     * @param array $options
     * @return array ['document_number' => string, 'registry_id' => int]
     * @throws ValidationException
     */
    public function generateDocumentNumber($documentTypeCode, $siteCode = null, $modifiers = [], $options = [])
    {
        $documentType = DocumentType::where('code', $documentTypeCode)
            ->where('is_active', true)
            ->first();

        if (!$documentType) {
            throw new ValidationException([
                'document_type' => "Document type '{$documentTypeCode}' not found or inactive"
            ]);
        }

        // Validate site requirement
        if ($documentType->requires_site_code && !$siteCode) {
            throw new ValidationException([
                'site_code' => "Site code is required for document type '{$documentTypeCode}'"
            ]);
        }

        // Get or validate site
        $site = null;
        if ($siteCode) {
            $site = Site::where('code', $siteCode)->first();
            if (!$site) {
                throw new ValidationException([
                    'site_code' => "Invalid site code: {$siteCode}"
                ]);
            }
        }

        // Generate the number with transaction safety
        return \DB::transaction(function () use ($documentType, $site, $siteCode, $modifiers, $options) {
            return $this->generateNumberInTransaction($documentType, $site, $siteCode, $modifiers, $options);
        });
    }

    /**
     * Generate number within database transaction for safety
     */
    protected function generateNumberInTransaction($documentType, $site, $siteCode, $modifiers, $options)
    {
        // Get current date components
        $now = now();
        $year = $now->year;
        $month = $now->month;

        // Get next sequence number
        $sequenceNumber = $this->getNextSequenceNumber($documentType, $site?->id, $year, $month);

        // Build document number components
        $components = $this->buildNumberComponents($documentType, $siteCode, $year, $month, $sequenceNumber);

        // Validate and format modifiers
        $modifier = $this->validateAndFormatModifier($documentType, $modifiers);

        // Generate the final document number
        $fullDocumentNumber = $this->formatDocumentNumber($documentType, $components, $modifier);

        // Check for duplicates (should be impossible but safety first)
        if (DocumentRegistry::where('full_document_number', $fullDocumentNumber)->exists()) {
            throw new ValidationException([
                'document_number' => "Document number collision detected: {$fullDocumentNumber}"
            ]);
        }

        // Update document type's current number
        $documentType->increment('current_number', $documentType->increment_by);

        // Create registry entry (this will be linked to actual document later)
        $registry = DocumentRegistry::create([
            'document_number' => $components['core_number'],
            'document_type_code' => $documentType->code,
            'site_id' => $site?->id,
            'site_code' => $siteCode,
            'year' => $year,
            'month' => $documentType->requires_month ? $month : null,
            'sequence_number' => $sequenceNumber,
            'modifier' => $modifier,
            'full_document_number' => $fullDocumentNumber,
            'documentable_type' => $options['documentable_type'] ?? 'pending',
            'documentable_id' => $options['documentable_id'] ?? 0,
            'status' => $options['initial_status'] ?? 'draft',
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'generated_by' => BackendAuth::getUser()?->id,
                'generation_options' => $options
            ]
        ]);

        return [
            'document_number' => $fullDocumentNumber,
            'registry_id' => $registry->id,
            'components' => $components,
            'sequence_number' => $sequenceNumber
        ];
    }

    /**
     * Get next sequence number based on reset cycle
     */
    protected function getNextSequenceNumber($documentType, $siteId, $year, $month)
    {
        $query = DocumentRegistry::where('document_type_code', $documentType->code);

        // Apply reset cycle logic
        switch ($documentType->reset_cycle) {
            case 'yearly':
                $query->where('year', $year);
                break;
            case 'monthly':
                $query->where('year', $year)->where('month', $month);
                break;
            case 'never':
                // No additional filters for continuous numbering
                break;
        }

        // Site-specific numbering if required
        if ($documentType->requires_site_code && $siteId) {
            $query->where('site_id', $siteId);
        }

        // Get the highest sequence number
        $lastSequence = $query->max('sequence_number') ?: 0;

        return $lastSequence + $documentType->increment_by;
    }

    /**
     * Build number components based on pattern
     */
    protected function buildNumberComponents($documentType, $siteCode, $year, $month, $sequenceNumber)
    {
        $components = [
            'site_code' => $siteCode,
            'document_code' => $documentType->code,
            'year' => $year,
            'month' => $month,
            'sequence' => str_pad($sequenceNumber, $documentType->number_length, '0', STR_PAD_LEFT),
            'core_number' => null
        ];

        // Build core number without modifiers
        $pattern = $documentType->numbering_pattern;
        $coreNumber = $this->replacePatternTokens($pattern, $components);
        $components['core_number'] = $coreNumber;

        return $components;
    }

    /**
     * Replace pattern tokens with actual values
     */
    protected function replacePatternTokens($pattern, $components)
    {
        $replacements = [
            '{SITE}' => $components['site_code'] ?? '',
            '{CODE}' => $components['document_code'],
            '{YYYY}' => $components['year'],
            '{MM}' => str_pad($components['month'] ?? '', 2, '0', STR_PAD_LEFT),
            '{######}' => $components['sequence'],
            '{#####}' => $components['sequence'],
            '{####}' => $components['sequence'],
            '{########}' => $components['sequence']
        ];

        $formatted = $pattern;
        foreach ($replacements as $token => $value) {
            $formatted = str_replace($token, $value, $formatted);
        }

        return $formatted;
    }

    /**
     * Validate and format modifier
     */
    protected function validateAndFormatModifier($documentType, $modifiers)
    {
        if (!$documentType->supports_modifiers || empty($modifiers)) {
            return null;
        }

        $availableModifiers = $documentType->getAvailableModifiers();
        
        if (is_string($modifiers)) {
            $modifiers = [$modifiers];
        }

        $validModifiers = [];
        foreach ($modifiers as $modifier) {
            if (!array_key_exists($modifier, $availableModifiers)) {
                throw new ValidationException([
                    'modifier' => "Invalid modifier '{$modifier}' for document type '{$documentType->code}'"
                ]);
            }
            $validModifiers[] = $modifier;
        }

        return implode(',', $validModifiers);
    }

    /**
     * Format final document number with modifiers
     */
    protected function formatDocumentNumber($documentType, $components, $modifier)
    {
        $documentNumber = $components['core_number'];

        if ($modifier && $documentType->supports_modifiers) {
            $separator = $documentType->modifier_separator ?: '(';
            $closing = $separator === '(' ? ')' : '';
            
            // Handle multiple modifiers
            $modifierList = explode(',', $modifier);
            foreach ($modifierList as $mod) {
                $documentNumber .= $separator . $mod . $closing;
            }
        }

        return $documentNumber;
    }

    /**
     * Reserve document number for future use
     */
    public function reserveDocumentNumber($documentTypeCode, $siteCode = null, $modifiers = [], $reserveFor = 'draft')
    {
        return $this->generateDocumentNumber($documentTypeCode, $siteCode, $modifiers, [
            'documentable_type' => 'reserved',
            'documentable_id' => 0,
            'initial_status' => $reserveFor
        ]);
    }

    /**
     * Link reserved number to actual document
     */
    public function linkDocumentToRegistry($registryId, $documentable)
    {
        $registry = DocumentRegistry::find($registryId);
        
        if (!$registry) {
            throw new ValidationException(['registry' => 'Document registry not found']);
        }

        if ($registry->documentable_type !== 'reserved' && $registry->documentable_type !== 'pending') {
            throw new ValidationException(['registry' => 'Document number is already linked to another document']);
        }

        $registry->update([
            'documentable_type' => get_class($documentable),
            'documentable_id' => $documentable->id
        ]);

        return $registry;
    }

    /**
     * Validate document number format
     */
    public function validateDocumentNumber($documentNumber, $documentTypeCode = null)
    {
        $registry = DocumentRegistry::where('full_document_number', $documentNumber)->first();
        
        if (!$registry) {
            return ['valid' => false, 'reason' => 'Document number not found in registry'];
        }

        if ($documentTypeCode && $registry->document_type_code !== $documentTypeCode) {
            return ['valid' => false, 'reason' => 'Document number belongs to different document type'];
        }

        if ($registry->is_voided) {
            return ['valid' => false, 'reason' => 'Document number has been voided'];
        }

        return [
            'valid' => true,
            'registry' => $registry,
            'document_type' => $registry->documentType,
            'status' => $registry->status
        ];
    }

    /**
     * Check sequence integrity for audit
     */
    public function checkSequenceIntegrity($documentTypeCode, $year = null, $siteId = null)
    {
        $documentType = DocumentType::where('code', $documentTypeCode)->first();
        if (!$documentType) {
            return ['valid' => false, 'reason' => 'Document type not found'];
        }

        $query = DocumentRegistry::where('document_type_code', $documentTypeCode)
            ->orderBy('sequence_number');

        if ($year) {
            $query->where('year', $year);
        }

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $documents = $query->get();
        $errors = [];
        $expectedSequence = $documentType->starting_number;

        foreach ($documents as $doc) {
            if ($doc->sequence_number !== $expectedSequence) {
                $errors[] = [
                    'document_number' => $doc->full_document_number,
                    'expected_sequence' => $expectedSequence,
                    'actual_sequence' => $doc->sequence_number,
                    'gap' => $doc->sequence_number - $expectedSequence
                ];
            }
            $expectedSequence = $doc->sequence_number + $documentType->increment_by;
        }

        return [
            'valid' => empty($errors),
            'total_documents' => $documents->count(),
            'errors' => $errors,
            'next_expected' => $expectedSequence
        ];
    }

    /**
     * Generate preview of document number pattern
     */
    public function previewDocumentNumber($documentTypeCode, $siteCode = 'HQ', $modifiers = [])
    {
        $documentType = DocumentType::where('code', $documentTypeCode)->first();
        
        if (!$documentType) {
            throw new ValidationException(['document_type' => 'Document type not found']);
        }

        return $documentType->getPatternExample($siteCode);
    }

    /**
     * Bulk number generation for system migration
     */
    public function bulkGenerateNumbers($documentTypeCode, $count, $siteCode = null, $startingDate = null)
    {
        if ($count > 1000) {
            throw new ValidationException(['count' => 'Bulk generation limited to 1000 numbers at once']);
        }

        $numbers = [];
        $startDate = $startingDate ? \Carbon\Carbon::parse($startingDate) : now();

        for ($i = 0; $i < $count; $i++) {
            $numbers[] = $this->generateDocumentNumber($documentTypeCode, $siteCode, [], [
                'documentable_type' => 'bulk_generated',
                'documentable_id' => $i + 1,
                'initial_status' => 'reserved'
            ]);
        }

        return $numbers;
    }
}