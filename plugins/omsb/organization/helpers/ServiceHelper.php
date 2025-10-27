<?php namespace Omsb\Organization\Helpers;

use Omsb\Organization\Models\ServiceSettings;
use ValidationException;

/**
 * Service Validation Helper
 * 
 * Provides business rules and validation logic for service-based operations
 */
class ServiceHelper
{
    /**
     * Validate that all items in a purchase request belong to the same service
     */
    public static function validatePurchaseRequestServiceConsistency($purchaseRequest, $items = null)
    {
        if (!$purchaseRequest->service_code) {
            throw new ValidationException(['service_code' => 'Purchase request must have a service assigned']);
        }

        if (!$items) {
            $items = $purchaseRequest->items; // Assuming hasMany relationship
        }

        $inconsistentItems = [];
        
        foreach ($items as $item) {
            if ($item->purchaseableItem && $item->purchaseableItem->service_code) {
                if ($item->purchaseableItem->service_code !== $purchaseRequest->service_code) {
                    $inconsistentItems[] = [
                        'item_code' => $item->purchaseableItem->code,
                        'item_name' => $item->purchaseableItem->name,
                        'item_service' => $item->purchaseableItem->service_code,
                        'expected_service' => $purchaseRequest->service_code
                    ];
                }
            }
        }

        if (!empty($inconsistentItems)) {
            $errorMessage = 'The following items do not match the purchase request service (' . 
                           ServiceSettings::getServiceName($purchaseRequest->service_code) . '):\n';
            
            foreach ($inconsistentItems as $item) {
                $errorMessage .= "• {$item['item_name']} (Expected: {$item['expected_service']}, Got: {$item['item_service']})\n";
            }

            throw new ValidationException(['items' => $errorMessage]);
        }

        return true;
    }

    /**
     * Get budget allocation options filtered by service
     */
    public static function getServiceBudgetOptions($serviceCode, $siteId = null)
    {
        if (!ServiceSettings::isValidServiceCode($serviceCode)) {
            return [];
        }

        // This would integrate with a Budget model when available
        // For now, return a placeholder structure
        return [
            'available_budgets' => [],
            'service_name' => ServiceSettings::getServiceName($serviceCode),
            'approval_threshold' => ServiceSettings::getApprovalThreshold($serviceCode)
        ];
    }

    /**
     * Check if staff member can approve for a specific service
     */
    public static function canStaffApproveForService($staffId, $serviceCode, $amount = 0)
    {
        $staff = \Omsb\Organization\Models\Staff::find($staffId);
        
        if (!$staff) {
            return false;
        }

        // Staff can only approve for their own service or if they have cross-service permissions
        if ($staff->service_code !== $serviceCode && !$staff->has_cross_service_approval) {
            return false;
        }

        // Check amount against service threshold
        $threshold = ServiceSettings::getApprovalThreshold($serviceCode);
        if ($amount > $threshold && !$staff->can_approve_above_threshold) {
            return false;
        }

        return true;
    }

    /**
     * Get filtered item options by service
     */
    public static function getServiceItemOptions($serviceCode, $activeOnly = true)
    {
        $query = \Omsb\Procurement\Models\PurchaseableItem::byService($serviceCode);
        
        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')
            ->get()
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get staff options filtered by service
     */
    public static function getServiceStaffOptions($serviceCode, $activeOnly = true)
    {
        $query = \Omsb\Organization\Models\Staff::byService($serviceCode);
        
        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('first_name')
            ->get()
            ->pluck('full_name', 'id')
            ->toArray();
    }

    /**
     * Validate service assignment consistency
     */
    public static function validateServiceAssignment($entityType, $entityId, $serviceCode)
    {
        if (!ServiceSettings::isValidServiceCode($serviceCode)) {
            throw new ValidationException(['service_code' => 'Invalid service code: ' . $serviceCode]);
        }

        switch ($entityType) {
            case 'staff':
                return self::validateStaffServiceAssignment($entityId, $serviceCode);
            case 'purchase_request':
                return self::validatePurchaseRequestServiceAssignment($entityId, $serviceCode);
            case 'purchaseable_item':
                return self::validateItemServiceAssignment($entityId, $serviceCode);
            default:
                throw new ValidationException(['entity_type' => 'Unknown entity type: ' . $entityType]);
        }
    }

    /**
     * Validate staff service assignment
     */
    protected static function validateStaffServiceAssignment($staffId, $serviceCode)
    {
        // Staff can only be assigned to one service at a time
        // Additional business rules can be added here
        return true;
    }

    /**
     * Validate purchase request service assignment
     */
    protected static function validatePurchaseRequestServiceAssignment($prId, $serviceCode)
    {
        $pr = \Omsb\Procurement\Models\PurchaseRequest::find($prId);
        
        if (!$pr) {
            return true; // New record
        }

        // Check if requester belongs to the same service
        if ($pr->requestedBy && $pr->requestedBy->service_code) {
            if ($pr->requestedBy->service_code !== $serviceCode) {
                throw new ValidationException([
                    'service_code' => 'Purchase request service must match requester\'s service (' . 
                                    ServiceSettings::getServiceName($pr->requestedBy->service_code) . ')'
                ]);
            }
        }

        return true;
    }

    /**
     * Validate item service assignment
     */
    protected static function validateItemServiceAssignment($itemId, $serviceCode)
    {
        // Check if item is already used in other service contexts
        // This could check existing purchase orders, inventory allocations, etc.
        return true;
    }

    /**
     * Get service-based approval workflow
     */
    public static function getServiceApprovalWorkflow($serviceCode, $amount = 0)
    {
        $threshold = ServiceSettings::getApprovalThreshold($serviceCode);
        $workflow = [
            'service' => ServiceSettings::getServiceByCode($serviceCode),
            'requires_special_approval' => $amount > $threshold,
            'approval_levels' => []
        ];

        // Basic approval levels (can be enhanced with Workflow plugin integration)
        if ($amount <= 5000) {
            $workflow['approval_levels'] = ['supervisor'];
        } elseif ($amount <= $threshold) {
            $workflow['approval_levels'] = ['supervisor', 'manager'];
        } else {
            $workflow['approval_levels'] = ['supervisor', 'manager', 'service_head', 'finance_manager'];
        }

        return $workflow;
    }

    /**
     * Generate service-based document number modifier
     */
    public static function getServiceDocumentModifier($serviceCode, $documentType = null)
    {
        $service = ServiceSettings::getServiceByCode($serviceCode);
        
        if (!$service) {
            return '';
        }

        // Return service code as modifier for specific document types
        $serviceDocumentTypes = ['MRN', 'MRI', 'SA', 'ST'];
        
        if (in_array($documentType, $serviceDocumentTypes)) {
            return '(' . $serviceCode . ')';
        }

        return '';
    }

    /**
     * Get cross-service impact analysis
     */
    public static function getCrossServiceImpact($serviceCode, $action, $entityData = [])
    {
        $impact = [
            'affected_services' => [],
            'warnings' => [],
            'recommendations' => []
        ];

        // Analyze potential cross-service impacts
        // This can be expanded based on business requirements

        return $impact;
    }
}