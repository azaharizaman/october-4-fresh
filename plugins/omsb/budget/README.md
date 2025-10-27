# Budget Plugin

Budget management system for yearly budget planning with support for budget transactions (transfers, adjustments, reallocations) and budget utilization tracking.

## Overview

The Budget plugin manages yearly budgets that are typically entered before the end of the current year for the next year's budget. It provides comprehensive budget tracking with calculated fields that help determine if purchases exceed available budget.

**Key Characteristic:** Budget is **NOT** a controlled document and does not have running numbers from the Registrar plugin. The budget code is a free input field.

## Models

### Budget (Core Model)

Represents a yearly budget allocation for a specific GL account and site.

**Properties:**
- `budget_code` - Free input field (e.g., `BGT/SGH/FEMS/MTC/5080190/25`)
- `description` - Budget description (e.g., `SGH-MAINTENANCE COSTS-Mech - Fire protection system-FEMS`)
- `year` - Budget year (e.g., 2025)
- `effective_from` / `effective_to` - Effective period dates
- `allocated_amount` - Initial allocated budget amount
- `status` - Budget status (draft, approved, active, expired, cancelled)
- `gl_account_id` - References Organization GL Account
- `site_id` - References Organization Site
- `service_code` - Optional service department code
- `created_by` / `approved_by` - Backend user references
- `notes` - Additional notes

**Budget Code Format:**
The budget code follows a pattern like: `BGT/SGH/FEMS/MTC/5080190/25` where:
- `BGT` - Budget prefix
- `SGH` - Site code
- `FEMS` - Service code (Facilities & Engineering Management Services)
- `MTC` - Category (Maintenance)
- `5080190` - GL Account number
- `25` - Year (2025)

**Note:** The code format is not enforced by the system and remains a free input field for flexibility.

**Calculated Fields:**

The Budget model includes several calculated fields that are automatically computed:

1. **total_transferred_out** - Total amount transferred out to other budgets
2. **total_transferred_in** - Total amount transferred in from other budgets
3. **total_adjustments** - Total adjustments (can be positive or negative)
4. **total_reallocations** - Total reallocations (can be positive or negative)
5. **current_budget** - Current budget after all transactions:
   ```
   allocated_amount + total_transferred_in - total_transferred_out + total_adjustments + total_reallocations
   ```
6. **utilized_amount** - Total utilized from Purchase Orders (TODO: implement integration)
7. **available_balance** - Available budget balance:
   ```
   current_budget - utilized_amount
   ```
8. **utilization_percentage** - Percentage of budget utilized:
   ```
   (utilized_amount / current_budget) * 100
   ```

**Key Methods:**
- `hasSufficientBalance($amount)` - Check if budget has sufficient balance
- `wouldExceedBudget($amount)` - Check if adding amount would exceed budget
- `isEditable()` - Only editable in draft status
- `canApprove()` - Can be approved from draft status

**Scopes:**
- `active()` - Active budgets only
- `byYear($year)` - Filter by year
- `forSite($siteId)` - Filter by site
- `forGlAccount($glAccountId)` - Filter by GL account
- `byService($serviceCode)` - Filter by service
- `effectiveOn($date)` - Budgets effective on specific date

### Budget Transactions (Controlled Documents)

All budget transaction models are **controlled documents** and require document numbers from the Registrar plugin.

**Trait Integration:** All budget transaction models use the `HasFinancialDocumentProtection` trait from the Registrar plugin, which provides:
- Automatic document number generation
- Edit protection based on status
- Complete audit trails via DocumentRegistry
- Void handling instead of deletion
- Enhanced financial document protection
- Amount change tracking and validation
- Multi-level approval support

**Protected Statuses:** Documents in `approved`, `completed`, `cancelled`, or `voided` status cannot be edited.

**Document Type Codes:**
- BudgetTransfer: `BUDGET_TRANSFER`
- BudgetAdjustment: `BUDGET_ADJUSTMENT`
- BudgetReallocation: `BUDGET_REALLOCATION`

#### 1. BudgetTransfer

Manages intersite budget transfers (between different sites).

**Properties:**
- `document_number` - Unique document number from registrar (auto-generated)
- `registry_id` - Links to DocumentRegistry for audit trail
- `transfer_type` - Type: outward or inward
- `transfer_date` - Transfer date
- `amount` - Transfer amount
- `from_budget_id` - Source budget
- `to_budget_id` - Destination budget
- `reason` - Transfer justification
- `status` - Document status (draft, submitted, approved, rejected, cancelled, completed, voided)
- `is_voided`, `voided_at`, `voided_by`, `void_reason` - Voiding information

**Business Rules:**
- Must be between budgets at different sites (intersite)
- Source and destination budgets cannot be the same
- Validation enforces site difference
- Cannot be deleted, must be voided instead
- Amount cannot be changed after approval

#### 2. BudgetAdjustment

Manages budget amount modifications (increase or decrease).

**Properties:**
- `document_number` - Unique document number from registrar (auto-generated)
- `registry_id` - Links to DocumentRegistry for audit trail
- `adjustment_date` - Adjustment date
- `adjustment_amount` - Adjustment amount (positive for increase, negative for decrease)
- `adjustment_type` - Type: increase or decrease
- `budget_id` - Affected budget
- `reason` - Adjustment justification (required)
- `status` - Document status (draft, submitted, approved, rejected, cancelled, completed, voided)
- `is_voided`, `voided_at`, `voided_by`, `void_reason` - Voiding information

**Business Rules:**
- Amount is automatically normalized based on type:
  - Increase → positive amount
  - Decrease → negative amount
- Cannot be deleted, must be voided instead
- Amount cannot be changed after approval

#### 3. BudgetReallocation

Manages budget reallocations within the same site between different GL accounts.

**Properties:**
- `document_number` - Unique document number from registrar (auto-generated)
- `registry_id` - Links to DocumentRegistry for audit trail
- `reallocation_date` - Reallocation date
- `amount` - Reallocation amount
- `budget_id` - Affected budget
- `from_gl_account_id` - Source GL account
- `to_gl_account_id` - Destination GL account
- `reason` - Reallocation justification (required)
- `status` - Document status (draft, submitted, approved, rejected, cancelled, completed, voided)
- `is_voided`, `voided_at`, `voided_by`, `void_reason` - Voiding information

**Business Rules:**
- Can only be within the same site (not intersite)
- Both GL accounts must belong to the budget's site
- Source and destination GL accounts must be different
- Cannot be deleted, must be voided instead
- Amount cannot be changed after approval
- Validation enforces same-site requirement

## Integration Points

### Organization Plugin
- **Sites**: Budgets belong to specific organizational sites
- **GL Accounts**: Budgets are associated with GL accounts for financial tracking
- **Service Settings**: Optional service code for department-specific budgets
- **Staff/Approval System**: Creator and approver tracking

### Registrar Plugin
- **Document Numbers**: All budget transactions (transfers, adjustments, reallocations) require document numbers
- **Document Patterns**: Each transaction type can have its own numbering pattern

### Workflow Plugin
- **Status Transitions**: Budget transactions follow workflow definitions
- **Approval Flows**: Multi-level approvals based on amount and hierarchy

### Procurement Plugin (Future Integration)
- **Budget Utilization**: Track PO amounts against budgets
- **Exceed Budget Checks**: Determine if PO would exceed available budget
- **Non-budgeted Flow**: POs that exceed budget follow alternative approval flow

### Feeder Plugin
- **Activity Tracking**: All budget and transaction activities are logged
- **Audit Trail**: Complete history of budget changes and approvals

## Status Flow

### Budget Status Flow
1. **draft** → Can be edited and deleted
2. **approved** → Approved but not yet active
3. **active** → Currently active budget
4. **expired** → Past effective date
5. **cancelled** → Cancelled budget

### Transaction Status Flow (All Transaction Types)
1. **draft** → Can be edited and deleted
2. **submitted** → Submitted for approval
3. **approved** → Approved and effective
4. **rejected** → Rejected (can be revised)
5. **cancelled** → Cancelled
6. **completed** → Completed and closed

## Permissions

- `omsb.budget.access_all` - Full access to budget management
- `omsb.budget.manage_budgets` - Manage budgets
- `omsb.budget.budget_transfers` - Manage budget transfers
- `omsb.budget.budget_adjustments` - Manage budget adjustments
- `omsb.budget.budget_reallocations` - Manage budget reallocations
- `omsb.budget.view_reports` - View budget reports

## Database Schema

### Tables
- `omsb_budget_budgets` - Core budget table
- `omsb_budget_transfers` - Budget transfer transactions
- `omsb_budget_adjustments` - Budget adjustment transactions
- `omsb_budget_reallocations` - Budget reallocation transactions

### Key Relationships
- Budget → GL Account (belongs to)
- Budget → Site (belongs to)
- Budget → BudgetTransfer (has many outward/inward)
- Budget → BudgetAdjustment (has many)
- Budget → BudgetReallocation (has many)

## Usage Examples

### Creating a Budget
```php
$budget = Budget::create([
    'budget_code' => 'BGT/SGH/FEMS/MTC/5080190/25',
    'description' => 'SGH-MAINTENANCE COSTS-Mech - Fire protection system-FEMS',
    'year' => 2025,
    'effective_from' => '2025-01-01',
    'effective_to' => '2025-12-31',
    'allocated_amount' => 100000.00,
    'status' => 'draft',
    'gl_account_id' => 1,
    'site_id' => 1,
    'service_code' => 'FEMS'
]);
```

### Checking Budget Availability
```php
$budget = Budget::find(1);

// Check if sufficient balance for PO
if ($budget->hasSufficientBalance(5000.00)) {
    // Proceed with PO creation
} else {
    // Follow non-budgeted approval flow
}

// Get budget details
$availableBalance = $budget->available_balance;
$utilizationRate = $budget->utilization_percentage;
$currentBudget = $budget->current_budget;
```

### Creating Budget Transfer (Intersite)
```php
$transfer = BudgetTransfer::create([
    'document_number' => 'BT-2025-00001', // From registrar
    'transfer_type' => 'outward',
    'transfer_date' => now(),
    'amount' => 10000.00,
    'from_budget_id' => 1, // Site A budget
    'to_budget_id' => 2,   // Site B budget
    'reason' => 'Transfer for emergency maintenance',
    'status' => 'draft'
]);
```

### Creating Budget Adjustment
```php
$adjustment = BudgetAdjustment::create([
    'document_number' => 'BA-2025-00001', // From registrar
    'adjustment_date' => now(),
    'adjustment_amount' => 5000.00,
    'adjustment_type' => 'increase',
    'budget_id' => 1,
    'reason' => 'Additional allocation for equipment purchase',
    'status' => 'draft'
]);
```

### Creating Budget Reallocation (Same Site)
```php
$reallocation = BudgetReallocation::create([
    'document_number' => 'BR-2025-00001', // From registrar
    'reallocation_date' => now(),
    'amount' => 3000.00,
    'budget_id' => 1,
    'from_gl_account_id' => 100, // Same site
    'to_gl_account_id' => 101,   // Same site
    'reason' => 'Reallocation from maintenance to repairs',
    'status' => 'draft'
]);
```

## Future Enhancements

### Phase 1 (Current Implementation)
- [x] Core Budget model with properties
- [x] Budget transaction models (Transfer, Adjustment, Reallocation)
- [x] Calculated fields for budget tracking
- [x] Database migrations and relationships
- [x] Model validation and business rules

### Phase 2 (Pending)
- [x] Backend controllers and views
- [x] YAML configurations for forms and lists
- [x] Integration with Registrar for document numbers
- [ ] Workflow integration for approval flows

### Phase 3 (Future)
- [ ] Budget utilization tracking from Purchase Orders
- [ ] Non-budgeted purchase approval flow
- [ ] Budget reports and analytics
- [ ] Budget forecasting and planning tools
- [ ] Budget performance dashboard
- [ ] Multi-year budget comparison

## Notes

- Budget code is intentionally kept as free input for flexibility
- All calculated fields are computed on-the-fly (not stored in database)
- Budget transactions require separate document numbers from Registrar
- Intersite transfers vs same-site reallocations are strictly enforced
- Future PO integration will enable automatic budget utilization tracking
