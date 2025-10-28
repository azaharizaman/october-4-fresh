# OMSB System Architecture Changelog

## Major Architectural Changes and Business Logic Updates

This document tracks significant architectural changes, business logic modifications, and their impacts across the OMSB plugin ecosystem.

---

## October 28, 2025 - Vendor Shareholders Relationship Implementation

### 🏢 **Database Change: Vendor Shareholders/Directors Tracking**

#### **Background**
The procurement system needed to track vendor ownership structure, including shareholders, directors, and their respective ownership stakes. This information is critical for:
- Vendor qualification and compliance (especially Bumiputera ownership verification)
- Conflict of interest checks
- Due diligence and vendor risk assessment
- Regulatory reporting requirements
- Corporate governance transparency

#### **Changes Made**

##### **1. New Table: Vendor Shareholders (`create_vendor_shareholders_table.php`)**

```sql
CREATE TABLE omsb_procurement_vendor_shareholders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id BIGINT UNSIGNED NOT NULL,           -- FK to vendors
    name VARCHAR(255) NOT NULL,                   -- Shareholder/director name
    ic_no VARCHAR(255) NULL,                      -- IC/Passport number
    designation VARCHAR(255) NULL,                -- Position (Director, CEO, etc.)
    category VARCHAR(255) NULL,                   -- Category classification
    share VARCHAR(255) NULL,                      -- Share percentage or amount
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,                    -- Soft delete support
    
    FOREIGN KEY (vendor_id) REFERENCES omsb_procurement_vendors(id) ON DELETE CASCADE,
    INDEX idx_shareholders_vendor_id (vendor_id),
    INDEX idx_shareholders_name (name),
    INDEX idx_shareholders_ic_no (ic_no),
    INDEX idx_shareholders_deleted_at (deleted_at)
);
```

##### **2. New Model: VendorShareholder (`VendorShareholder.php`)**

**Key Features:**
```php
// Relationships
$belongsTo = ['vendor' => [Vendor::class]];

// Custom Accessors
getDisplayNameAttribute() // "John Doe (DIRECTOR) - 50%"
getFormattedShareAttribute() // "50%"

// Utility Methods
isCompany() // Detects if shareholder is company vs individual

// Query Scopes
scopeByVendor($vendorId)
scopeByCategory($category)
```

##### **3. Vendor Model Enhancement**

**Added hasMany Relationship:**
```php
public $hasMany = [
    'shareholders' => [
        VendorShareholder::class,
        'key' => 'vendor_id'
    ]
];
```

##### **4. Backend UI Integration**

**Vendors Controller:**
- Implements `RelationController` behavior
- Manages shareholders through relation interface

**Relation Configuration (`config_relation.yaml`):**
```yaml
shareholders:
    label: Shareholders/Directors
    view:
        list: $/omsb/procurement/models/vendorshareholder/columns.yaml
        toolbarButtons: create|delete
    manage:
        form: $/omsb/procurement/models/vendorshareholder/fields.yaml
```

**UI Features:**
- Add/edit/delete shareholders from vendor detail page
- List view with name, IC, designation, share percentage
- Form with validation for all fields

##### **5. Data Import Seeder (`seed_vendor_shareholders_from_csv.php`)**

**Purpose:** Import ~900 shareholder records from legacy TSI system

**Key Features:**
- **Vendor ID Mapping:** Maps legacy vendor IDs to new vendor codes
  ```
  Legacy vendor_id (1385) → Vendor code (VS900001) → New vendor record
  ```
- **Dual CSV Processing:**
  1. Reads vendor CSV to build ID→code mapping
  2. Reads shareholder CSV and maps to correct vendors
- **Data Quality:**
  - Skips soft-deleted records (deleted_at IS NOT NULL)
  - Upsert logic (updates existing, creates new)
  - Preserves original timestamps from legacy system
- **Error Handling:**
  - Reports missing vendor mappings
  - Counts imported, skipped (deleted), and skipped (vendor not found)

**Import Statistics (Expected):**
```
Total rows processed: ~918
Shareholders imported/updated: ~700-800
Skipped (deleted): ~100-200
Skipped (vendor not found): Minimal (if vendor import successful)
```

#### **Business Logic Impact**

##### **Vendor Qualification Workflows**
```php
// Verify Bumiputera ownership (requires >51% Malaysian shareholders)
$malaysiaShareholders = $vendor->shareholders()
    ->where('category', '1')
    ->get();

$totalMalaysianShare = $malaysiaShareholders->sum('share');

if ($vendor->is_bumi && $totalMalaysianShare < 51) {
    // Flag for review - claimed Bumi but ownership doesn't meet threshold
}
```

##### **Conflict of Interest Checks**
```php
// Check if any shareholder is also a staff member
$staffICs = Staff::pluck('ic_no');
$conflictingShareholders = $vendor->shareholders()
    ->whereIn('ic_no', $staffICs)
    ->get();

if ($conflictingShareholders->count() > 0) {
    // Flag potential conflict of interest
}
```

##### **Ownership Reports**
```php
// Generate ownership structure report
$ownershipReport = $vendor->shareholders()
    ->whereNull('deleted_at')
    ->orderByRaw('CAST(share AS DECIMAL(10,2)) DESC')
    ->get()
    ->map(function($shareholder) {
        return [
            'name' => $shareholder->name,
            'designation' => $shareholder->designation,
            'share' => $shareholder->share . '%',
            'type' => $shareholder->isCompany() ? 'Corporate' : 'Individual'
        ];
    });
```

##### **Corporate Shareholder Detection**
```php
// Identify vendors with corporate shareholders (potential group structures)
$corporateShareholders = VendorShareholder::whereNull('deleted_at')
    ->get()
    ->filter(function($shareholder) {
        return $shareholder->isCompany();
    });

// Cross-reference to detect vendor groups
foreach ($corporateShareholders as $corpShareholder) {
    $relatedVendor = Vendor::where('name', 'LIKE', '%' . $corpShareholder->name . '%')->first();
    if ($relatedVendor) {
        // Detected corporate group relationship
    }
}
```

#### **Migration Path**

**Version Sequence:**
1. **v1.0.2** - `create_vendors_table.php` (vendors created first)
2. **v1.0.3** - `create_vendor_shareholders_table.php` (shareholders table)
3. **v1.0.4** - `seed_vendors_from_csv.php` (seed vendors)
4. **v1.0.5** - `seed_vendor_shareholders_from_csv.php` (seed shareholders)

**Migration Commands:**
```bash
# Run all migrations and seeders in order
php artisan plugin:refresh Omsb.Procurement

# Verify shareholder import
mysql -u root -p railwayfour -e "
SELECT 
    v.code, v.name, 
    COUNT(s.id) as shareholder_count 
FROM omsb_procurement_vendors v 
LEFT JOIN omsb_procurement_vendor_shareholders s ON v.id = s.vendor_id 
WHERE s.deleted_at IS NULL 
GROUP BY v.id 
HAVING shareholder_count > 0 
ORDER BY shareholder_count DESC 
LIMIT 10;
"
```

#### **Data Quality Considerations**

**IC Number Formats:**
- Malaysian IC: `YYMMDD-SS-####` (e.g., `830206-13-5676`)
- Passport: Variable format (e.g., `G24109480`)
- Company Registration: May be blank or registration number

**Share Values:**
- Formats: Percentage (`70`), decimal (`7.5`), or empty
- **Note:** May not sum to 100% (data quality issue in legacy system)

**Designation Values:**
- Common: DIRECTOR, CEO, MANAGING DIRECTOR, MANAGER, PARTNER, OWNER
- Variable capitalization in legacy data

**Category Values:**
- `0` - Possibly foreign/corporate shareholder
- `1` - Possibly Malaysian/individual shareholder
- Empty - Unknown classification

#### **Backward Compatibility**

✅ **Fully Compatible:**
- No changes to existing Vendor model fields or relationships
- Shareholders are optional (vendors can have zero shareholders)
- Existing vendor queries unaffected
- Pure additive change (no breaking modifications)

#### **Future Enhancements**

1. **Share Validation:** Add business rule to validate shares sum to 100%
2. **Historical Tracking:** Track shareholder changes over time (audit trail)
3. **IC Validation:** Validate Malaysian IC format and checksum
4. **Relationship Mapping:** Link related shareholders across vendors (detect groups)
5. **Document Attachment:** Upload shareholding certificates/documents
6. **Ownership Analysis Dashboard:** Visual ownership structure reports
7. **Automated Compliance Checks:** Flag Bumi ownership mismatches
8. **Shareholder Portal:** Allow shareholders to update their own information

---

## October 28, 2025 - Vendor Model Schema Extension for Legacy Data Migration

### 📦 **Database Change: Vendor Table Schema Extension**

#### **Background**
The vendor management system needed to import historical vendor data from a legacy TSI procurement system. The existing vendor schema was minimal and didn't accommodate the rich vendor metadata from the legacy system, including:
- Vendor classification (Bumiputera status, specialized vendors, precision vendors)
- Tax/GST registration details
- Credit management information
- Detailed address and contact information
- Business scope and service descriptions

#### **Changes Made**

##### **1. Procurement Vendor Migration (`create_vendors_table.php`)**

**New Fields Added:**

```sql
-- Vendor identification and dates
+ incorporation_date DATE NULL              -- Date of company incorporation
+ sap_code VARCHAR NULL                     -- SAP system integration code

-- Vendor classification flags
+ is_bumi BOOLEAN DEFAULT false             -- Bumiputera vendor status
+ type VARCHAR NULL                         -- Standard, Contractor, Specialized Vendor, etc.
+ category VARCHAR NULL                     -- Vendor category
+ is_specialized BOOLEAN DEFAULT false      -- Specialized vendor flag
+ is_precision BOOLEAN DEFAULT false        -- Precision vendor flag (high-accuracy requirements)
+ is_approved BOOLEAN DEFAULT false         -- Pre-approved vendor flag

-- Tax/GST information (extended from single tax_number)
+ is_gst BOOLEAN DEFAULT false              -- GST registered flag
+ gst_number VARCHAR NULL                   -- GST registration number
+ gst_type VARCHAR NULL                     -- GST type (SR = Special Rate, etc.)
+ tax_number VARCHAR NULL                   -- Generic tax ID (kept for compatibility)

-- International vendor support
+ is_foreign BOOLEAN DEFAULT false          -- Foreign vendor flag
+ country_id INT UNSIGNED NULL              -- Current country
+ origin_country_id INT UNSIGNED NULL       -- Origin/home country

-- Enhanced contact information
+ designation VARCHAR NULL                  -- Contact person's job title
+ tel_no VARCHAR NULL                       -- Telephone number
+ fax_no VARCHAR NULL                       -- Fax number
+ hp_no VARCHAR NULL                        -- Mobile/HP number
+ email VARCHAR NULL                        -- General email (in addition to contact_email)

-- Embedded address fields (in addition to address_id FK)
+ street VARCHAR NULL                       -- Street address
+ city VARCHAR NULL                         -- City
+ state_id INT UNSIGNED NULL                -- State/region
+ postcode VARCHAR NULL                     -- Postal code

-- Business information
+ scope_of_work TEXT NULL                   -- Detailed scope of services/products
+ service VARCHAR NULL                      -- Service category/department

-- Credit management
+ credit_limit DECIMAL(15,2) NULL           -- Credit limit amount
+ credit_terms VARCHAR NULL                 -- Credit payment terms
+ credit_updated_at TIMESTAMP NULL          -- Last credit review date
+ credit_review VARCHAR NULL                -- Credit review status
+ credit_remarks TEXT NULL                  -- Credit management notes

-- Multi-company support
+ company_id INT UNSIGNED NULL              -- Company association (for multi-company setup)

-- Status change
~ status VARCHAR DEFAULT 'Active'           -- Changed from ENUM to VARCHAR for legacy compatibility
```

**Indexes Added:**
```sql
+ INDEX idx_vendors_type (type)
+ INDEX idx_vendors_is_bumi (is_bumi)
+ INDEX idx_vendors_is_specialized (is_specialized)
+ INDEX idx_vendors_is_approved (is_approved)
+ INDEX idx_vendors_company_id (company_id)
```

##### **2. Vendor Model Updates (`Vendor.php`)**

**Fillable Fields Extended:**
- Added 33 new fillable fields to accommodate all CSV columns
- Maintained backward compatibility with existing fields

**Nullable Fields:**
- Extended `$nullable` array to properly handle empty values from CSV import
- Prevents "Incorrect integer value" errors for nullable foreign keys

**Validation Rules Enhanced:**
```php
+ 'incorporation_date' => 'nullable|date'
+ 'credit_limit' => 'nullable|numeric|min:0'
+ 'credit_updated_at' => 'nullable|date'
+ 'is_bumi' => 'boolean'
+ 'is_specialized' => 'boolean'
+ 'is_precision' => 'boolean'
+ 'is_approved' => 'boolean'
+ 'is_gst' => 'boolean'
+ 'is_foreign' => 'boolean'
```

**New Casts:**
```php
+ 'is_bumi' => 'boolean'
+ 'is_specialized' => 'boolean'
+ 'is_precision' => 'boolean'
+ 'is_approved' => 'boolean'
+ 'is_gst' => 'boolean'
+ 'is_foreign' => 'boolean'
+ 'credit_limit' => 'decimal:2'
+ 'incorporation_date' => 'date'
+ 'credit_updated_at' => 'datetime'
```

**New Model Scopes:**
```php
+ scopeBumi()           // Filter Bumiputera vendors only
+ scopeSpecialized()    // Filter specialized vendors only
+ scopeApproved()       // Filter pre-approved vendors only
+ scopeByType($type)    // Filter by vendor type
```

**Status Handling Update:**
- Changed from hardcoded ENUM values (`active`, `inactive`, `blacklisted`)
- Now uses VARCHAR to support legacy status values (`Active`, `Inactive`, `Blacklisted`, etc.)
- Updated `isActive()` and `isBlacklisted()` methods to use case-insensitive comparison

##### **3. CSV Import Seeder (`seed_vendors_from_csv.php`)**

**Purpose:** Import 3,200+ vendor records from legacy TSI procurement system

**Features:**
- Reads CSV file: `raw_data/tsi_procurement_vendors_202510281231-procurement_vendors.csv`
- Skips soft-deleted records (where `deleted_at IS NOT NULL`)
- Preserves original timestamps from legacy system
- Updates existing vendors by code (upsert logic)
- Transaction-based import for data integrity
- Comprehensive data parsing:
  - Boolean conversion (0/1 to false/true)
  - Date parsing (ISO format)
  - Decimal parsing for credit limits
  - Null handling for empty strings
- Progress reporting with import statistics

**Usage:**
```bash
php artisan db:seed --class="Omsb\Procurement\Updates\SeedVendorsFromCsv"
```

#### **Business Logic Impact**

##### **Enhanced Vendor Classification**
```php
// Query Bumiputera vendors for government procurement preferences
$bumiVendors = Vendor::bumi()->active()->get();

// Find specialized vendors for complex medical equipment
$specializedVendors = Vendor::specialized()
    ->where('type', 'Specialized Vendor')
    ->where('scope_of_work', 'LIKE', '%medical%')
    ->get();

// Filter contractors vs. standard vendors
$contractors = Vendor::byType('Contractor')->get();
$standardVendors = Vendor::byType('Standard Vendor')->get();
```

##### **Credit Management Integration**
```php
// Check vendor credit limits before PO approval
if ($purchaseOrder->total_amount > $vendor->credit_limit) {
    throw new ValidationException(['vendor' => 'PO amount exceeds vendor credit limit']);
}

// Track credit review dates
if ($vendor->credit_updated_at < now()->subMonths(6)) {
    // Flag for credit review
}
```

##### **International Vendor Support**
```php
// Handle foreign vendors differently (currency, tax, shipping)
if ($vendor->is_foreign) {
    $po->currency_code = $vendor->country->currency_code;
    $po->requires_customs_clearance = true;
}
```

#### **Migration Path**

1. **Backup existing vendor data** (if any)
2. **Run updated migration:**
   ```bash
   php artisan plugin:refresh Omsb.Procurement
   ```
3. **Import legacy vendor data:**
   ```bash
   php artisan db:seed --class="Omsb\Procurement\Updates\SeedVendorsFromCsv"
   ```
4. **Verify import results:**
   - Check import statistics in console output
   - Validate vendor counts match expectations
   - Spot-check vendor details for accuracy

#### **Backward Compatibility**

✅ **Fully Backward Compatible:**
- All original fields retained (`code`, `name`, `registration_number`, `tax_number`, etc.)
- Existing relationships unchanged (`address`, `purchase_orders`, `vendor_quotations`, `purchaseable_items`)
- Original scopes preserved (`scopeActive`, `scopeByStatus`)
- Existing validation rules maintained
- `address_id` foreign key still supported for future address normalization

**⚠️ Breaking Change:**
- `status` field changed from `ENUM('active', 'inactive', 'blacklisted')` to `VARCHAR`
- **Impact:** Any hardcoded status checks using exact case-sensitive strings may need updates
- **Mitigation:** Model methods `isActive()` and `isBlacklisted()` now use case-insensitive comparison

#### **Future Enhancements**

1. **Address Normalization:** Create Address records for all vendors using embedded address fields, then populate `address_id`
2. **Country/State Reference:** Link `country_id`, `origin_country_id`, and `state_id` to reference tables
3. **Credit Management Module:** Build dedicated credit management workflow using `credit_*` fields
4. **Vendor Portal:** Expose vendor profile for self-service updates using enhanced fields
5. **Vendor Performance Tracking:** Leverage vendor metadata for supplier scorecarding

---

## October 27, 2025 - Approval System Consolidation

### 🏗️ **Architecture Change: MLAS Integration into Organization Plugin**

#### **Background**
The system previously had two separate approval mechanisms:
- Organization plugin: Basic approval rules (staff-based, amount-based)
- Workflow plugin: MLAS (Multi-Level Approval System) table with overlapping functionality

This duplication created confusion, maintenance overhead, and limited the system's ability to handle complex approval scenarios.

#### **Changes Made**

##### **1. Organization Plugin Enhancements (`omsb_organization_approvals`)**
```sql
-- Added MLAS functionality to existing approvals table:
+ approval_type VARCHAR(20) DEFAULT 'single'  -- single, quorum, majority, unanimous
+ required_approvers INT DEFAULT 1            -- How many approvals needed
+ eligible_approvers INT NULL                 -- Total eligible pool (for quorum)
+ assignment_strategy VARCHAR(20) DEFAULT 'manual' -- manual, position_based, round_robin
+ is_position_based BOOLEAN DEFAULT false
+ eligible_position_ids JSON NULL             -- Array of position IDs
+ eligible_staff_ids JSON NULL                -- Array of staff IDs
+ requires_hierarchy_validation BOOLEAN DEFAULT true
+ minimum_hierarchy_level INT NULL
+ override_individual_limits BOOLEAN DEFAULT false
+ approval_timeout_days INT NULL
+ timeout_action VARCHAR(20) DEFAULT 'revert' -- revert, escalate, auto_approve
+ escalation_approval_rule_id BIGINT NULL
+ rejection_target_status VARCHAR NULL
+ requires_comment_on_rejection BOOLEAN DEFAULT true
+ requires_comment_on_approval BOOLEAN DEFAULT false
+ allows_delegation BOOLEAN DEFAULT true
+ max_delegation_days INT NULL
+ requires_delegation_justification BOOLEAN DEFAULT false
```

##### **2. Workflow Plugin Refocus**
- **Removed**: `omsb_workflow_mlas` table (approval definitions)
- **Added**: `omsb_workflow_instances` table (execution tracking)
- **Added**: `omsb_workflow_actions` table (individual approval actions)
- **Updated**: `MLA` model now aliases to `Organization\Approval`

##### **3. New Workflow Models**

**WorkflowInstance** - Manages ongoing workflow execution:
```php
'workflow_code'         // Unique identifier
'status'               // pending, in_progress, completed, failed, cancelled
'document_type'        // Type of document being approved
'documentable'         // morphTo relationship to actual document
'current_step'         // Current approval step
'approvals_required'   // How many approvals needed for current step
'approvals_received'   // How many received so far
'approval_path'        // JSON array of approval rule IDs in sequence
```

**WorkflowAction** - Tracks individual approval actions:
```php
'action'               // approve, reject, delegate, escalate, comment
'step_sequence'        // Order in the workflow
'staff_id'            // Who took the action
'approval_rule_id'    // Which rule was applied
'comments'            // Approver comments
'action_taken_at'     // When action was performed
```

#### **Business Logic Impact**

##### **Enhanced Multi-Approver Support**
```php
// NEW: "3 out of 5 department heads must approve"
Approval::create([
    'document_type' => 'purchase_request',
    'approval_type' => 'quorum',
    'required_approvers' => 3,
    'eligible_approvers' => 5,
    'assignment_strategy' => 'position_based',
    'eligible_position_ids' => [1, 2, 3, 4, 5]
]);
```

##### **Position-based Assignment**
```php
// NEW: Any Finance Manager at any site can approve
Approval::create([
    'assignment_strategy' => 'position_based',
    'is_position_based' => true,
    'eligible_position_ids' => [$financeManagerPosition->id],
    'allow_external_site_approvers' => true
]);
```

##### **Escalation Chains**
```php
// NEW: Auto-escalate after 5 days
Approval::create([
    'approval_timeout_days' => 5,
    'timeout_action' => 'escalate',
    'escalation_approval_rule_id' => $seniorRule->id
]);
```

#### **Clear Separation of Concerns**

| **Organization Plugin** | **Workflow Plugin** |
|-------------------------|---------------------|
| Approval **Definitions** | Workflow **Execution** |
| WHO can approve | TRACKING ongoing approvals |
| HOW MUCH they can approve | Individual approval ACTIONS |
| WHICH documents | Progress monitoring |
| Approval POLICIES | Overdue management |
| Hierarchy rules | Audit trail |

#### **Migration Strategy**
1. **Zero-breaking Changes**: Existing approval rules continue to work
2. **Enhanced Capabilities**: Added fields provide new functionality
3. **Backward Compatibility**: `MLA` model aliases to `Approval`
4. **Data Migration**: No existing data needs modification

#### **Affected Systems**

##### **Procurement Plugin**
- Purchase Request approvals now support quorum-based approval
- Multi-site purchase orders can have site-specific approval rules
- Enhanced vendor payment approvals with escalation

##### **Inventory Plugin**
- Stock adjustments support multiple approver validation
- Physical count discrepancies can require department head consensus
- Inter-warehouse transfers support site-specific approval rules

##### **Reporting Systems**
- New approval analytics: average approval time, bottleneck identification
- Delegation tracking and compliance reporting
- Overdue workflow monitoring

#### **Benefits Achieved**
✅ **Eliminated Duplication**: Single source of truth for approval rules  
✅ **Enhanced Capabilities**: Complex multi-approver scenarios ("3 of 5")  
✅ **Better Integration**: Seamless with organizational hierarchy  
✅ **Improved Tracking**: Comprehensive audit trail of all actions  
✅ **Scalable Architecture**: Clean separation allows independent evolution  
✅ **Enterprise Ready**: Supports complex corporate approval workflows  

#### **Technical Metrics**
- **Database Tables**: Reduced from 2 approval tables to 1 definition + 2 execution
- **Code Duplication**: Eliminated ~300 lines of duplicate approval logic
- **Query Performance**: Consolidated approval lookups reduce database calls
- **Maintenance**: Single codebase for approval definitions reduces bugs

---

## October 27, 2025 - Budget Plugin Implementation

### 🏗️ **Architecture Change: New Budget Management Plugin**

#### **Background**
Previously, budget functionality was planned to be part of the Procurement plugin. However, budget management deserves its own dedicated plugin due to its cross-cutting nature and integration with multiple systems (Procurement, Organization, Workflow, Registrar).

#### **Changes Made**

##### **1. New Budget Plugin (`omsb/budget`)**
Created a standalone plugin for comprehensive budget management with the following structure:

**Core Models:**
1. **Budget** - Main budget entity (NOT a controlled document)
   - Budget code: Free input field (e.g., `BGT/SGH/FEMS/MTC/5080190/25`)
   - Yearly budget allocations with effective date ranges
   - Associated with GL Accounts and Sites
   - Optional service department assignment
   - Status tracking: draft → approved → active → expired/cancelled

2. **BudgetTransfer** - Intersite budget transfers (Controlled Document)
   - Requires document number from Registrar plugin
   - Must be between different sites (intersite requirement enforced)
   - Tracks outward/inward transfer types
   - Full workflow support with approval tracking

3. **BudgetAdjustment** - Budget amount modifications (Controlled Document)
   - Requires document number from Registrar plugin
   - Supports increase/decrease adjustments
   - Amount automatically normalized based on type
   - Requires justification for all adjustments

4. **BudgetReallocation** - Same-site budget reallocations (Controlled Document)
   - Requires document number from Registrar plugin
   - Must be within same site (enforced validation)
   - Reallocates between different GL accounts
   - Validates GL accounts belong to budget's site

##### **2. Database Schema**
```sql
-- Core budget table (NOT controlled document)
omsb_budget_budgets
- budget_code VARCHAR(100)           -- Free input
- description VARCHAR(500)
- year INT
- effective_from DATE
- effective_to DATE
- allocated_amount DECIMAL(15,2)
- status ENUM
- gl_account_id FK
- site_id FK
- service_code VARCHAR(10) NULLABLE
- created_by, approved_by FK to backend_users

-- Transaction tables (ALL controlled documents)
omsb_budget_transfers
- document_number VARCHAR(100) UNIQUE -- From registrar
- transfer_type ENUM(outward, inward)
- from_budget_id, to_budget_id FK
- amount DECIMAL(15,2)
- status ENUM

omsb_budget_adjustments
- document_number VARCHAR(100) UNIQUE -- From registrar
- adjustment_type ENUM(increase, decrease)
- adjustment_amount DECIMAL(15,2)
- budget_id FK
- status ENUM

omsb_budget_reallocations
- document_number VARCHAR(100) UNIQUE -- From registrar
- from_gl_account_id, to_gl_account_id FK
- budget_id FK
- amount DECIMAL(15,2)
- status ENUM
```

#### **Business Logic Implementation**

##### **Calculated Fields (Real-time)**
The Budget model includes calculated fields that provide budget status:

```php
// Computed on-the-fly (not stored in database)
$budget->total_transferred_out    // Sum of approved outward transfers
$budget->total_transferred_in     // Sum of approved inward transfers
$budget->total_adjustments        // Sum of approved adjustments
$budget->total_reallocations      // Sum of approved reallocations
$budget->current_budget           // allocated + in - out + adjustments + reallocations
$budget->utilized_amount          // From Purchase Orders (future integration)
$budget->available_balance        // current_budget - utilized_amount
$budget->utilization_percentage   // (utilized / current) * 100
```

##### **Budget Validation Rules**

**BudgetTransfer Validation:**
```php
// Must be intersite (different sites)
if ($fromBudget->site_id === $toBudget->site_id) {
    throw ValidationException(
        'Budget transfers must be between different sites. 
         Use Budget Reallocation for same-site transfers.'
    );
}
```

**BudgetReallocation Validation:**
```php
// Must be same site
if ($fromGlAccount->site_id !== $budget->site_id || 
    $toGlAccount->site_id !== $budget->site_id) {
    throw ValidationException(
        'Reallocation GL accounts must belong to budget\'s site.'
    );
}
```

##### **Budget Checking Methods**
```php
// Check if budget has sufficient balance for PO
$budget->hasSufficientBalance($amount);  // returns bool
$budget->wouldExceedBudget($amount);     // returns bool

// Used by Procurement plugin to determine approval flow
if ($budget->wouldExceedBudget($poAmount)) {
    // Follow non-budgeted purchase approval flow
}
```

#### **Integration Points**

##### **Organization Plugin**
- **Sites**: Budgets belong to organizational sites
- **GL Accounts**: Each budget associated with specific GL account
  - Only transactable (non-header) GL accounts selectable
  - GL accounts filtered by site in reallocations
- **Service Settings**: Optional service code for department budgets
- **Staff/Approval**: Creator and approver tracking

##### **Registrar Plugin**
- **Document Numbers**: All budget transactions require document numbers
- **Document Patterns**: Each transaction type can have unique numbering pattern
  - Budget Transfer: e.g., `BT-SITE-YYYY-#####`
  - Budget Adjustment: e.g., `BA-SITE-YYYY-#####`
  - Budget Reallocation: e.g., `BR-SITE-YYYY-#####`

##### **Workflow Plugin**
- **Status Transitions**: Budget transactions follow workflow definitions
- **Approval Flows**: Multi-level approvals based on amount and hierarchy
- **WorkflowInstance**: Tracks ongoing approval process
- **WorkflowAction**: Records individual approval actions

##### **Procurement Plugin (Future Integration)**
- **Budget Utilization**: Track PO amounts against budgets
- **Exceed Budget Checks**: Determine if PO exceeds available budget
- **Non-budgeted Flow**: POs exceeding budget follow alternative approval
- **GL Account Matching**: Link POs to budgets via GL account + site + service

##### **Feeder Plugin**
- **Activity Tracking**: All budget and transaction activities logged
- **Audit Trail**: Complete history of budget changes and approvals
- **Feed Items**: User-friendly activity feed for budget lifecycle

#### **Backend Interface**

##### **Navigation Structure**
```
Budget (Main Menu)
├── Budgets (Core management)
├── TRANSACTIONS (Separator)
├── Budget Transfers (Intersite)
├── Budget Adjustments (Increase/Decrease)
├── Budget Reallocations (Same-site)
├── REPORTS (Separator)
└── Budget Reports (Analytics)
```

##### **Budget Form Features**
- **Primary Tab**: Core budget information
- **Budget Details Tab**: Real-time calculated fields display
- **Transaction Tabs**: 
  - Outward Transfers (relation controller)
  - Inward Transfers (relation controller)
  - Adjustments (relation controller)
  - Reallocations (relation controller)
- **Audit Trail Tab**: Creation and approval information

##### **Form Validations**
- Budget code: Required, free input (no format enforcement)
- Effective dates: End date must be after start date
- Amount: Must be non-negative
- GL Account: Must be active and transactable
- Status: Controls editability (only draft is editable)

#### **Permissions Structure**

```php
'omsb.budget.access_all'            // Full access
'omsb.budget.manage_budgets'        // Manage budgets
'omsb.budget.budget_transfers'      // Manage transfers
'omsb.budget.budget_adjustments'    // Manage adjustments
'omsb.budget.budget_reallocations'  // Manage reallocations
'omsb.budget.view_reports'          // View budget reports
```

#### **Design Decisions**

##### **1. Budget Code: Free Input vs. Auto-Generated**
**Decision**: Free input field  
**Rationale**: 
- Budget codes follow complex organizational patterns
- Include site codes, service codes, GL account numbers, year
- Example: `BGT/SGH/FEMS/MTC/5080190/25`
- Different sites/departments may have different conventions
- Flexibility prioritized over standardization

##### **2. Budget vs. Budget Transactions: Document Status**
**Decision**: Budget is NOT a controlled document  
**Rationale**:
- Budget itself is a planning document
- Budget transactions (transfers, adjustments, reallocations) are operational
- Only operational documents need formal document numbers and workflows
- Reduces overhead while maintaining control where needed

##### **3. Calculated Fields: Computed vs. Stored**
**Decision**: Computed on-the-fly  
**Rationale**:
- Always reflects current state from transactions
- No risk of data inconsistency
- Simplifies transaction processing (no aggregate updates)
- Performance acceptable for typical budget record counts

##### **4. Transfer vs. Reallocation: Why Two Models?**
**Decision**: Separate models with validation  
**Rationale**:
- Different business meanings (intersite vs same-site)
- Different approval requirements and workflows
- Enforced separation prevents user errors
- Clear audit trail of movement types

#### **Benefits Achieved**

✅ **Comprehensive Budget Tracking**: Full lifecycle from allocation to utilization  
✅ **Flexible Budget Codes**: Accommodates diverse organizational patterns  
✅ **Real-time Budget Status**: Calculated fields always current  
✅ **Transaction Integrity**: Enforced business rules for transfers/reallocations  
✅ **Integration Ready**: Prepared for Procurement PO budget checking  
✅ **Audit Trail**: Complete history via Feeder and Workflow plugins  
✅ **Scalable Design**: Clean separation between budget and transactions  

#### **Future Enhancements**

**Phase 2 (Pending):**
- Budget utilization tracking from Purchase Orders
- Non-budgeted purchase approval flow integration
- Registrar integration for auto-document numbering

**Phase 3 (Future):**
- Budget reports and analytics dashboard
- Budget forecasting and planning tools
- Multi-year budget comparison
- Budget performance metrics
- Budget allocation recommendations (AI-powered)

#### **Technical Metrics**
- **Database Tables**: 4 new tables (1 core + 3 transactions)
- **Models**: 4 models with full validation and relationships
- **Controllers**: 4 backend controllers with form/list behaviors
- **YAML Configs**: 24+ configuration files for forms, lists, columns, fields
- **Code Volume**: ~2,500 lines of PHP code + configurations
- **Test Coverage**: Pending (test infrastructure setup required)

#### **Migration Impact**
- **Breaking Changes**: None (new plugin, no existing dependencies)
- **Database Migration**: Standard October CMS migration system
- **User Training**: Required for budget staff and approvers
- **Documentation**: Complete README.md with examples

---

## Future Architecture Changes

### Planned Q1 2026
- **Notification System**: Real-time approval notifications via WebSocket
- **Mobile Integration**: Native mobile app for approval workflows
- **API Gateway**: RESTful APIs for external system integration

### Proposed Q2 2026  
- **AI-Powered Routing**: Machine learning for optimal approval routing
- **Blockchain Audit**: Immutable approval trail for compliance
- **Multi-Tenant Support**: Support for multiple organizations in single instance

---

## Change Impact Assessment Template

For future changes, use this template:

### **Change Overview**
- **Date**: 
- **Type**: Architecture | Business Logic | Performance | Security
- **Scope**: Plugin(s) affected
- **Breaking Changes**: Yes/No

### **Business Justification**
- **Problem**: What issue does this solve?
- **Solution**: How does this change address it?
- **Benefits**: Measurable improvements expected

### **Technical Details**
- **Database Changes**: Schema modifications
- **API Changes**: New/modified endpoints
- **Code Changes**: Files/classes affected
- **Dependencies**: New requirements or removed dependencies

### **Impact Analysis**
- **Affected Plugins**: List with specific impacts
- **Migration Required**: Yes/No, with strategy
- **Backward Compatibility**: Assessment and mitigation
- **Performance Impact**: Expected changes
- **Testing Requirements**: Validation needed

### **Rollout Plan**
- **Development**: Implementation timeline
- **Testing**: QA strategy
- **Deployment**: Production rollout plan
- **Documentation**: Updates required
- **Training**: User education needs

---

## Documentation Standards

### **When to Document Changes**
1. **Architecture Changes**: Any modification affecting multiple plugins
2. **Business Logic Changes**: New workflows or rule modifications  
3. **Database Schema Changes**: Table/column additions/modifications
4. **Integration Changes**: New external system connections
5. **Performance Optimizations**: Significant performance improvements
6. **Security Enhancements**: Authentication, authorization, or encryption changes

### **Documentation Requirements**
1. **Update Plugin READMEs**: Reflect current functionality
2. **Update Copilot Instructions**: Keep AI context current
3. **Update This Changelog**: Record architectural impacts
4. **Create Migration Guides**: For breaking changes
5. **Update API Documentation**: For interface changes

### **Review Process**
1. **Technical Review**: Architecture team validates technical soundness
2. **Business Review**: Product team validates business value
3. **Documentation Review**: Documentation team ensures completeness
4. **Approval**: Senior architect signs off on major changes

---

*This changelog ensures that architectural decisions and their business impacts are preserved for future reference and onboarding.*