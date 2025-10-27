<?php namespace Omsb\Workflow\Services;

use Omsb\Workflow\Models\WorkflowInstance;
use Omsb\Organization\Models\Approval;
use Backend\Facades\BackendAuth;

/**
 * WorkflowService
 * 
 * Main service for creating and managing workflow instances.
 * Automatically determines approval paths and creates workflows.
 */
class WorkflowService
{
    protected $approvalPathService;
    
    public function __construct()
    {
        $this->approvalPathService = new ApprovalPathService();
    }
    
    /**
     * Start approval workflow for any document
     * 
     * @param mixed $document The document needing approval
     * @param string $documentType Type identifier (purchase_order, stock_adjustment, etc.)
     * @param array $options Additional options for workflow creation
     * @return WorkflowInstance
     */
    public function startWorkflow($document, $documentType, $options = [])
    {
        // Determine approval path automatically
        $approvalPath = $this->determineApprovalPath($document, $documentType, $options);
        
        if (empty($approvalPath)) {
            throw new \Exception("No approval path found for {$documentType} with amount {$document->total_amount}");
        }
        
        // Get first approval rule
        $firstRule = Approval::find($approvalPath[0]);
        
        // Generate unique workflow code
        $workflowCode = $this->generateWorkflowCode($documentType, $document);
        
        // Create workflow instance
        $workflow = WorkflowInstance::create([
            'workflow_code' => $workflowCode,
            'status' => 'pending',
            'document_type' => $documentType,
            'documentable_type' => get_class($document),
            'documentable_id' => $document->id,
            'document_amount' => $this->getDocumentAmount($document),
            'current_step' => $this->getStepName($firstRule),
            'total_steps_required' => count($approvalPath),
            'steps_completed' => 0,
            'approval_path' => $approvalPath,
            'current_approval_rule_id' => $firstRule->id,
            'current_approval_type' => $firstRule->approval_type,
            'approvals_required' => $firstRule->required_approvers,
            'approvals_received' => 0,
            'rejections_received' => 0,
            'started_at' => now(),
            'due_at' => $this->calculateDueDate($firstRule),
            'site_id' => $document->site_id ?? null,
            'created_by' => BackendAuth::getUser()?->id,
            'workflow_notes' => $options['notes'] ?? null,
            'metadata' => $options['metadata'] ?? null
        ]);
        
        // Update document status to indicate it's in approval
        $this->updateDocumentStatus($document, $firstRule->from_status ?: 'pending_approval');
        
        // Send notifications to approvers
        $this->notifyApprovers($workflow, $firstRule);
        
        return $workflow;
    }
    
    /**
     * Determine approval path for document
     */
    protected function determineApprovalPath($document, $documentType, $options)
    {
        $documentAmount = $this->getDocumentAmount($document);
        $siteId = $document->site_id ?? null;
        
        // Build document attributes for approval routing
        $documentAttributes = array_merge([
            'current_status' => $document->status ?? 'draft',
            'budget_type' => $document->budget_type ?? 'Operating',
            'transaction_category' => $document->category ?? null,
            'is_budgeted' => $document->is_budgeted ?? true,
            'urgency' => $document->urgency ?? 'normal',
            'created_by' => $document->created_by ?? BackendAuth::getUser()?->id
        ], $options['document_attributes'] ?? []);
        
        return $this->approvalPathService->determineApprovalPath(
            $documentType,
            $documentAmount,
            $siteId,
            $documentAttributes
        );
    }
    
    /**
     * Start workflow for Purchase Order
     */
    public function startPurchaseOrderWorkflow($purchaseOrder, $options = [])
    {
        return $this->startWorkflow($purchaseOrder, 'purchase_order', $options);
    }
    
    /**
     * Start workflow for Stock Adjustment
     */
    public function startStockAdjustmentWorkflow($stockAdjustment, $options = [])
    {
        return $this->startWorkflow($stockAdjustment, 'stock_adjustment', $options);
    }
    
    /**
     * Preview approval path without starting workflow
     */
    public function previewWorkflow($document, $documentType, $options = [])
    {
        $documentAmount = $this->getDocumentAmount($document);
        $siteId = $document->site_id ?? null;
        
        $documentAttributes = array_merge([
            'current_status' => $document->status ?? 'draft',
            'budget_type' => $document->budget_type ?? 'Operating',
            'transaction_category' => $document->category ?? null,
            'is_budgeted' => $document->is_budgeted ?? true
        ], $options['document_attributes'] ?? []);
        
        return $this->approvalPathService->previewApprovalPath(
            $documentType,
            $documentAmount,
            $siteId,
            $documentAttributes
        );
    }
    
    /**
     * Get document amount for approval routing
     */
    protected function getDocumentAmount($document)
    {
        // Try common amount field names
        $amountFields = ['total_amount', 'amount', 'value', 'total_value', 'cost', 'total_cost'];
        
        foreach ($amountFields as $field) {
            if (isset($document->$field)) {
                return (float) $document->$field;
            }
        }
        
        return 0;
    }
    
    /**
     * Generate unique workflow code
     */
    protected function generateWorkflowCode($documentType, $document)
    {
        $prefix = strtoupper(substr($documentType, 0, 3));
        $year = date('Y');
        $month = date('m');
        
        // Try to use document number if available
        $documentNumber = $document->document_number ?? 
                         $document->po_number ?? 
                         $document->number ?? 
                         $document->id;
        
        return "WF-{$prefix}-{$year}{$month}-{$documentNumber}";
    }
    
    /**
     * Get step name for approval rule
     */
    protected function getStepName($approvalRule)
    {
        if ($approvalRule->approval_type === 'single') {
            $staffName = $approvalRule->staff->full_name ?? 'Assigned Staff';
            return "Approval by {$staffName}";
        }
        
        if ($approvalRule->approval_type === 'quorum') {
            return "Quorum Approval ({$approvalRule->required_approvers} of {$approvalRule->eligible_approvers})";
        }
        
        return "Approval Step: {$approvalRule->code}";
    }
    
    /**
     * Calculate due date for approval step
     */
    protected function calculateDueDate($approvalRule)
    {
        $days = $approvalRule->approval_timeout_days ?? 3;
        return now()->addDays($days);
    }
    
    /**
     * Update document status when workflow starts
     */
    protected function updateDocumentStatus($document, $newStatus)
    {
        $document->update(['status' => $newStatus]);
    }
    
    /**
     * Notify approvers of pending approval
     */
    protected function notifyApprovers($workflow, $approvalRule)
    {
        // This would integrate with notification system
        // For now, just log the notification requirement
        \Log::info("Workflow {$workflow->workflow_code} requires approval", [
            'rule_code' => $approvalRule->code,
            'document_type' => $workflow->document_type,
            'amount' => $workflow->document_amount
        ]);
        
        // TODO: Implement actual notification dispatch
        // - Email notifications
        // - In-app notifications  
        // - Mobile push notifications
        // - Slack/Teams integration
    }
}