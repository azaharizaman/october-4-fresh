# Registrar Plugin

## Overview

The Registrar plugin is the **audit and fraud prevention backbone** of the OMSB system. It provides controlled document numbering, immutable audit trails, and document protection mechanisms essential for financial and operational compliance.

### Primary Purpose

**Controlled Document Management**: Ensures all business-critical documents have:
- Unique, non-reusable document numbers
- Complete audit trails from creation to destruction
- Protection against unauthorized editing after critical states
- Fraud prevention through database-level constraints

### Key Business Requirements Addressed

1. **Audit Compliance**: Every document action tracked with user, timestamp, and reason
2. **Fraud Prevention**: Document numbers cannot be duplicated or reused
3. **Edit Protection**: Documents locked after reaching certain states (e.g., after inventory posting)
4. **Number Sequencing**: Controlled numbering with site codes, yearly resets, and custom patterns
5. **Void Management**: Proper voiding process instead of deletion for audit trail preservation

---

## Architecture Overview

### Three-Core Model System

| Model | Purpose | Key Features |
|-------|---------|--------------|
| **DocumentType** | Configuration | Numbering patterns, reset cycles, protection rules |
| **DocumentRegistry** | Central registry | All issued document numbers, status tracking, protection state |
| **DocumentAuditTrail** | Audit logging | Every action logged with forensic-level detail |

### Integration Pattern

```php
// In any model that needs controlled numbering
use \Omsb\Registrar\Traits\HasControlledDocumentNumber;

class PurchaseOrder extends Model
{
    use HasControlledDocumentNumber;
    
    protected $documentTypeCode = 'PO';
    protected $protectedStatuses = ['sent_to_vendor', 'delivered'];
}
```

---

## Business Logic and Document Lifecycle

### Document Number Generation

**Pattern Examples**:
- `HQ-PO-2025-00123` - Purchase Order from HQ, 2025, sequence 123
- `LBG-SA-2025-10-00045(A)` - Stock Adjustment from Limbang, October 2025, with appendix modifier
- `BTU-MRN-2025-00889` - Material Received Note from Batu site
- `INV-2025-00900123` - System invoice with high starting number (900xxx range)

**Numbering Rules**:
1. **Site Codes**: Each site has unique code (HQ, LBG, BTU, etc.)
2. **Document Codes**: Each document type has unique code (PO, SA, MRN, etc.)
3. **Year Component**: Most documents include year, some include month
4. **Sequence Numbers**: Zero-padded, site-specific or global depending on configuration
5. **Modifiers**: Optional suffixes like (A) for appendix, (SBW) for spawn documents

### Document Protection States

**Edit Protection Levels**:
1. **Draft**: Fully editable
2. **Submitted**: Limited editing (basic fields only)
3. **Approved**: Protected status - critical fields locked
4. **Posted/Ledgered**: No editing allowed - void only
5. **Voided**: Immutable - preserved for audit

**Example Protection Flow**:
```php
// MRN (Material Received Note) protection example
$mrn = new MaterialReceivedNote();
$mrn->status = 'draft';           // Can edit everything
$mrn->status = 'submitted';       // Can edit quantities, not items
$mrn->status = 'approved';        // Can edit remarks only  
$mrn->status = 'posted_to_inventory'; // Cannot edit - void only

// Attempting to edit protected document
$mrn->quantity = 50; // Throws ValidationException
$mrn->save();
```

### Audit Trail Requirements

**What Gets Logged**:
- Document creation with initial values
- Every field change (before/after values)
- Status transitions with reasons
- Lock/unlock operations
- Void operations with justification
- Document access for high-value items
- Print/export activities

**Audit Metadata**:
- User ID and full name
- Exact timestamp
- IP address and user agent
- Session information
- Business context (workflow actions, etc.)

---

## Document Types and Numbering Patterns

### Standard Document Types

| Code | Document Type | Pattern | Reset Cycle | Protected After |
|------|---------------|---------|-------------|----------------|
| **PR** | Purchase Request | `{SITE}-PR-{YYYY}-{#####}` | Yearly | `approved` |
| **PO** | Purchase Order | `{SITE}-PO-{YYYY}-{#####}` | Yearly | `sent_to_vendor` |
| **MRN** | Material Received Note | `{SITE}-MRN-{YYYY}-{#####}` | Yearly | `posted_to_inventory` |
| **MRI** | Material Request Issuance | `{SITE}-MRI-{YYYY}-{#####}` | Yearly | `issued` |
| **SA** | Stock Adjustment | `{SITE}-SA-{YYYY}-{MM}-{#####}` | Monthly | `approved` |
| **ST** | Stock Transfer | `{SITE}-ST-{YYYY}-{#####}` | Yearly | `transferred` |
| **PC** | Physical Count | `{SITE}-PC-{YYYY}-{MM}-{####}` | Monthly | `counted` |
| **DO** | Delivery Order | `{SITE}-DO-{YYYY}-{#####}` | Yearly | `delivered` |
| **VQ** | Vendor Quotation | `{SITE}-VQ-{YYYY}-{#####}` | Yearly | `evaluated` |
| **INV** | Invoice | `INV-{YYYY}-{########}` | Yearly | `issued` |

### Modifier Support

**Common Modifiers**:
- `(A)` - Appendix attached
- `(SBW)` - Spawn document (bulk purchase with site-wise breakdown)
- `(RUSH)` - Urgent processing required
- `(C)` - Correction to previous document
- `(PC)` - Physical count variance

**Modifier Examples**:
```php
// Purchase Order with spawn modifier
$poNumber = 'HQ-PO-2025-00156(SBW)';

// Stock Adjustment with appendix
$saNumber = 'LBG-SA-2025-10-00023(A)';

// Multiple modifiers (if supported)
$complexNumber = 'HQ-PO-2025-00200(RUSH)(PARTIAL)';
```

---

## Integration Guide

### Step 1: Add Trait to Model

```php
<?php namespace Omsb\Procurement\Models;

use Model;
use \Omsb\Registrar\Traits\HasControlledDocumentNumber;

class PurchaseOrder extends Model
{
    use HasControlledDocumentNumber;
    
    // Required: Document type configuration
    protected $documentTypeCode = 'PO';
    
    // Optional: Custom protection statuses
    protected $protectedStatuses = ['sent_to_vendor', 'delivered', 'invoiced'];
    
    // Required model fields for integration
    protected $fillable = [
        'document_number',    // Will store generated number
        'status',            // Document status for protection logic
        'site_id',           // For site-specific numbering
        'is_voided',         // Void flag
        'voided_at',         // Void timestamp
        'voided_by',         // Void user
        'void_reason'        // Void justification
        // ... other business fields
    ];
}
```

### Step 2: Add Database Fields

```php
// In your model's migration
Schema::table('purchase_orders', function (Blueprint $table) {
    // Registrar integration fields
    $table->string('document_number')->unique()->nullable();
    $table->unsignedBigInteger('registry_id')->nullable();
    $table->boolean('is_voided')->default(false);
    $table->timestamp('voided_at')->nullable();
    $table->unsignedInteger('voided_by')->nullable();
    $table->text('void_reason')->nullable();
    
    // Foreign keys
    $table->foreign('registry_id')->references('id')->on('omsb_registrar_document_registries')->nullOnDelete();
    $table->foreign('voided_by')->references('id')->on('backend_users')->nullOnDelete();
});
```

### Step 3: Use in Controllers

```php
<?php namespace Omsb\Procurement\Controllers;

use Backend\Classes\Controller;
use Omsb\Procurement\Models\PurchaseOrder;
use Omsb\Registrar\Helpers\RegistrarHelper;

class PurchaseOrderController extends Controller
{
    public function store()
    {
        // Create document with auto-generated number
        $po = PurchaseOrder::create([
            'vendor_id' => post('vendor_id'),
            'total_amount' => post('total_amount'),
            'site_id' => post('site_id'),
            'status' => 'draft'
            // document_number generated automatically via trait
        ]);
        
        Flash::success("Purchase Order {$po->document_number} created successfully");
        return redirect()->back();
    }
    
    public function update($id)
    {
        $po = PurchaseOrder::find($id);
        
        // Check edit permission
        if (!$po->canBeEdited()) {
            Flash::error("Cannot edit PO {$po->document_number} in status '{$po->status}'");
            return redirect()->back();
        }
        
        $po->update(post());
        // Audit trail automatically logged via trait
        
        Flash::success("Purchase Order updated");
        return redirect()->back();
    }
    
    public function void($id)
    {
        $po = PurchaseOrder::find($id);
        $reason = post('void_reason');
        
        try {
            $po->voidDocument($reason);
            Flash::success("Purchase Order {$po->document_number} voided successfully");
        } catch (ValidationException $e) {
            Flash::error($e->getMessage());
        }
        
        return redirect()->back();
    }
    
    public function auditHistory($id)
    {
        $po = PurchaseOrder::find($id);
        $auditHistory = $po->getAuditHistory();
        
        $this->vars['po'] = $po;
        $this->vars['auditHistory'] = $auditHistory;
    }
}
```

### Step 4: Financial Document Integration

For financial documents (Purchase Orders, Stock Adjustments, etc.), use the enhanced trait:

```php
<?php namespace Omsb\Procurement\Models;

use Model;
use \Omsb\Registrar\Traits\HasFinancialDocumentProtection;

class PurchaseOrder extends Model
{
    use HasFinancialDocumentProtection;
    
    protected $documentTypeCode = 'PO';
    protected $protectionThreshold = 50000; // RM50,000 threshold
    protected $amountProtectedStatuses = ['sent_to_vendor', 'delivered'];
    
    // Amount field changes tracked automatically
    protected $fillable = ['total_amount', 'vendor_id', 'site_id', 'status'];
}
```

---

## Advanced Features

### Document Locking

```php
// Lock document to prevent editing
$document->lockDocument('Posted to inventory - no changes allowed');

// Check lock status
if ($document->documentRegistry->is_locked) {
    echo "Document locked: " . $document->documentRegistry->lock_reason;
}

// Admin unlock (requires special permissions)
$document->unlockDocument('Administrative override for correction');
```

### Sequence Integrity Checking

```php
// Check for missing numbers or sequence gaps
$integrity = RegistrarHelper::checkIntegrity('PO', 2025, $siteId);

if (!$integrity['valid']) {
    foreach ($integrity['errors'] as $error) {
        Log::warning("Sequence integrity issue", $error);
    }
}
```

### Compliance Reporting

```php
// Generate compliance report for audit
$report = RegistrarHelper::getComplianceReport('PO', '2025-01-01', '2025-12-31');

// Check for fraud indicators
$duplicates = RegistrarHelper::findDuplicateNumbers();
if (!empty($duplicates)) {
    Alert::critical('Duplicate document numbers detected', $duplicates);
}
```

### Bulk Number Generation

```php
// For system migration or bulk imports
$numberingService = new DocumentNumberingService();
$numbers = $numberingService->bulkGenerateNumbers('PO', 100, 'HQ');

foreach ($numbers as $numberData) {
    echo "Reserved: " . $numberData['document_number'];
}
```

---

## Fraud Prevention Mechanisms

### Database-Level Protection

1. **Unique Constraints**: Document numbers cannot be duplicated at database level
2. **Foreign Key Constraints**: Registry entries cannot be orphaned
3. **Soft Deletes**: No hard deletion of registry entries
4. **Immutable Audit Trail**: Audit records cannot be modified or deleted

### Application-Level Protection

1. **Transaction Safety**: Number generation wrapped in database transactions
2. **Status Validation**: Document states validated before any changes
3. **User Authorization**: Actions logged with authenticated user context
4. **IP Tracking**: All actions tracked with IP address and user agent

### Audit Red Flags

The system automatically detects suspicious patterns:
- Same user creating and immediately voiding documents
- Excessive status changes in short timeframes
- After-hours modifications
- Multiple IP addresses accessing same document
- Significant amount changes without proper justification

---

## Compliance Features

### Audit Requirements Met

1. **Complete Transaction History**: Every change tracked with before/after values
2. **User Accountability**: All actions tied to authenticated users
3. **Temporal Integrity**: Exact timestamps with timezone information
4. **Non-Repudiation**: Immutable audit records with digital signatures
5. **Regulatory Reporting**: Configurable compliance reports for various standards

### Document Retention

- **Active Documents**: Fully accessible and auditable
- **Voided Documents**: Preserved with full history, marked as voided
- **Archived Documents**: Compressed audit data for long-term retention
- **Deleted Documents**: Soft-deleted only, audit trail preserved

### Integration with External Audits

```php
// Generate audit package for external auditor
$auditPackage = [
    'document_registry' => DocumentRegistry::where('document_number', $auditedNumber)->first(),
    'audit_trail' => $document->getAuditHistory(),
    'integrity_check' => RegistrarHelper::validateDocumentIntegrity($document),
    'compliance_score' => $document->generateFinancialComplianceReport()
];

// Export to auditor-required format
$this->exportAuditPackage($auditPackage, 'excel');
```

---

## Performance Considerations

### Database Indexes

The plugin creates optimized indexes for:
- Document number lookups (unique)
- Sequence integrity checking
- Audit trail queries by user/date/action
- Site-wise and temporal filtering

### Caching Strategy

- Document type configurations cached in memory
- Sequence number generation optimized with database locks
- Audit summaries cached for performance dashboards

### Scalability

- Partitioned audit trail table by year for large datasets
- Configurable retention policies for audit data
- Bulk operations optimized for system migrations

---

## Security Considerations

### Access Control

- Document numbering requires authenticated users
- Audit trail access restricted by role
- High-value document operations require elevated permissions
- Administrative operations (unlock, integrity fixes) logged separately

### Data Protection

- Sensitive audit data encrypted at rest
- IP addresses hashed for privacy compliance
- User agent strings sanitized to remove personal information
- Audit exports include watermarks and access logging

---

## Troubleshooting Guide

### Common Issues

**Document Number Collisions**:
```php
// Rare race condition - system will retry automatically
// Check for integrity issues if persistent
$integrity = RegistrarHelper::checkIntegrity('PO');
```

**Edit Protection Issues**:
```php
// Debug protection status
$status = RegistrarHelper::getProtectionStatus($document);
var_dump($status); // Shows why document is protected
```

**Audit Trail Gaps**:
```php
// Check for missing audit entries
$auditSummary = RegistrarHelper::getAuditSummary($document);
if ($auditSummary['total_actions'] < 2) {
    Log::warning('Insufficient audit trail', ['document' => $document->id]);
}
```

### Maintenance Operations

**Reset Document Type Numbering** (Year-end):
```php
$documentType = DocumentType::where('code', 'PO')->first();
$documentType->resetNumbering(); // Resets to starting number
```

**Archive Old Audit Data**:
```php
// Archive audit trails older than 7 years
DocumentAuditTrail::where('performed_at', '<', now()->subYears(7))
    ->update(['archived' => true]);
```

---

## Migration from Legacy Systems

### Document Number Mapping

```php
// Map existing document numbers to new registry
foreach ($legacyDocuments as $legacy) {
    $registry = DocumentRegistry::create([
        'full_document_number' => $legacy->old_number,
        'document_type_code' => $this->mapDocumentType($legacy->type),
        'documentable_type' => $this->mapModelClass($legacy->type),
        'documentable_id' => $legacy->new_id,
        'status' => $legacy->status,
        'created_at' => $legacy->created_date
    ]);
}
```

### Audit Trail Reconstruction

```php
// Reconstruct audit trail from legacy change logs
foreach ($legacyChangeLogs as $change) {
    DocumentAuditTrail::create([
        'document_registry_id' => $registry->id,
        'action' => $this->mapChangeAction($change->action),
        'old_values' => json_decode($change->old_data),
        'new_values' => json_decode($change->new_data),
        'performed_by' => $this->mapUserId($change->user),
        'performed_at' => $change->timestamp
    ]);
}
```

---

## Future Enhancements

### Planned Features

1. **Digital Signatures**: Cryptographic signing of critical documents
2. **Blockchain Integration**: Immutable audit trail using blockchain technology
3. **AI Fraud Detection**: Machine learning models for suspicious pattern detection
4. **External System Integration**: API for third-party audit tools
5. **Advanced Reporting**: Interactive dashboards and analytics

### API Endpoints

Future REST API for external integrations:
- `GET /api/registrar/documents/{number}` - Document lookup
- `POST /api/registrar/audit/{id}/search` - Audit trail search
- `GET /api/registrar/compliance/report` - Compliance reporting
- `POST /api/registrar/integrity/check` - Sequence validation

---

This Registrar plugin provides the foundational audit and fraud prevention infrastructure required for a compliant business system. Every controlled document in the OMSB ecosystem flows through this system, ensuring complete traceability and protection against unauthorized modifications.