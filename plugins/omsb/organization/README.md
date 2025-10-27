# Organization Plugin

## Overview

The Organization plugin manages the hierarchical structure of companies, sites, warehouses, and staff for One Medicare Sdn Bhd. It provides a comprehensive system for organizing business entities with multi-level hierarchies.

## Key Concepts

### Models

#### Company
The Company model represents business entities within the organization. Companies can form hierarchical structures by referencing parent companies, allowing for complex organizational trees.

**Features:**
- Hierarchical structure with parent-child relationships
- Multiple addresses per company
- Primary address selection from company addresses
- Multiple sites per company
- Comprehensive validation rules

**Relationships:**
- `belongsTo`: `parent` (Company), `address` (Address)
- `hasMany`: `children` (Company), `addresses` (Address), `sites` (Site)

**Key Methods:**
- `getDisplayNameAttribute()`: Returns formatted display name (code - name)
- `getParentIdOptions()`: Returns dropdown options for parent company selection (prevents circular references)
- `getAddressIdOptions()`: Returns dropdown options for primary address selection (only addresses belonging to this company)

**Validation:**
- `code`: Required, unique across all companies
- `name`: Required, minimum 3 characters

#### Address
Address model stores physical addresses that can be referenced by companies, sites, and warehouses. This centralized approach minimizes errors and reduces the need to update addresses in multiple locations.

**Features:**
- Defined at company level
- Complete address information (street, city, state, postcode, country, region)
- Unique constraint on full address to prevent duplicates
- Formatted address display

**Relationships:**
- `belongsTo`: `company` (Company)

**Key Methods:**
- `getFullAddressAttribute()`: Returns formatted complete address string

**Validation:**
- All address fields required except region
- Unique constraint on combined address fields

#### Site
Sites represent physical locations or branches within the organization.

**Relationships:**
- `belongsTo`: `parent` (Site), `company` (Company), `address` (Address)
- `hasMany`: `children` (Site), `warehouses` (Warehouse from Inventory plugin)

#### Warehouse
Managed by the Inventory plugin. Warehouses are storage locations within sites.

#### Staff
Staff members with hierarchical relationships for approval workflows.

#### GLAccount
Chart of accounts entries at site level for financial tracking.

## Backend Interface

### Companies Controller

The Companies controller provides full CRUD operations for managing companies through the OctoberCMS backend.

**Features:**
- List view with searching, sorting, and pagination
- Form view with tabbed interface
- Relation management for addresses, sites, and child companies
- File upload for company logos

**Behaviors:**
- `FormController`: Handles create, update, and preview operations
- `ListController`: Manages the company list view
- `RelationController`: Manages related records (addresses, sites, children)

**Tabs:**
1. **Primary Tab**: Basic company information (code, name, parent company, logo, primary address)
2. **Addresses Tab**: Manage company addresses with inline create/edit/delete
3. **Sites Tab**: Manage company sites
4. **Child Companies Tab**: View and manage child companies in the hierarchy

### Navigation

The Organization menu appears in the backend navigation with the following structure:
- **Companies**: Main company management interface
- **Sites**: Site management interface

## Database Structure

### Companies Table (`omsb_organization_companies`)
- `id`: Primary key
- `code`: Unique company identifier
- `name`: Company name
- `logo`: Company logo file path (optional)
- `parent_id`: Foreign key to parent company (nullable)
- `address_id`: Foreign key to primary address (nullable)
- `created_at`, `updated_at`, `deleted_at`: Timestamps

### Addresses Table (`omsb_organization_addresses`)
- `id`: Primary key
- `company_id`: Foreign key to company (nullable)
- `address_street`: Street address
- `address_city`: City name
- `address_state`: State/province (default: Sarawak)
- `address_postcode`: Postal code
- `address_country`: Country (default: Malaysia)
- `region`: Optional region identifier
- `created_at`, `updated_at`, `deleted_at`: Timestamps

## Usage Examples

### Creating a Company with Hierarchy

1. Navigate to Organization > Companies
2. Click "New Company"
3. Enter company code (e.g., "HQ") and name
4. Optionally select a parent company to create hierarchy
5. Upload a logo if desired
6. Save the company
7. Switch to "Addresses" tab to add company addresses
8. Once addresses are added, return to the primary tab and select a primary address
9. Switch to "Sites" tab to add company sites

### Managing Addresses

1. Open an existing company for editing
2. Navigate to the "Addresses" tab
3. Click "Create" to add a new address
4. Fill in address details (street, city, state, postcode, country)
5. Save the address
6. Return to the primary tab to select this as the primary address if desired

### Viewing Company Hierarchy

1. Open a parent company for editing
2. Navigate to the "Child Companies" tab
3. View all companies that have this company as their parent
4. Note: Child companies are managed through their own forms by setting the parent_id

## Technical Implementation Details

### Form Field Configuration
Form fields are defined in `models/company/fields.yaml` with:
- Primary fields on the main tab
- Secondary tabs for related data (addresses, sites, children)
- Conditional display logic (e.g., address selection only appears after company is saved)

### Relation Configuration
Relations are configured in `controllers/companies/config_relation.yaml`:
- **Addresses**: Full CRUD with create/delete buttons
- **Sites**: Full CRUD with create/delete buttons
- **Children**: View-only with add/remove capabilities

### Validation Rules
Validation is handled at the model level:
- Company code must be unique
- Company name must be at least 3 characters
- Address fields are required (except region)
- Duplicate addresses are prevented by unique constraint

## Integration Points

### With Inventory Plugin
- Sites reference warehouses from the Inventory plugin
- Warehouse model: `Omsb\Inventory\Models\Warehouse`

### With Other OMSB Plugins
- Organization provides the foundational structure for other plugins
- Staff hierarchy used for approval workflows (Workflow plugin)
- Site structure used for inventory management (Inventory plugin)
- Company and site data used for procurement (Procurement plugin)

## Approval System (Enhanced Multi-Level Approval)

### Overview
The Organization plugin manages **approval definitions and policies** for the entire OMSB system. It defines WHO can approve WHAT and HOW MUCH, supporting complex multi-level approval scenarios including quorum-based approvals.

### Approval Model (Enhanced MLAS)

The `Approval` model in Organization plugin combines traditional approval rules with Multi-Level Approval System (MLAS) functionality.

**Core Approval Features:**
```php
// Basic approval definition
'document_type'        // purchase_request, stock_adjustment, etc.
'action'              // approve, review, authorize
'floor_limit'         // Minimum value this approver can handle
'ceiling_limit'       // Maximum value (null = unlimited)
'from_status'         // Current document status to transition from
'to_status'           // Target status after approval
```

**Multi-Approver Support (MLAS):**
```php
'approval_type'       // single, quorum, majority, unanimous
'required_approvers'  // How many approvals needed (e.g., 3)
'eligible_approvers'  // Total eligible pool (e.g., 5)
'assignment_strategy' // manual, position_based, round_robin
'eligible_position_ids' // JSON array of position IDs
'eligible_staff_ids'   // JSON array of staff IDs
```

**Hierarchy and Validation:**
```php
'requires_hierarchy_validation' // Must be above creator in hierarchy
'minimum_hierarchy_level'       // Minimum org chart level required
'override_individual_limits'    // Can exceed staff ceiling limits
```

**Advanced Workflow Features:**
```php
'approval_timeout_days'         // Auto-escalate after X days
'timeout_action'               // revert, escalate, auto_approve
'escalation_approval_rule_id'  // Rule to escalate to
'rejection_target_status'      // Where to go if rejected
'requires_comment_on_rejection' // Force comments on rejection
```

### Business Logic Examples

#### Single Approver
```php
// Manager approves up to $10,000 purchase requests
Approval::create([
    'code' => 'MGR_PR_10K',
    'document_type' => 'purchase_request',
    'action' => 'approve',
    'approval_type' => 'single',
    'required_approvers' => 1,
    'ceiling_limit' => 10000,
    'staff_id' => $manager->id
]);
```

#### Quorum-based Approval
```php
// 3 out of 5 department heads must approve capital expenditure over $50K
Approval::create([
    'code' => 'CAPEX_QUORUM_3OF5',
    'document_type' => 'purchase_request',
    'action' => 'approve',
    'approval_type' => 'quorum',
    'required_approvers' => 3,
    'eligible_approvers' => 5,
    'assignment_strategy' => 'position_based',
    'eligible_position_ids' => [1, 2, 3, 4, 5], // 5 dept head positions
    'floor_limit' => 50000,
    'budget_type' => 'Capital'
]);
```

#### Position-based Assignment
```php
// Any Finance Manager at any site can approve budget transfers
Approval::create([
    'code' => 'FIN_MGR_BUDGET',
    'document_type' => 'budget_transfer',
    'assignment_strategy' => 'position_based',
    'is_position_based' => true,
    'eligible_position_ids' => [$financeManagerPosition->id],
    'allow_external_site_approvers' => true
]);
```

#### Escalation Chain
```php
// If department head doesn't approve in 5 days, escalate to VP
$vpRule = Approval::create([...]);

Approval::create([
    'code' => 'DEPT_HEAD_WITH_ESCALATION',
    'document_type' => 'expense_report',
    'approval_timeout_days' => 5,
    'timeout_action' => 'escalate',
    'escalation_approval_rule_id' => $vpRule->id
]);
```

### Integration with Workflow Plugin

The Organization plugin defines approval rules, while the Workflow plugin executes them:

```php
// 1. Organization defines the rule
$rule = Approval::getApplicableRule($document);

// 2. Workflow creates instance to track execution
$workflow = WorkflowInstance::create([
    'current_approval_rule_id' => $rule->id,
    'approvals_required' => $rule->required_approvers,
    'current_approval_type' => $rule->approval_type
]);

// 3. Workflow tracks individual actions
WorkflowAction::create([
    'workflow_instance_id' => $workflow->id,
    'approval_rule_id' => $rule->id,
    'action' => 'approve'
]);
```

### Staff Hierarchy and Approval Authority

#### Hierarchical Validation
```php
// Approval rule requires hierarchy validation
public function canApprove($staff, $document)
{
    if ($this->requires_hierarchy_validation) {
        return $staff->isAboveInHierarchy($document->created_by);
    }
    return true;
}
```

#### Site-based Authority
```php
// Staff can only approve for sites they're assigned to
public function hasApprovalAuthority($staff, $site)
{
    if ($this->site_id && $this->site_id !== $site->id) {
        return false;
    }
    
    return $staff->canAccessSite($site);
}
```

### Database Structure

#### Enhanced Approvals Table
```sql
-- Basic approval fields
code, document_type, action, floor_limit, ceiling_limit
from_status, to_status, staff_id, site_id

-- Multi-approver fields (MLAS)
approval_type, required_approvers, eligible_approvers
assignment_strategy, is_position_based, eligible_position_ids

-- Hierarchy and validation
requires_hierarchy_validation, minimum_hierarchy_level
override_individual_limits

-- Workflow integration
approval_timeout_days, timeout_action, escalation_approval_rule_id
rejection_target_status, requires_comment_on_rejection

-- Delegation and effectiveness
is_active, effective_from, effective_to
allows_delegation, max_delegation_days
```

### Configuration Examples

#### Department Budget Approvals
```yaml
# config/organization.php
approval_scenarios:
  department_budget:
    small_purchases:      # Under $1,000
      approval_type: single
      required_approvers: 1
      eligible_roles: [supervisor]
    
    medium_purchases:     # $1,000 - $10,000  
      approval_type: single
      required_approvers: 1
      eligible_roles: [department_manager]
    
    large_purchases:      # Over $10,000
      approval_type: quorum
      required_approvers: 2
      eligible_approvers: 3
      eligible_roles: [department_manager, finance_manager, operations_manager]
```

### Migration History

#### October 2025 - MLAS Integration
**Background**: The Workflow plugin originally contained a separate MLAS table that duplicated approval functionality.

**Changes**:
1. **Merged MLAS into Organization**: All approval definitions consolidated in `omsb_organization_approvals`
2. **Enhanced Approval Model**: Added multi-approver, quorum, position-based assignment capabilities  
3. **Workflow Integration**: Clear separation between approval definitions (Organization) and execution (Workflow)
4. **Backward Compatibility**: Existing approval rules enhanced, no breaking changes

**Benefits**:
- Single source of truth for all approval rules
- Eliminated duplication between plugins
- Enhanced multi-approver capabilities ("3 out of 5 approvers")
- Better integration with organizational hierarchy
- Support for complex enterprise approval scenarios

## Development Guidelines

When extending this plugin:
1. Follow OctoberCMS conventions for models, controllers, and views
2. Use validation traits for model validation
3. Use soft deletes for all models to maintain data integrity
4. Document relationships clearly in model comments
5. Use proper namespacing for cross-plugin references
6. Maintain backward compatibility when modifying database structure

## Future Enhancements

Potential areas for expansion:
- Staff management interface
- GL Account configuration
- Advanced reporting on organizational hierarchy
- Bulk import/export of companies and addresses
- Company contact management
- Document attachment support
- Approval rule templates and wizards
- Real-time approval dashboard
- Mobile approval interfaces
