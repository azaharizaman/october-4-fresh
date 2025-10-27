<?php namespace Omsb\Registrar\Traits;

use Omsb\Registrar\Models\DocumentRegistry;
use Omsb\Registrar\Services\DocumentNumberingService;
use Omsb\Registrar\Services\DocumentAuditService;
use ValidationException;

/**
 * HasControlledDocumentNumber Trait
 * 
 * Provides controlled document numbering and protection capabilities to any model.
 * Models using this trait automatically get:
 * - Controlled document number generation
 * - Edit protection based on status
 * - Complete audit trails
 * - Void handling instead of deletion
 * 
 * Usage in models:
 * use \Omsb\Registrar\Traits\HasControlledDocumentNumber;
 * 
 * Required model properties:
 * - $documentTypeCode (string) - Document type code for numbering
 * - $protectedStatuses (array) - Statuses that prevent editing
 */
trait HasControlledDocumentNumber
{
    /**
     * Boot the trait
     */
    public static function bootHasControlledDocumentNumber()
    {
        // Auto-generate document number on creation
        static::creating(function ($model) {
            if (!$model->document_number) {
                $model->generateDocumentNumber();
            }
        });

        // Create registry entry after creation
        static::created(function ($model) {
            $model->createRegistryEntry();
        });

        // Track updates
        static::updating(function ($model) {
            $model->validateEditPermission();
            $model->trackDocumentChanges();
        });

        // Prevent deletion, require voiding instead
        static::deleting(function ($model) {
            if (!$model->is_being_voided) {
                throw new ValidationException([
                    'document' => 'Controlled documents cannot be deleted. Use void operation instead.'
                ]);
            }
        });
    }

    /**
     * Relationship to document registry
     */
    public function documentRegistry()
    {
        return $this->morphOne(DocumentRegistry::class, 'documentable');
    }

    /**
     * Generate document number
     */
    public function generateDocumentNumber($siteCode = null, $modifiers = [])
    {
        if (!property_exists($this, 'documentTypeCode')) {
            throw new \Exception('Model must define $documentTypeCode property');
        }

        $numberingService = new DocumentNumberingService();
        
        // Use site from model or parameter
        $siteCode = $siteCode ?: $this->getSiteCode();
        
        $result = $numberingService->generateDocumentNumber(
            $this->documentTypeCode,
            $siteCode,
            $modifiers,
            [
                'documentable_type' => get_class($this),
                'documentable_id' => $this->id ?: 0,
                'initial_status' => $this->status ?? 'draft'
            ]
        );

        $this->document_number = $result['document_number'];
        $this->registry_id = $result['registry_id'];

        return $result;
    }

    /**
     * Create registry entry linking document to numbering system
     */
    protected function createRegistryEntry()
    {
        if ($this->registry_id) {
            $registry = DocumentRegistry::find($this->registry_id);
            if ($registry) {
                $registry->update([
                    'documentable_type' => get_class($this),
                    'documentable_id' => $this->id
                ]);

                // Log creation
                $auditService = new DocumentAuditService();
                $auditService->logDocumentCreation($registry);
            }
        }
    }

    /**
     * Get site code for numbering
     */
    protected function getSiteCode()
    {
        // Try multiple common site relationship patterns
        if ($this->site && $this->site->code) {
            return $this->site->code;
        }
        
        if ($this->site_code) {
            return $this->site_code;
        }

        if ($this->site_id) {
            $site = \Omsb\Organization\Models\Site::find($this->site_id);
            return $site?->code;
        }

        return 'HQ'; // Default fallback
    }

    /**
     * Check if document can be edited
     */
    public function canBeEdited()
    {
        // Check if voided
        if ($this->is_voided ?? false) {
            return false;
        }

        // Check registry lock status
        if ($this->documentRegistry && $this->documentRegistry->is_locked) {
            return false;
        }

        // Check protected statuses
        if (property_exists($this, 'protectedStatuses')) {
            return !in_array($this->status, $this->protectedStatuses);
        }

        // Check document type rules
        if ($this->documentRegistry && $this->documentRegistry->documentType) {
            return $this->documentRegistry->documentType->allowsEditingAtStatus($this->status);
        }

        return true;
    }

    /**
     * Validate edit permission before update
     */
    protected function validateEditPermission()
    {
        if (!$this->canBeEdited()) {
            throw new ValidationException([
                'document' => "Document {$this->document_number} cannot be edited in its current state."
            ]);
        }
    }

    /**
     * Track document changes for audit
     */
    protected function trackDocumentChanges()
    {
        if ($this->isDirty() && $this->documentRegistry) {
            $auditService = new DocumentAuditService();
            $auditService->logDocumentUpdate(
                $this->documentRegistry,
                $this->getOriginal(),
                $this->getDirty(),
                'Document updated'
            );
        }
    }

    /**
     * Lock document to prevent editing
     */
    public function lockDocument($reason = null)
    {
        if (!$this->documentRegistry) {
            throw new \Exception('Document must have registry entry to be locked');
        }

        return $this->documentRegistry->lockDocument($reason);
    }

    /**
     * Unlock document (admin only)
     */
    public function unlockDocument($reason = null)
    {
        if (!$this->documentRegistry) {
            throw new \Exception('Document registry not found');
        }

        return $this->documentRegistry->unlockDocument($reason);
    }

    /**
     * Void document instead of deleting
     */
    public function voidDocument($reason)
    {
        if (empty($reason)) {
            throw new ValidationException(['void_reason' => 'Void reason is required']);
        }

        // Mark model as voided
        $this->is_voided = true;
        $this->voided_at = now();
        $this->voided_by = \Backend\Facades\BackendAuth::getUser()?->id;
        $this->void_reason = $reason;
        $this->previous_status = $this->status;
        $this->status = 'voided';

        // Mark flag to allow deletion if needed
        $this->is_being_voided = true;

        $this->save();

        // Void registry entry
        if ($this->documentRegistry) {
            $this->documentRegistry->voidDocument($reason);
        }

        return true;
    }

    /**
     * Get document audit history
     */
    public function getAuditHistory()
    {
        if (!$this->documentRegistry) {
            return collect();
        }

        $auditService = new DocumentAuditService();
        return $auditService->getDocumentAuditHistory($this->documentRegistry->id);
    }

    /**
     * Log document access
     */
    public function logAccess($accessType = 'view')
    {
        if ($this->documentRegistry) {
            $auditService = new DocumentAuditService();
            $auditService->logDocumentAccess($this->documentRegistry, $accessType);
        }
    }

    /**
     * Log document printing
     */
    public function logPrint($format = 'PDF', $recipient = null)
    {
        if ($this->documentRegistry) {
            $auditService = new DocumentAuditService();
            $auditService->logDocumentPrint($this->documentRegistry, $format, $recipient);
        }
    }

    /**
     * Update status with audit trail
     */
    public function updateStatus($newStatus, $reason = null)
    {
        if ($this->status === $newStatus) {
            return true;
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->save();

        // Log status change
        if ($this->documentRegistry) {
            $auditService = new DocumentAuditService();
            $auditService->logStatusChange($this->documentRegistry, $oldStatus, $newStatus, $reason);
        }

        return true;
    }

    /**
     * Get document protection summary
     */
    public function getProtectionSummary()
    {
        return [
            'document_number' => $this->document_number,
            'current_status' => $this->status,
            'can_edit' => $this->canBeEdited(),
            'is_locked' => $this->documentRegistry?->is_locked ?? false,
            'is_voided' => $this->is_voided ?? false,
            'protection_reason' => $this->getProtectionReason()
        ];
    }

    /**
     * Get reason why document is protected
     */
    protected function getProtectionReason()
    {
        if ($this->is_voided ?? false) {
            return 'Document has been voided';
        }

        if ($this->documentRegistry?->is_locked) {
            return $this->documentRegistry->lock_reason ?: 'Document is locked';
        }

        if (!$this->canBeEdited()) {
            return "Document status '{$this->status}' prevents editing";
        }

        return null;
    }

    /**
     * Scope for non-voided documents
     */
    public function scopeActive($query)
    {
        return $query->where('is_voided', '!=', true)->orWhereNull('is_voided');
    }

    /**
     * Scope for voided documents
     */
    public function scopeVoided($query)
    {
        return $query->where('is_voided', true);
    }

    /**
     * Scope for editable documents
     */
    public function scopeEditable($query)
    {
        $protectedStatuses = property_exists($this, 'protectedStatuses') ? $this->protectedStatuses : [];
        
        return $query->active()
            ->whereNotIn('status', $protectedStatuses)
            ->whereDoesntHave('documentRegistry', function ($q) {
                $q->where('is_locked', true);
            });
    }
}