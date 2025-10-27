<?php namespace Omsb\Registrar\Updates;

use Omsb\Registrar\Models\DocumentType;
use October\Rain\Database\Updates\Seeder;

/**
 * SeedDocumentTypesTable Seeder
 * 
 * Seeds the system with common document types used across OMSB plugins.
 * These represent the standard controlled documents that require numbering and audit trails.
 */
class SeedDocumentTypesTable extends Seeder
{
    public function run()
    {
        // Purchase Request
        DocumentType::create([
            'code' => 'PR',
            'name' => 'Purchase Request',
            'description' => 'Purchase requisition documents initiated by departments for procurement approval',
            'numbering_pattern' => '{SITE}-PR-{YYYY}-{######}',
            'reset_cycle' => 'yearly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'approved',
            'void_only_statuses' => ['approved', 'converted_to_po'],
            'is_active' => true
        ]);

        // Purchase Order
        DocumentType::create([
            'code' => 'PO',
            'name' => 'Purchase Order',
            'description' => 'Official purchase orders sent to vendors for goods and services',
            'numbering_pattern' => '{SITE}-PO-{YYYY}-{######}',
            'reset_cycle' => 'yearly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => true,
            'modifier_separator' => '(',
            'modifier_options' => [
                'SBW' => 'Spawn - Bulk purchase with site-wise delivery',
                'RUSH' => 'Urgent delivery required',
                'PARTIAL' => 'Partial delivery allowed'
            ],
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'sent_to_vendor',
            'void_only_statuses' => ['sent_to_vendor', 'acknowledged', 'delivered'],
            'is_active' => true
        ]);

        // Material Received Note
        DocumentType::create([
            'code' => 'MRN',
            'name' => 'Material Received Note',
            'description' => 'Goods receipt documentation for inventory items received at warehouses',
            'numbering_pattern' => '{SITE}-MRN-{YYYY}-{######}',
            'reset_cycle' => 'yearly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'posted_to_inventory',
            'void_only_statuses' => ['posted_to_inventory'],
            'is_active' => true
        ]);

        // Material Request Issuance
        DocumentType::create([
            'code' => 'MRI',
            'name' => 'Material Request Issuance',
            'description' => 'Material issuance requests for internal consumption and projects',
            'numbering_pattern' => '{SITE}-MRI-{YYYY}-{######}',
            'reset_cycle' => 'yearly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'issued',
            'void_only_statuses' => ['issued'],
            'is_active' => true
        ]);

        // Stock Adjustment
        DocumentType::create([
            'code' => 'SA',
            'name' => 'Stock Adjustment',
            'description' => 'Inventory adjustments for physical count variances and corrections',
            'numbering_pattern' => '{SITE}-SA-{YYYY}-{MM}-{######}',
            'reset_cycle' => 'monthly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => true,
            'modifier_separator' => '(',
            'modifier_options' => [
                'A' => 'Appendix - Supporting documentation attached',
                'C' => 'Correction - Amendment to previous adjustment',
                'PC' => 'Physical Count - Annual/cycle count variance'
            ],
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => true,
            'protect_after_status' => 'approved',
            'void_only_statuses' => ['approved', 'posted_to_ledger'],
            'is_active' => true
        ]);

        // Stock Transfer
        DocumentType::create([
            'code' => 'ST',
            'name' => 'Stock Transfer',
            'description' => 'Inter-warehouse and inter-site stock transfer documentation',
            'numbering_pattern' => '{SITE}-ST-{YYYY}-{######}',
            'reset_cycle' => 'yearly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'transferred',
            'void_only_statuses' => ['transferred', 'received'],
            'is_active' => true
        ]);

        // Physical Count
        DocumentType::create([
            'code' => 'PC',
            'name' => 'Physical Count',
            'description' => 'Inventory physical count sheets and cycle count documentation',
            'numbering_pattern' => '{SITE}-PC-{YYYY}-{MM}-{######}',
            'reset_cycle' => 'monthly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 4,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => true,
            'protect_after_status' => 'counted',
            'void_only_statuses' => ['counted', 'variance_recorded'],
            'is_active' => true
        ]);

        // Delivery Order (Non-inventory items)
        DocumentType::create([
            'code' => 'DO',
            'name' => 'Delivery Order',
            'description' => 'Delivery documentation for non-inventory items and services',
            'numbering_pattern' => '{SITE}-DO-{YYYY}-{######}',
            'reset_cycle' => 'yearly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'delivered',
            'void_only_statuses' => ['delivered', 'invoiced'],
            'is_active' => true
        ]);

        // Vendor Quotation
        DocumentType::create([
            'code' => 'VQ',
            'name' => 'Vendor Quotation',
            'description' => 'Vendor quotation requests and responses for procurement evaluation',
            'numbering_pattern' => '{SITE}-VQ-{YYYY}-{######}',
            'reset_cycle' => 'yearly',
            'starting_number' => 1,
            'current_number' => 1,
            'number_length' => 5,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => true,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'evaluated',
            'void_only_statuses' => ['evaluated', 'converted_to_po'],
            'is_active' => true
        ]);

        // Special: Running number example with high starting number
        DocumentType::create([
            'code' => 'INV',
            'name' => 'Invoice',
            'description' => 'System-generated invoice numbers with running sequence',
            'numbering_pattern' => 'INV-{YYYY}-{########}',
            'reset_cycle' => 'yearly',
            'starting_number' => 900001,
            'current_number' => 900001,
            'number_length' => 8,
            'increment_by' => 1,
            'supports_modifiers' => false,
            'requires_site_code' => false,
            'requires_year' => true,
            'requires_month' => false,
            'protect_after_status' => 'issued',
            'void_only_statuses' => ['issued', 'paid'],
            'is_active' => true
        ]);
    }
}