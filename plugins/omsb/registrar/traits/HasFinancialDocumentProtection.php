<?php namespace Omsb\Registrar\Traits;

use ValidationException;

/**
 * HasFinancialDocumentProtection Trait
 * 
 * Enhanced protection trait for financial documents that require:
 * - Stricter audit controls
 * - Amount-based protection thresholds
 * - Multi-level approval tracking
 * - Enhanced fraud detection
 * 
 * Use this trait for documents like Purchase Orders, Invoices, Stock Adjustments
 * that have financial implications.
 */
trait HasFinancialDocumentProtection
{
    use HasControlledDocumentNumber;

    /**
     * Boot the financial protection trait
     */
    public static function bootHasFinancialDocumentProtection()
    {
        // Additional validation for financial documents
        static::creating(function ($model) {
            $model->validateFinancialRules();
        });

        static::updating(function ($model) {
            $model->validateFinancialChanges();
            $model->checkAmountThresholds();
        });

        // Log all financial document access
        static::retrieved(function ($model) {
            if ($model->shouldLogAccess()) {
                $model->logAccess('view');
            }
        });
    }

    /**
     * Check if document exceeds financial thresholds requiring extra protection
     */
    public function exceedsProtectionThreshold()
    {
        $amount = $this->getDocumentAmount();
        $threshold = $this->getProtectionThreshold();
        
        return $amount > $threshold;
    }

    /**
     * Get document amount for protection calculation
     */
    protected function getDocumentAmount()
    {
        // Try common amount field names
        $amountFields = ['total_amount', 'amount', 'value', 'total_value', 'adjustment_value'];
        
        foreach ($amountFields as $field) {
            if (isset($this->$field)) {
                return (float) $this->$field;
            }
        }
        
        return 0;
    }

    /**
     * Get protection threshold amount
     */
    protected function getProtectionThreshold()
    {
        // Default threshold - can be overridden in models
        return property_exists($this, 'protectionThreshold') ? $this->protectionThreshold : 50000;
    }

    /**
     * Validate financial rules on creation
     */
    protected function validateFinancialRules()
    {
        $amount = $this->getDocumentAmount();
        
        // Require explanation for high-value documents
        if ($amount > $this->getProtectionThreshold()) {
            if (empty($this->description) && empty($this->remarks)) {
                throw new ValidationException([
                    'description' => 'High-value financial documents require detailed description'
                ]);
            }
        }

        // Validate currency if present
        if (isset($this->currency) && !in_array($this->currency, ['MYR', 'USD', 'SGD'])) {
            throw new ValidationException([
                'currency' => 'Invalid currency code'
            ]);
        }
    }

    /**
     * Validate financial changes during updates
     */
    protected function validateFinancialChanges()
    {
        // Prevent amount changes after certain statuses
        if ($this->isDirty($this->getAmountField()) && !$this->canAmountBeChanged()) {
            throw new ValidationException([
                'amount' => 'Document amount cannot be changed in current status'
            ]);
        }

        // Log significant amount changes
        if ($this->isDirty($this->getAmountField())) {
            $oldAmount = $this->getOriginal($this->getAmountField());
            $newAmount = $this->getAttribute($this->getAmountField());
            $changePercentage = abs(($newAmount - $oldAmount) / $oldAmount * 100);
            
            if ($changePercentage > 10) { // More than 10% change
                $this->logSignificantAmountChange($oldAmount, $newAmount, $changePercentage);
            }
        }
    }

    /**
     * Get the primary amount field name
     */
    protected function getAmountField()
    {
        $amountFields = ['total_amount', 'amount', 'value', 'total_value', 'adjustment_value'];
        
        foreach ($amountFields as $field) {
            if ($this->hasAttribute($field)) {
                return $field;
            }
        }
        
        return 'amount'; // Default
    }

    /**
     * Check if amount can be changed in current status
     */
    protected function canAmountBeChanged()
    {
        $restrictedStatuses = property_exists($this, 'amountProtectedStatuses') 
            ? $this->amountProtectedStatuses 
            : ['approved', 'sent_to_vendor', 'posted_to_ledger', 'invoiced'];
            
        return !in_array($this->status, $restrictedStatuses);
    }

    /**
     * Check amount thresholds and apply additional protection
     */
    protected function checkAmountThresholds()
    {
        $amount = $this->getDocumentAmount();
        
        // Auto-lock high-value documents
        if ($amount > ($this->getProtectionThreshold() * 2)) {
            if ($this->status === 'approved' && $this->documentRegistry && !$this->documentRegistry->is_locked) {
                $this->lockDocument('Auto-locked: High-value financial document');
            }
        }
    }

    /**
     * Log significant amount changes
     */
    protected function logSignificantAmountChange($oldAmount, $newAmount, $changePercentage)
    {
        if ($this->documentRegistry) {
            $auditService = new \Omsb\Registrar\Services\DocumentAuditService();
            $auditService->logDocumentUpdate(
                $this->documentRegistry,
                ['amount_change_flag' => 'significant'],
                [
                    'old_amount' => $oldAmount,
                    'new_amount' => $newAmount,
                    'change_percentage' => round($changePercentage, 2),
                    'amount_change_flag' => 'significant'
                ],
                "Significant amount change: {$changePercentage}% variation"
            );
        }
    }

    /**
     * Check if access should be logged (high-value or sensitive documents)
     */
    protected function shouldLogAccess()
    {
        // Log access for high-value documents or voided documents
        return $this->exceedsProtectionThreshold() || 
               ($this->is_voided ?? false) ||
               in_array($this->status, ['voided', 'rejected']);
    }

    /**
     * Enhanced void function for financial documents
     */
    public function voidFinancialDocument($reason, $approvalRequired = true)
    {
        if ($approvalRequired && $this->exceedsProtectionThreshold()) {
            $currentUser = \Backend\Facades\BackendAuth::getUser();
            
            // Check if user has authority to void high-value documents
            if (!$this->canUserVoidHighValueDocument($currentUser)) {
                throw new ValidationException([
                    'authorization' => 'High-value financial documents require supervisor approval for voiding'
                ]);
            }
        }

        // Additional financial validation
        if ($this->hasFinancialImpact()) {
            $reason = "FINANCIAL IMPACT: " . $reason;
        }

        return $this->voidDocument($reason);
    }

    /**
     * Check if user can void high-value documents
     */
    protected function canUserVoidHighValueDocument($user)
    {
        if (!$user) {
            return false;
        }

        // Check user role/permissions (implement based on your auth system)
        $authorizedRoles = ['cfo', 'finance_manager', 'ceo', 'finance_admin'];
        
        return $user->hasAnyRole($authorizedRoles) ?? false;
    }

    /**
     * Check if document has financial impact
     */
    protected function hasFinancialImpact()
    {
        // Check if document affects inventory, GL accounts, or payment obligations
        $financialStatuses = ['approved', 'sent_to_vendor', 'posted_to_ledger', 'invoiced', 'paid'];
        
        return in_array($this->status, $financialStatuses);
    }

    /**
     * Generate financial compliance report for this document
     */
    public function generateFinancialComplianceReport()
    {
        $auditHistory = $this->getAuditHistory();
        
        return [
            'document_number' => $this->document_number,
            'document_type' => $this->documentTypeCode ?? 'Unknown',
            'current_amount' => $this->getDocumentAmount(),
            'exceeds_threshold' => $this->exceedsProtectionThreshold(),
            'financial_impact' => $this->hasFinancialImpact(),
            'protection_level' => $this->getFinancialProtectionLevel(),
            'amount_changes' => $this->getAmountChangeHistory($auditHistory),
            'approval_trail' => $this->getApprovalTrail($auditHistory),
            'access_log' => $auditHistory->where('action', 'access')->count(),
            'compliance_score' => $this->calculateComplianceScore($auditHistory)
        ];
    }

    /**
     * Get financial protection level
     */
    protected function getFinancialProtectionLevel()
    {
        $amount = $this->getDocumentAmount();
        $threshold = $this->getProtectionThreshold();
        
        if ($amount < $threshold * 0.1) return 'Low';
        if ($amount < $threshold) return 'Medium';
        if ($amount < $threshold * 2) return 'High';
        return 'Critical';
    }

    /**
     * Get amount change history from audit trail
     */
    protected function getAmountChangeHistory($auditHistory)
    {
        return $auditHistory->filter(function ($entry) {
            $newValues = is_array($entry->new_values) ? $entry->new_values : json_decode($entry->new_values, true);
            return isset($newValues['amount_change_flag']);
        })->map(function ($entry) {
            $newValues = is_array($entry->new_values) ? $entry->new_values : json_decode($entry->new_values, true);
            return [
                'date' => $entry->performed_at,
                'user' => $entry->performer?->full_name,
                'old_amount' => $newValues['old_amount'] ?? null,
                'new_amount' => $newValues['new_amount'] ?? null,
                'change_percentage' => $newValues['change_percentage'] ?? null
            ];
        });
    }

    /**
     * Get approval trail for financial document
     */
    protected function getApprovalTrail($auditHistory)
    {
        return $auditHistory->where('action', 'status_change')
            ->map(function ($entry) {
                $newValues = is_array($entry->new_values) ? $entry->new_values : json_decode($entry->new_values, true);
                return [
                    'date' => $entry->performed_at,
                    'user' => $entry->performer?->full_name,
                    'status_change' => $newValues['status'] ?? 'Unknown',
                    'reason' => $entry->reason
                ];
            });
    }

    /**
     * Calculate compliance score based on audit trail
     */
    protected function calculateComplianceScore($auditHistory)
    {
        $score = 100;
        
        // Deduct points for compliance issues
        $amountChanges = $this->getAmountChangeHistory($auditHistory)->count();
        $score -= ($amountChanges * 5); // -5 points per amount change
        
        $afterHoursAccess = $auditHistory->filter(function ($entry) {
            $hour = $entry->performed_at->hour;
            return $hour < 7 || $hour > 19;
        })->count();
        $score -= ($afterHoursAccess * 2); // -2 points per after-hours access
        
        // Minimum score is 0
        return max(0, $score);
    }
}