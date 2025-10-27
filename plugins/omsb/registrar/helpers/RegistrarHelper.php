<?php namespace Omsb\Registrar\Helpers;

use Omsb\Registrar\Services\DocumentNumberingService;
use Omsb\Registrar\Services\DocumentAuditService;
use Omsb\Registrar\Models\DocumentRegistry;

/**
 * RegistrarHelper
 * 
 * Convenience helper class for common Registrar operations.
 * Provides static methods for easy integration with other plugins.
 */
class RegistrarHelper
{
    /**
     * Quick document number generation
     * 
     * @param string $documentTypeCode
     * @param string|null $siteCode
     * @param array $modifiers
     * @return string Generated document number
     */
    public static function generateNumber($documentTypeCode, $siteCode = null, $modifiers = [])
    {
        $service = new DocumentNumberingService();
        $result = $service->generateDocumentNumber($documentTypeCode, $siteCode, $modifiers);
        
        return $result['document_number'];
    }

    /**
     * Reserve document number for later linking
     * 
     * @param string $documentTypeCode
     * @param string|null $siteCode
     * @param array $modifiers
     * @return array ['document_number' => string, 'registry_id' => int]
     */
    public static function reserveNumber($documentTypeCode, $siteCode = null, $modifiers = [])
    {
        $service = new DocumentNumberingService();
        return $service->reserveDocumentNumber($documentTypeCode, $siteCode, $modifiers);
    }

    /**
     * Link reserved number to document
     * 
     * @param int $registryId
     * @param mixed $document
     * @return DocumentRegistry
     */
    public static function linkDocument($registryId, $document)
    {
        $service = new DocumentNumberingService();
        return $service->linkDocumentToRegistry($registryId, $document);
    }

    /**
     * Validate document number
     * 
     * @param string $documentNumber
     * @param string|null $expectedType
     * @return array Validation result
     */
    public static function validateNumber($documentNumber, $expectedType = null)
    {
        $service = new DocumentNumberingService();
        return $service->validateDocumentNumber($documentNumber, $expectedType);
    }

    /**
     * Quick audit logging
     * 
     * @param mixed $document
     * @param string $action
     * @param string|null $reason
     * @return \Omsb\Registrar\Models\DocumentAuditTrail
     */
    public static function logAction($document, $action, $reason = null)
    {
        $registry = $document->documentRegistry ?? self::findRegistry($document);
        
        if (!$registry) {
            throw new \Exception('Document registry not found for audit logging');
        }

        $auditService = new DocumentAuditService();
        
        switch ($action) {
            case 'access':
            case 'view':
                return $auditService->logDocumentAccess($registry, $action);
            
            case 'print':
                return $auditService->logDocumentPrint($registry, 'PDF');
            
            case 'lock':
                return $auditService->logDocumentLock($registry, $reason ?: 'Document locked');
            
            case 'unlock':
                return $auditService->logDocumentUnlock($registry, $reason ?: 'Document unlocked');
            
            case 'void':
                return $auditService->logDocumentVoid($registry, $reason ?: 'Document voided');
            
            default:
                throw new \Exception("Unknown audit action: {$action}");
        }
    }

    /**
     * Find document registry for a model
     * 
     * @param mixed $document
     * @return DocumentRegistry|null
     */
    public static function findRegistry($document)
    {
        return DocumentRegistry::where('documentable_type', get_class($document))
            ->where('documentable_id', $document->id)
            ->first();
    }

    /**
     * Check if document can be edited
     * 
     * @param mixed $document
     * @return bool
     */
    public static function canEdit($document)
    {
        if (method_exists($document, 'canBeEdited')) {
            return $document->canBeEdited();
        }

        $registry = self::findRegistry($document);
        return $registry ? $registry->canBeEdited() : true;
    }

    /**
     * Get document protection status
     * 
     * @param mixed $document
     * @return array
     */
    public static function getProtectionStatus($document)
    {
        $registry = self::findRegistry($document);
        
        if (!$registry) {
            return ['protected' => false, 'reason' => null];
        }

        return [
            'protected' => !$registry->canBeEdited(),
            'locked' => $registry->is_locked,
            'voided' => $registry->is_voided,
            'reason' => $registry->lock_reason ?: ($registry->is_voided ? 'Document is voided' : null)
        ];
    }

    /**
     * Get audit summary for document
     * 
     * @param mixed $document
     * @return array
     */
    public static function getAuditSummary($document)
    {
        $registry = self::findRegistry($document);
        
        if (!$registry) {
            return ['error' => 'Document registry not found'];
        }

        $auditService = new DocumentAuditService();
        return $auditService->getAuditSummary($registry->id);
    }

    /**
     * Check sequence integrity for document type
     * 
     * @param string $documentTypeCode
     * @param int|null $year
     * @param int|null $siteId
     * @return array
     */
    public static function checkIntegrity($documentTypeCode, $year = null, $siteId = null)
    {
        $service = new DocumentNumberingService();
        return $service->checkSequenceIntegrity($documentTypeCode, $year, $siteId);
    }

    /**
     * Preview document number format
     * 
     * @param string $documentTypeCode
     * @param string $siteCode
     * @param array $modifiers
     * @return string
     */
    public static function previewNumber($documentTypeCode, $siteCode = 'HQ', $modifiers = [])
    {
        $service = new DocumentNumberingService();
        return $service->previewDocumentNumber($documentTypeCode, $siteCode, $modifiers);
    }

    /**
     * Get compliance report for date range
     * 
     * @param string|null $documentTypeCode
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public static function getComplianceReport($documentTypeCode = null, $dateFrom = null, $dateTo = null)
    {
        $auditService = new DocumentAuditService();
        return $auditService->generateComplianceReport($documentTypeCode, $dateFrom, $dateTo);
    }

    /**
     * Search audit trails
     * 
     * @param array $criteria
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public static function searchAuditTrail($criteria)
    {
        $auditService = new DocumentAuditService();
        return $auditService->searchAuditTrail($criteria);
    }

    /**
     * Quick void operation
     * 
     * @param mixed $document
     * @param string $reason
     * @return bool
     */
    public static function voidDocument($document, $reason)
    {
        if (method_exists($document, 'voidDocument')) {
            return $document->voidDocument($reason);
        }

        $registry = self::findRegistry($document);
        if ($registry) {
            return $registry->voidDocument($reason);
        }

        throw new \Exception('Cannot void document: registry not found');
    }

    /**
     * Lock document
     * 
     * @param mixed $document
     * @param string|null $reason
     * @return bool
     */
    public static function lockDocument($document, $reason = null)
    {
        if (method_exists($document, 'lockDocument')) {
            return $document->lockDocument($reason);
        }

        $registry = self::findRegistry($document);
        if ($registry) {
            return $registry->lockDocument($reason);
        }

        throw new \Exception('Cannot lock document: registry not found');
    }

    /**
     * Get document statistics
     * 
     * @param string|null $documentTypeCode
     * @param int|null $siteId
     * @return array
     */
    public static function getDocumentStatistics($documentTypeCode = null, $siteId = null)
    {
        $query = DocumentRegistry::query();
        
        if ($documentTypeCode) {
            $query->where('document_type_code', $documentTypeCode);
        }
        
        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $total = $query->count();
        $active = $query->where('is_voided', false)->count();
        $voided = $query->where('is_voided', true)->count();
        $locked = $query->where('is_locked', true)->count();
        $thisYear = $query->whereYear('created_at', date('Y'))->count();

        return [
            'total_documents' => $total,
            'active_documents' => $active,
            'voided_documents' => $voided,
            'locked_documents' => $locked,
            'this_year_count' => $thisYear,
            'void_percentage' => $total > 0 ? round(($voided / $total) * 100, 2) : 0,
            'lock_percentage' => $total > 0 ? round(($locked / $total) * 100, 2) : 0
        ];
    }

    /**
     * Find duplicate document numbers (fraud detection)
     * 
     * @return array
     */
    public static function findDuplicateNumbers()
    {
        $duplicates = DocumentRegistry::selectRaw('full_document_number, COUNT(*) as count')
            ->groupBy('full_document_number')
            ->having('count', '>', 1)
            ->get();

        $results = [];
        foreach ($duplicates as $duplicate) {
            $documents = DocumentRegistry::where('full_document_number', $duplicate->full_document_number)
                ->with(['documentable'])
                ->get();
            
            $results[] = [
                'document_number' => $duplicate->full_document_number,
                'duplicate_count' => $duplicate->count,
                'documents' => $documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'created_at' => $doc->created_at,
                        'documentable_type' => $doc->documentable_type,
                        'documentable_id' => $doc->documentable_id,
                        'status' => $doc->status
                    ];
                })
            ];
        }

        return $results;
    }

    /**
     * Validate document integrity
     * 
     * @param mixed $document
     * @return array
     */
    public static function validateDocumentIntegrity($document)
    {
        $registry = self::findRegistry($document);
        
        if (!$registry) {
            return ['valid' => false, 'errors' => ['Registry entry not found']];
        }

        return [
            'valid' => true,
            'registry_errors' => $registry->validateIntegrity(),
            'can_edit' => $registry->canBeEdited(),
            'protection_status' => self::getProtectionStatus($document)
        ];
    }
}