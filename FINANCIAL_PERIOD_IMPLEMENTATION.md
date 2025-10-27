# Financial Period Management - Implementation Guide

## Overview

This document describes the **dual-period architecture** implemented in OMSB domain:

1. **InventoryPeriod** (Inventory plugin) - Operational inventory management
2. **FinancialPeriod** (Organization plugin) - Financial accounting compliance

---

## Architecture Decision: Why Two Period Systems?

### Separation of Concerns

```
┌─────────────────────────────────────────────────────────────────┐
│                    OMSB Period Architecture                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  InventoryPeriod (Operational)    FinancialPeriod (Accounting)  │
│  ════════════════════════════     ═══════════════════════════   │
│                                                                   │
│  • Stock movements                • GL posting control           │
│  • Valuation (FIFO/LIFO/Avg)      • Budget period alignment     │
│  • Physical counts                • AP/AR cycle management       │
│  • Warehouse operations           • Financial statement close   │
│  • Month-end inventory close      • Audit compliance             │
│                                                                   │
│  Closes: 2-3 days after month     Closes: 10-15 days after      │
│  Focus: Inventory accuracy        Focus: Financial reporting     │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Relationship Pattern

**Time Granularity Difference:**
```
FinancialPeriod: FY2025-Q1 (Jan 1 - Mar 31) [QUARTERLY]
    ├── InventoryPeriod: 2025-01 (Jan 1 - Jan 31) [MONTHLY]
    ├── InventoryPeriod: 2025-02 (Feb 1 - Feb 28) [MONTHLY]
    └── InventoryPeriod: 2025-03 (Mar 1 - Mar 31) [MONTHLY]
```

**Closing Sequence:**
```
Day 1-31: Operations occur
Day 32-34: Inventory closes (lock stock movements, calculate valuations)
Day 35-45: Financial closes (lock GL, finalize statements, audit review)
Day 46+: Both periods locked
```

---

## InventoryPeriod (Existing Implementation)

### Location
`plugins/omsb/inventory/models/InventoryPeriod.php`

### Purpose
Manages **operational inventory accuracy** and **stock valuation** for warehouses.

### Features
- ✅ Period types: **monthly, quarterly, yearly**
- ✅ Valuation methods: **FIFO, LIFO, Average Cost**
- ✅ Status lifecycle: `open → closing → closed → locked`
- ✅ Ledger entry locking (prevents backdated stock movements)
- ✅ Adjustment periods (year-end inventory adjustments)
- ✅ Physical count tracking
- ✅ Opening balance transfers from previous period

### Key Operations
```php
$period = InventoryPeriod::current()->first();

// Close month-end
$period->close(); 
// - Locks all InventoryLedger entries for this period
// - Calculates closing stock valuations
// - Generates InventoryValuation records
// - Transfers closing balance to next period as opening balance

// Lock permanently (after audit)
$period->lock();
// - Prevents any modifications
// - Period cannot be reopened once locked

// Reopen (admin override)
$period->reopen(); // Only if not locked
```

### Integration Points
- **Goods Receipt Notes**: Stock receipts create ledger entries in current open period
- **Material Request Issuance**: Stock issues create ledger entries
- **Stock Adjustments**: Qty corrections recorded in open period
- **Physical Counts**: Variance adjustments posted to period
- **Valuation Reports**: Generated based on closed period data

---

## FinancialPeriod (New Implementation)

### Location
`plugins/omsb/organization/models/FinancialPeriod.php`

### Purpose
Manages **financial accounting compliance** and **GL posting control** across all modules.

### Features
- ✅ Period types: **monthly, quarterly, yearly**
- ✅ Multi-stage closing: `draft → open → soft_closed → closed → locked`
- ✅ Soft close (AP/AR close, GL still open for adjustments)
- ✅ Full close (all posting stops)
- ✅ Permanent lock (audit finalized)
- ✅ Year-end adjustment periods (13th period)
- ✅ Backdated posting control
- ✅ Fiscal year tracking
- ✅ Opening balance management via `previous_period_id`

### Status Workflow

```
draft           → Period created but not active (setup phase)
  ↓ activate
open            → All transactions allowed (normal operations)
  ↓ soft_close
soft_closed     → AP/AR closed, GL still open for adjustments
  ↓ close
closed          → No new transactions, period finalized
  ↓ lock
locked          → Permanent lock (cannot reopen, audit complete)
```

### Posting Rules by Status

| Status       | AP/AR Posting | GL Posting | Budget Posting | Can Reopen |
|--------------|---------------|------------|----------------|------------|
| draft        | ❌            | ❌         | ❌             | N/A        |
| open         | ✅            | ✅         | ✅             | N/A        |
| soft_closed  | ❌            | ✅         | ✅             | ✅ Yes     |
| closed       | ❌            | ❌         | ❌             | ✅ Yes     |
| locked       | ❌            | ❌         | ❌             | ❌ No      |

### Key Methods

```php
$period = FinancialPeriod::getCurrentPeriod();

// Check posting permissions
$period->allowsPosting();      // true if open or soft_closed
$period->allowsGLPosting();    // true if open only
$period->allowsAPARPosting();  // true if open only
$period->allowsBackdating();   // true if open AND flag enabled

// Closing workflow
$period->softClose();  // Step 1: Close AP/AR (payables/receivables)
$period->close();      // Step 2: Close GL (all posting stops)
$period->lock();       // Step 3: Permanent lock (audit finalized)

// Admin override
$period->reopen();     // Only if not locked
```

### Database Schema

```sql
CREATE TABLE omsb_organization_financial_periods (
    id BIGINT PRIMARY KEY,
    period_code VARCHAR(30) UNIQUE,           -- 'FY2025-Q1', 'FY2025-01'
    period_name VARCHAR(255),                 -- 'Q1 FY2025', 'January FY2025'
    period_type ENUM('monthly','quarterly','yearly'),
    start_date DATE,
    end_date DATE,
    fiscal_year SMALLINT,                     -- 2025
    fiscal_quarter TINYINT,                   -- 1-4 (for quarterly)
    fiscal_month TINYINT,                     -- 1-13 (13 for year-end adjustments)
    status ENUM('draft','open','soft_closed','closed','locked'),
    is_year_end BOOLEAN,                      -- true for 13th period
    allow_backdated_posting BOOLEAN,
    soft_closed_at TIMESTAMP,
    closed_at TIMESTAMP,
    locked_at TIMESTAMP,
    soft_closed_by INT REFERENCES backend_users(id),
    closed_by INT REFERENCES backend_users(id),
    locked_by INT REFERENCES backend_users(id),
    closing_notes TEXT,
    previous_period_id BIGINT REFERENCES omsb_organization_financial_periods(id),
    created_by INT REFERENCES backend_users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

---

## Integration with Controlled Documents

### HasFinancialPeriodValidation Trait

Apply this trait to any model with financial impact:

```php
use Omsb\Organization\Traits\HasFinancialPeriodValidation;

class BudgetTransaction extends Model
{
    use HasFinancialPeriodValidation;
    
    // Configure (optional, defaults shown)
    protected $financialDateField = 'transaction_date';  // Field to validate
    protected $requireOpenPeriod = true;                 // Require open period
}
```

### Validation Logic

**Automatic validation on save/update:**
1. Finds financial period containing transaction date
2. Throws `ValidationException` if:
   - No period exists for date
   - Period is closed/locked (if `requireOpenPeriod = true`)
   - Backdating not allowed and date < period start
3. Auto-assigns `financial_period_id` if field exists

**Usage example:**
```php
$transaction = new BudgetTransaction([
    'transaction_date' => '2025-02-15',
    'amount' => 5000
]);

$transaction->save(); 
// ✅ Success if FY2025-02 is open
// ❌ ValidationException: "Financial period FY2025-02 is closed. Cannot post transactions."
```

### Helper Methods

```php
// Check if transaction can be edited
$transaction->canEditBasedOnPeriod();    // false if period closed/locked

// Check if transaction can be deleted
$transaction->canDeleteBasedOnPeriod();  // false if period closed/locked

// Get associated period
$period = $transaction->financial_period; // Accessor method
```

---

## Recommended Plugin Integrations

### 1. Budget Plugin

**Update `Budget` model:**
```php
// Add financial_period_id to budgets table
Schema::table('omsb_budget_budgets', function (Blueprint $table) {
    $table->unsignedBigInteger('financial_period_id')->nullable()->after('fiscal_year');
    $table->foreign('financial_period_id')->references('id')->on('omsb_organization_financial_periods')->nullOnDelete();
});

// Add relationship
public $belongsTo = [
    'financial_period' => [FinancialPeriod::class, 'key' => 'financial_period_id']
];
```

**Update `BudgetTransaction` model:**
```php
use Omsb\Organization\Traits\HasFinancialPeriodValidation;

class BudgetTransaction extends Model
{
    use HasFinancialPeriodValidation;
    use HasControlledDocumentNumber; // existing
    
    protected $financialDateField = 'transaction_date';
    protected $requireOpenPeriod = true;
}
```

### 2. Procurement Plugin

**Add to `PurchaseOrder` and `PurchaseInvoice`:**
```php
use Omsb\Organization\Traits\HasFinancialPeriodValidation;

class PurchaseOrder extends Model
{
    use HasFinancialPeriodValidation;
    
    protected $financialDateField = 'order_date';
    protected $requireOpenPeriod = true; // Cannot create PO in closed period
}

class PurchaseInvoice extends Model
{
    use HasFinancialPeriodValidation;
    
    protected $financialDateField = 'invoice_date';
    protected $requireOpenPeriod = true; // Cannot post invoice in closed period
}
```

### 3. Inventory Plugin

**Coordinate with InventoryPeriod:**
```php
// In Goods Receipt Note
use Omsb\Organization\Traits\HasFinancialPeriodValidation;

class GoodsReceiptNote extends Model
{
    use HasFinancialPeriodValidation;
    
    protected $financialDateField = 'receipt_date';
    protected $requireOpenPeriod = true;
    
    // Validate both financial AND inventory periods
    public function validateFinancialPeriod(): void
    {
        parent::validateFinancialPeriod();
        
        // Also check inventory period
        $invPeriod = InventoryPeriod::current()->first();
        if (!$invPeriod || $invPeriod->status !== 'open') {
            throw new ValidationException([
                'receipt_date' => 'Inventory period is closed. Cannot receive goods.'
            ]);
        }
    }
}
```

---

## Month-End Close Sequence

### Recommended Workflow

**Days 1-31: Normal Operations**
- All transactions post to current open periods
- Both InventoryPeriod and FinancialPeriod are `open`

**Days 32-34: Inventory Close**
```php
$invPeriod = InventoryPeriod::where('period_code', '2025-01')->first();
$invPeriod->close();
// - Locks all stock movements for Jan 2025
// - Calculates closing valuations (FIFO/LIFO/Avg)
// - Generates InventoryValuation records
// - Transfers closing QoH to Feb 2025 opening balance
```

**Days 35-40: Financial Soft Close**
```php
$finPeriod = FinancialPeriod::where('period_code', 'FY2025-01')->first();
$finPeriod->softClose();
// - Closes AP/AR (no more vendor invoices, customer payments)
// - GL still open for accruals, adjustments, reclassifications
// - Budget transfers still allowed
```

**Days 41-45: Financial Full Close**
```php
$finPeriod->close();
// - All GL posting stops
// - Budget transactions frozen
// - Financial statements finalized
// - Ready for management review
```

**Days 46+: Lock Both Periods**
```php
$invPeriod->lock();   // Inventory permanent lock
$finPeriod->lock();   // Financial permanent lock
// - Both periods immutable
// - Audit trail preserved
// - Cannot reopen
```

---

## Year-End Adjustment Periods

### The 13th Period Concept

Financial accounting often needs a "13th period" for year-end adjustments after Dec 31:

```php
// Create year-end adjustment period
$yearEndPeriod = FinancialPeriod::create([
    'period_code' => 'FY2025-13',
    'period_name' => 'Year-End Adjustments FY2025',
    'period_type' => 'monthly',
    'start_date' => '2025-12-31',      // Same as Dec end
    'end_date' => '2025-12-31',        // Same day
    'fiscal_year' => 2025,
    'fiscal_month' => 13,              // 13th period indicator
    'is_year_end' => true,             // Flag for reporting
    'status' => 'open',
    'previous_period_id' => $dec2025->id
]);
```

**Use cases:**
- Depreciation adjustments
- Accrual reversals
- Audit adjustments
- Inventory reserve adjustments
- Bad debt write-offs

**Workflow:**
1. Close regular Dec 2025 period
2. Open FY2025-13 period
3. Post year-end adjustments (backdated to Dec 31)
4. Close FY2025-13 period
5. Lock entire FY2025 fiscal year
6. Open FY2026-01 period

---

## Reporting Considerations

### Period-Based Reports

**Budget Variance Reports:**
```php
// Get budget vs actual for Q1 FY2025
$period = FinancialPeriod::where('period_code', 'FY2025-Q1')->first();
$budgets = Budget::where('financial_period_id', $period->id)->get();
$actuals = BudgetTransaction::whereBetween('transaction_date', [$period->start_date, $period->end_date])->sum('amount');
```

**Inventory Valuation Reports:**
```php
// Get inventory valuation for Jan 2025
$invPeriod = InventoryPeriod::where('period_code', '2025-01')->first();
$valuations = InventoryValuation::where('inventory_period_id', $invPeriod->id)->get();
```

**Financial Statement Extraction:**
```php
// Income Statement for Q1 FY2025
$period = FinancialPeriod::where('period_code', 'FY2025-Q1')->first();
$revenue = GLTransaction::where('financial_period_id', $period->id)
    ->where('account_type', 'revenue')
    ->sum('amount');
```

---

## Migration Path for Existing Data

### Step 1: Add financial_period_id to existing tables

```php
// Budget plugin
Schema::table('omsb_budget_budgets', function (Blueprint $table) {
    $table->unsignedBigInteger('financial_period_id')->nullable()->after('fiscal_year');
    $table->foreign('financial_period_id')->references('id')->on('omsb_organization_financial_periods')->nullOnDelete();
});

Schema::table('omsb_budget_budget_transactions', function (Blueprint $table) {
    $table->unsignedBigInteger('financial_period_id')->nullable()->after('transaction_date');
    $table->foreign('financial_period_id')->references('id')->on('omsb_organization_financial_periods')->nullOnDelete();
});

// Procurement plugin
Schema::table('omsb_procurement_purchase_orders', function (Blueprint $table) {
    $table->unsignedBigInteger('financial_period_id')->nullable()->after('order_date');
    $table->foreign('financial_period_id')->references('id')->on('omsb_organization_financial_periods')->nullOnDelete();
});
```

### Step 2: Backfill existing records

```php
// Assign periods to existing budgets based on fiscal_year
$budgets = Budget::whereNull('financial_period_id')->get();
foreach ($budgets as $budget) {
    $period = FinancialPeriod::where('fiscal_year', $budget->fiscal_year)
        ->where('period_type', 'yearly')
        ->first();
    if ($period) {
        $budget->financial_period_id = $period->id;
        $budget->save();
    }
}

// Assign periods to existing transactions based on transaction_date
$transactions = BudgetTransaction::whereNull('financial_period_id')->get();
foreach ($transactions as $txn) {
    $period = FinancialPeriod::where('start_date', '<=', $txn->transaction_date)
        ->where('end_date', '>=', $txn->transaction_date)
        ->first();
    if ($period) {
        $txn->financial_period_id = $period->id;
        $txn->save();
    }
}
```

---

## Backend Controllers and Views

### FinancialPeriodsController

Create controller for period management:

```php
<?php namespace Omsb\Organization\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class FinancialPeriods extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];
    
    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Omsb.Organization', 'organization', 'financialperiods');
    }
    
    // Month-end closing action
    public function onSoftClose($recordId)
    {
        $period = $this->formFindModelObject($recordId);
        $period->softClose();
        Flash::success('Period soft-closed successfully');
        return $this->listRefresh();
    }
    
    public function onClose($recordId)
    {
        $period = $this->formFindModelObject($recordId);
        $period->close();
        Flash::success('Period closed successfully');
        return $this->listRefresh();
    }
    
    public function onLock($recordId)
    {
        $period = $this->formFindModelObject($recordId);
        $period->lock();
        Flash::success('Period locked permanently');
        return $this->listRefresh();
    }
    
    public function onReopen($recordId)
    {
        $period = $this->formFindModelObject($recordId);
        $period->reopen();
        Flash::success('Period reopened');
        return $this->listRefresh();
    }
}
```

### List Configuration (config_list.yaml)

```yaml
title: Financial Periods
list: $/omsb/organization/models/financialperiod/columns.yaml
modelClass: Omsb\Organization\Models\FinancialPeriod
recordUrl: omsb/organization/financialperiods/update/:id
noRecordsMessage: No financial periods found
showSetup: true
showCheckboxes: false
defaultSort:
    column: start_date
    direction: desc
toolbar:
    buttons: list_toolbar
    search:
        prompt: Search periods...
```

### Form Configuration (config_form.yaml)

```yaml
name: Financial Period
form: $/omsb/organization/models/financialperiod/fields.yaml
modelClass: Omsb\Organization\Models\FinancialPeriod
defaultRedirect: omsb/organization/financialperiods
create:
    title: Create Financial Period
    redirect: omsb/organization/financialperiods/update/:id
    redirectClose: omsb/organization/financialperiods
update:
    title: Edit Financial Period
    redirect: omsb/organization/financialperiods
    redirectClose: omsb/organization/financialperiods
```

---

## Best Practices

### 1. Period Creation Strategy

**Option A: Pre-create all periods for fiscal year**
```php
// Create all 12 monthly periods for FY2025 at once
for ($month = 1; $month <= 12; $month++) {
    FinancialPeriod::create([
        'period_code' => "FY2025-{$month}",
        'period_name' => Carbon::createFromDate(2025, $month, 1)->format('F Y'),
        'period_type' => 'monthly',
        'start_date' => Carbon::createFromDate(2025, $month, 1)->startOfMonth(),
        'end_date' => Carbon::createFromDate(2025, $month, 1)->endOfMonth(),
        'fiscal_year' => 2025,
        'fiscal_month' => $month,
        'status' => 'draft', // Activate month by month
    ]);
}
```

**Option B: Just-in-time period creation**
- Only create next month's period when current month closes
- More flexible but requires automation

### 2. Access Control

Restrict period management to finance/admin roles:

```php
// In Plugin.php
public function registerPermissions()
{
    return [
        'omsb.organization.manage_financial_periods' => [
            'tab' => 'Organization',
            'label' => 'Manage Financial Periods'
        ],
        'omsb.organization.close_financial_periods' => [
            'tab' => 'Organization',
            'label' => 'Close/Lock Financial Periods'
        ]
    ];
}
```

### 3. Period Transition Automation

Create console command for automated month-end:

```php
// php artisan omsb:close-financial-period FY2025-01
class CloseFinancialPeriod extends Command
{
    protected $signature = 'omsb:close-financial-period {period_code}';
    
    public function handle()
    {
        $period = FinancialPeriod::where('period_code', $this->argument('period_code'))->first();
        
        // Pre-flight checks
        if (!$period->canClose()) {
            $this->error('Period cannot be closed: ' . $period->getCloseBlockers());
            return 1;
        }
        
        // Close sequence
        $this->info('Soft closing AP/AR...');
        $period->softClose();
        
        $this->info('Running month-end reports...');
        // Generate reports
        
        if ($this->confirm('Proceed with full close?')) {
            $period->close();
            $this->info('Period closed successfully');
        }
        
        return 0;
    }
}
```

---

## Troubleshooting

### Issue: "No financial period exists for date"

**Cause:** Transaction date falls outside any defined period.

**Solution:** Create missing period or adjust transaction date.

### Issue: "Period is closed. Cannot post transactions."

**Cause:** Attempting to post to closed/locked period.

**Solution:** 
1. Admin reopens period (if not locked)
2. Post to current open period instead
3. Use year-end adjustment period for backdated corrections

### Issue: Inventory and financial periods out of sync

**Cause:** Inventory closed but financial still open (or vice versa).

**Solution:** Follow recommended close sequence:
1. Close inventory first (day 32-34)
2. Then close financial (day 35-45)
3. Lock both together (day 46+)

---

## Summary

| Feature | InventoryPeriod | FinancialPeriod |
|---------|-----------------|-----------------|
| **Owner** | Inventory plugin | Organization plugin |
| **Purpose** | Stock accuracy & valuation | Financial compliance & GL control |
| **Granularity** | Monthly (usually) | Monthly/Quarterly/Yearly |
| **Closes First** | Yes (2-3 days after month) | No (10-15 days after month) |
| **Locks** | InventoryLedger entries | All financial transactions |
| **Valuation** | FIFO/LIFO/Average Cost | N/A |
| **13th Period** | No | Yes (year-end adjustments) |
| **Soft Close** | No | Yes (AP/AR vs GL) |
| **Integration** | Warehouse operations | Budget, Procurement, GL, Reporting |

---

**Recommendation:** Implement FinancialPeriod now and integrate with Budget plugin first, then gradually roll out to Procurement and other financial modules.
