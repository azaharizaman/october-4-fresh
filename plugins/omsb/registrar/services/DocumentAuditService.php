<?php namespace Omsb\Registrar\Services;

use Omsb\Registrar\Models\DocumentRegistry;
use Omsb\Registrar\Models\DocumentAuditTrail;
use Backend\Facades\BackendAuth;

/**
 * DocumentAuditService
 * 
 * Comprehensive audit tracking service for all document operations.
 * Provides compliance-grade audit trails with forensic-level detail.
 * 
 * Key Features:
 * - Automatic change tracking with before/after values
 * - User, timestamp, and IP tracking
 * - Compliance reporting capabilities
 * - Fraud detection through pattern analysis
 * - Immutable audit records
 */
class DocumentAuditService
{
    /**
     * Log document creation
     */
    public function logDocumentCreation($documentRegistry, $additionalData = [])
    {
        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'create',
            'old_values' => null,
            'new_values' => [
                'document_number' => $documentRegistry->full_document_number,
                'status' => $documentRegistry->status,
                'site_id' => $documentRegistry->site_id
            ],
            'reason' => 'Document created',
            'metadata' => array_merge([
                'initial_creation' => true,
                'document_type' => $documentRegistry->document_type_code
            ], $additionalData)
        ]);
    }

    /**
     * Log document update with field-level change tracking
     */
    public function logDocumentUpdate($documentRegistry, $oldValues, $newValues, $reason = null)
    {
        // Filter out unchanged values
        $changes = $this->getFieldChanges($oldValues, $newValues);
        
        if (empty($changes['old']) && empty($changes['new'])) {
            return null; // No changes to log
        }

        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'update',
            'old_values' => $changes['old'],
            'new_values' => $changes['new'],
            'reason' => $reason ?: 'Document updated',
            'metadata' => [
                'change_count' => count($changes['new']),
                'fields_changed' => array_keys($changes['new'])
            ]
        ]);
    }

    /**
     * Log status change
     */
    public function logStatusChange($documentRegistry, $oldStatus, $newStatus, $reason = null)
    {
        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'status_change',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus],
            'reason' => $reason ?: "Status changed from {$oldStatus} to {$newStatus}",
            'metadata' => [
                'status_transition' => "{$oldStatus} → {$newStatus}",
                'is_workflow_action' => true
            ]
        ]);
    }

    /**
     * Log document locking
     */
    public function logDocumentLock($documentRegistry, $reason)
    {
        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'lock',
            'old_values' => ['is_locked' => false],
            'new_values' => ['is_locked' => true, 'lock_reason' => $reason],
            'reason' => $reason,
            'metadata' => [
                'protection_level' => 'locked',
                'lock_timestamp' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Log document unlocking
     */
    public function logDocumentUnlock($documentRegistry, $reason)
    {
        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'unlock',
            'old_values' => ['is_locked' => true],
            'new_values' => ['is_locked' => false],
            'reason' => $reason,
            'metadata' => [
                'protection_level' => 'unlocked',
                'unlock_timestamp' => now()->toISOString(),
                'administrative_action' => true
            ]
        ]);
    }

    /**
     * Log document voiding
     */
    public function logDocumentVoid($documentRegistry, $reason)
    {
        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'void',
            'old_values' => [
                'is_voided' => false,
                'status' => $documentRegistry->previous_status
            ],
            'new_values' => [
                'is_voided' => true,
                'status' => 'voided',
                'void_reason' => $reason
            ],
            'reason' => $reason,
            'metadata' => [
                'void_timestamp' => now()->toISOString(),
                'previous_status' => $documentRegistry->previous_status,
                'irreversible_action' => true
            ]
        ]);
    }

    /**
     * Log document access/view
     */
    public function logDocumentAccess($documentRegistry, $accessType = 'view')
    {
        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'access',
            'old_values' => null,
            'new_values' => ['access_type' => $accessType],
            'reason' => "Document {$accessType}ed",
            'metadata' => [
                'access_type' => $accessType,
                'document_status' => $documentRegistry->status,
                'tracking_only' => true
            ]
        ]);
    }

    /**
     * Log printing/export activities
     */
    public function logDocumentPrint($documentRegistry, $format = 'PDF', $recipient = null)
    {
        return $this->createAuditEntry([
            'document_registry_id' => $documentRegistry->id,
            'document_type_code' => $documentRegistry->document_type_code,
            'action' => 'print',
            'old_values' => null,
            'new_values' => [
                'format' => $format,
                'recipient' => $recipient
            ],
            'reason' => "Document printed in {$format} format",
            'metadata' => [
                'print_format' => $format,
                'print_timestamp' => now()->toISOString(),
                'recipient' => $recipient,
                'compliance_critical' => true
            ]
        ]);
    }

    /**
     * Create audit entry with standard metadata
     */
    protected function createAuditEntry($data)
    {
        $user = BackendAuth::getUser();
        
        $auditData = array_merge($data, [
            'performed_by' => $user?->id,
            'performed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($data['metadata'] ?? [], [
                'session_id' => session()->getId(),
                'request_id' => request()->header('X-Request-ID'),
                'user_full_name' => $user?->full_name,
                'audit_version' => '1.0'
            ])
        ]);

        return DocumentAuditTrail::create($auditData);
    }

    /**
     * Get field-level changes between old and new values
     */
    protected function getFieldChanges($oldValues, $newValues)
    {
        $oldChanges = [];
        $newChanges = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            
            // Only log actual changes
            if ($oldValue !== $newValue) {
                $oldChanges[$field] = $oldValue;
                $newChanges[$field] = $newValue;
            }
        }

        return ['old' => $oldChanges, 'new' => $newChanges];
    }

    /**
     * Get complete audit history for a document
     */
    public function getDocumentAuditHistory($documentRegistryId, $includeSystem = false)
    {
        $query = DocumentAuditTrail::where('document_registry_id', $documentRegistryId)
            ->with(['performer'])
            ->orderBy('performed_at', 'desc');

        if (!$includeSystem) {
            $query->whereNotIn('action', ['access']);
        }

        return $query->get();
    }

    /**
     * Get audit summary for compliance reporting
     */
    public function getAuditSummary($documentRegistryId)
    {
        $auditTrail = $this->getDocumentAuditHistory($documentRegistryId, true);
        
        $summary = [
            'total_actions' => $auditTrail->count(),
            'unique_users' => $auditTrail->pluck('performed_by')->unique()->count(),
            'date_range' => [
                'first_action' => $auditTrail->last()?->performed_at,
                'last_action' => $auditTrail->first()?->performed_at
            ],
            'action_breakdown' => $auditTrail->groupBy('action')->map->count(),
            'status_changes' => $auditTrail->where('action', 'status_change')->count(),
            'protection_actions' => $auditTrail->whereIn('action', ['lock', 'unlock', 'void'])->count(),
            'compliance_flags' => $this->checkComplianceFlags($auditTrail)
        ];

        return $summary;
    }

    /**
     * Check for compliance red flags
     */
    protected function checkComplianceFlags($auditTrail)
    {
        $flags = [];

        // Check for suspicious patterns
        $userActions = $auditTrail->groupBy('performed_by');
        foreach ($userActions as $userId => $actions) {
            // Flag: Same user creating and immediately voiding
            $creates = $actions->where('action', 'create');
            $voids = $actions->where('action', 'void');
            if ($creates->count() > 0 && $voids->count() > 0) {
                $flags[] = 'same_user_create_void';
            }

            // Flag: Multiple status changes in short time
            $statusChanges = $actions->where('action', 'status_change');
            if ($statusChanges->count() > 5) {
                $flags[] = 'excessive_status_changes';
            }
        }

        // Flag: Access from unusual IP addresses
        $ipAddresses = $auditTrail->pluck('ip_address')->unique();
        if ($ipAddresses->count() > 10) {
            $flags[] = 'multiple_ip_access';
        }

        // Flag: After-hours modifications
        $afterHoursActions = $auditTrail->filter(function ($action) {
            $hour = $action->performed_at->hour;
            return $hour < 7 || $hour > 19; // Outside 7 AM - 7 PM
        });
        if ($afterHoursActions->count() > 0) {
            $flags[] = 'after_hours_modifications';
        }

        return $flags;
    }

    /**
     * Generate compliance report for audit
     */
    public function generateComplianceReport($documentTypeCode = null, $dateFrom = null, $dateTo = null)
    {
        $query = DocumentAuditTrail::with(['documentRegistry', 'performer']);

        if ($documentTypeCode) {
            $query->where('document_type_code', $documentTypeCode);
        }

        if ($dateFrom) {
            $query->where('performed_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('performed_at', '<=', $dateTo);
        }

        $auditData = $query->get();

        return [
            'report_period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'total_actions' => $auditData->count(),
            'unique_documents' => $auditData->pluck('document_registry_id')->unique()->count(),
            'unique_users' => $auditData->pluck('performed_by')->unique()->count(),
            'action_breakdown' => $auditData->groupBy('action')->map->count(),
            'hourly_distribution' => $auditData->groupBy(function ($item) {
                return $item->performed_at->format('H');
            })->map->count(),
            'user_activity' => $auditData->groupBy('performed_by')->map(function ($userActions) {
                return [
                    'total_actions' => $userActions->count(),
                    'action_types' => $userActions->pluck('action')->unique()->values()
                ];
            }),
            'compliance_flags' => $this->checkComplianceFlags($auditData),
            'generated_at' => now(),
            'generated_by' => BackendAuth::getUser()?->full_name
        ];
    }

    /**
     * Search audit trail for forensic analysis
     */
    public function searchAuditTrail($criteria)
    {
        $query = DocumentAuditTrail::with(['documentRegistry', 'performer']);

        // Filter by user
        if (isset($criteria['user_id'])) {
            $query->where('performed_by', $criteria['user_id']);
        }

        // Filter by action
        if (isset($criteria['action'])) {
            $query->where('action', $criteria['action']);
        }

        // Filter by document number
        if (isset($criteria['document_number'])) {
            $query->whereHas('documentRegistry', function ($q) use ($criteria) {
                $q->where('full_document_number', 'like', '%' . $criteria['document_number'] . '%');
            });
        }

        // Filter by IP address
        if (isset($criteria['ip_address'])) {
            $query->where('ip_address', $criteria['ip_address']);
        }

        // Filter by date range
        if (isset($criteria['date_from'])) {
            $query->where('performed_at', '>=', $criteria['date_from']);
        }
        if (isset($criteria['date_to'])) {
            $query->where('performed_at', '<=', $criteria['date_to']);
        }

        return $query->orderBy('performed_at', 'desc')->paginate(50);
    }
}