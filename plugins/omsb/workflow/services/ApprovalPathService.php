<?php namespace Omsb\Workflow\Services;

use Omsb\Organization\Models\Approval;
use Omsb\Workflow\Models\WorkflowInstance;

/**
 * ApprovalPathService
 * 
 * Determines the sequence of approval rules required for a document
 * based on document type, amount, site, and other business criteria.
 */
class ApprovalPathService
{
    /**
     * Determine the approval path for a document
     * 
     * @param string $documentType
     * @param float $amount
     * @param int $siteId
     * @param array $documentAttributes
     * @return array Array of approval rule IDs in sequence
     */
    public function determineApprovalPath($documentType, $amount, $siteId, $documentAttributes = [])
    {
        $approvalPath = [];
        $currentAmount = $amount;
        $currentStatus = $documentAttributes['current_status'] ?? 'submitted';
        
        // Get all applicable approval rules for this document type and site
        $approvalRules = $this->getApplicableRules($documentType, $siteId, $documentAttributes);
        
        // Sort rules by floor_limit to create approval hierarchy
        $sortedRules = $approvalRules->sortBy('floor_limit');
        
        foreach ($sortedRules as $rule) {
            if ($this->ruleApplies($rule, $currentAmount, $currentStatus, $documentAttributes)) {
                $approvalPath[] = $rule->id;
                
                // Update current status for next rule evaluation
                $currentStatus = $rule->to_status;
                
                // If this rule has a ceiling limit and amount exceeds it,
                // continue to next approval level
                if ($rule->ceiling_limit && $currentAmount > $rule->ceiling_limit) {
                    continue;
                }
                
                // If amount is within this rule's authority, we can stop here
                // unless it's a multi-step process
                if (!$rule->ceiling_limit || $currentAmount <= $rule->ceiling_limit) {
                    // Check if there are mandatory subsequent approvals
                    $nextRule = $this->getNextMandatoryRule($rule, $documentType, $currentAmount);
                    if (!$nextRule) {
                        break;
                    }
                }
            }
        }
        
        return $approvalPath;
    }
    
    /**
     * Get applicable approval rules for document type and site
     */
    protected function getApplicableRules($documentType, $siteId, $documentAttributes)
    {
        $query = Approval::where('document_type', $documentType)
            ->where('is_active', true)
            ->where(function($q) use ($siteId) {
                $q->whereNull('site_id')
                  ->orWhere('site_id', $siteId);
            })
            ->where(function($q) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            });
            
        // Apply document-specific filters
        if (isset($documentAttributes['budget_type'])) {
            $query->where(function($q) use ($documentAttributes) {
                $q->where('budget_type', 'All')
                  ->orWhere('budget_type', $documentAttributes['budget_type']);
            });
        }
        
        if (isset($documentAttributes['transaction_category'])) {
            $query->where(function($q) use ($documentAttributes) {
                $q->whereNull('transaction_category')
                  ->orWhere('transaction_category', $documentAttributes['transaction_category']);
            });
        }
        
        return $query->get();
    }
    
    /**
     * Check if a specific rule applies to the current context
     */
    protected function ruleApplies($rule, $amount, $currentStatus, $documentAttributes)
    {
        // Check amount limits
        if ($rule->floor_limit && $amount < $rule->floor_limit) {
            return false;
        }
        
        // Check status transition (if specified)
        if ($rule->from_status && $rule->from_status !== $currentStatus) {
            return false;
        }
        
        // Check budget vs non-budget limits
        $isBudgeted = $documentAttributes['is_budgeted'] ?? true;
        
        if ($isBudgeted && $rule->budget_ceiling_limit) {
            return $amount <= $rule->budget_ceiling_limit;
        }
        
        if (!$isBudgeted && $rule->non_budget_ceiling_limit) {
            return $amount <= $rule->non_budget_ceiling_limit;
        }
        
        // Default ceiling limit check
        if ($rule->ceiling_limit) {
            return $amount <= $rule->ceiling_limit;
        }
        
        return true;
    }
    
    /**
     * Get next mandatory approval rule (for complex workflows)
     */
    protected function getNextMandatoryRule($currentRule, $documentType, $amount)
    {
        // Look for rules that must follow this one
        return Approval::where('document_type', $documentType)
            ->where('from_status', $currentRule->to_status)
            ->where('is_active', true)
            ->where(function($q) use ($amount) {
                $q->whereNull('floor_limit')
                  ->orWhere('floor_limit', '<=', $amount);
            })
            ->first();
    }
    
    /**
     * Create approval path for specific business scenarios
     */
    public function createPathForPurchaseOrder($purchaseOrder)
    {
        $documentAttributes = [
            'current_status' => $purchaseOrder->status,
            'budget_type' => $purchaseOrder->budget_type ?? 'Operating',
            'transaction_category' => $purchaseOrder->category ?? null,
            'is_budgeted' => $purchaseOrder->is_budgeted ?? true,
            'urgency' => $purchaseOrder->urgency ?? 'normal'
        ];
        
        return $this->determineApprovalPath(
            'purchase_order',
            $purchaseOrder->total_amount,
            $purchaseOrder->site_id,
            $documentAttributes
        );
    }
    
    /**
     * Create approval path for stock adjustment
     */
    public function createPathForStockAdjustment($stockAdjustment)
    {
        // High-value adjustments need multiple approvals
        $adjustmentValue = abs($stockAdjustment->total_value ?? 0);
        
        $documentAttributes = [
            'current_status' => $stockAdjustment->status,
            'adjustment_type' => $stockAdjustment->adjustment_type,
            'has_discrepancy' => $stockAdjustment->has_discrepancy ?? false
        ];
        
        return $this->determineApprovalPath(
            'stock_adjustment',
            $adjustmentValue,
            $stockAdjustment->site_id,
            $documentAttributes
        );
    }
    
    /**
     * Preview approval path without creating workflow
     * Useful for showing users what approvals will be required
     */
    public function previewApprovalPath($documentType, $amount, $siteId, $documentAttributes = [])
    {
        $approvalPath = $this->determineApprovalPath($documentType, $amount, $siteId, $documentAttributes);
        
        $preview = [];
        foreach ($approvalPath as $ruleId) {
            $rule = Approval::find($ruleId);
            if ($rule) {
                $preview[] = [
                    'step' => count($preview) + 1,
                    'rule_code' => $rule->code,
                    'approval_type' => $rule->approval_type,
                    'required_approvers' => $rule->required_approvers,
                    'eligible_approvers' => $rule->eligible_approvers,
                    'description' => $this->getApprovalDescription($rule),
                    'estimated_days' => $rule->approval_timeout_days ?? 3
                ];
            }
        }
        
        return $preview;
    }
    
    /**
     * Get human-readable description of approval rule
     */
    protected function getApprovalDescription($rule)
    {
        if ($rule->approval_type === 'single') {
            return "Requires approval from {$rule->staff->full_name}";
        }
        
        if ($rule->approval_type === 'quorum') {
            return "Requires {$rule->required_approvers} out of {$rule->eligible_approvers} approvals";
        }
        
        if ($rule->approval_type === 'majority') {
            return "Requires majority approval ({$rule->required_approvers}+ approvals)";
        }
        
        return "Custom approval requirement";
    }
}