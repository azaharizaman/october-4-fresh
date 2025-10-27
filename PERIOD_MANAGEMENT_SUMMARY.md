# Period Management - Quick Summary

## What You Already Have

### ✅ InventoryPeriod (Fully Implemented)
- **Location**: `plugins/omsb/inventory/models/InventoryPeriod.php`
- **Purpose**: Manages operational inventory (stock movements, valuations, physical counts)
- **Features**:
  - Monthly/quarterly/yearly periods
  - FIFO/LIFO/Average Cost valuation methods
  - Status: open → closing → closed → locked
  - Ledger entry locking on close
  - Opening balance transfers
  - Adjustment periods
  - Reopen capability (if not locked)

## What I Just Created

### 🆕 FinancialPeriod (New Implementation)
- **Location**: `plugins/omsb/organization/models/FinancialPeriod.php`
- **Purpose**: Manages financial/accounting compliance (GL posting, budgets, AP/AR)
- **Features**:
  - Monthly/quarterly/yearly periods with fiscal year tracking
  - Multi-stage closing: draft → open → soft_closed → closed → locked
  - Soft close: AP/AR closes, GL stays open for adjustments
  - Year-end adjustment periods (13th period for Dec 31 adjustments)
  - Backdated posting control
  - Period overlap validation
  - Comprehensive audit trail (who closed/locked, when, notes)

### 🔧 HasFinancialPeriodValidation Trait
- **Location**: `plugins/omsb/organization/traits/HasFinancialPeriodValidation.php`
- **Purpose**: Automatic validation for any model with financial transactions
- **Usage**: Add to models like `BudgetTransaction`, `PurchaseOrder`, `Invoice`
- **Validates**:
  - Transaction date falls within defined period
  - Period is open (if required)
  - Backdating rules respected
  - Auto-assigns `financial_period_id` if field exists

## Files Created

1. **Model**: `plugins/omsb/organization/models/FinancialPeriod.php`
2. **Migration**: `plugins/omsb/organization/updates/create_financial_periods_table.php`
3. **Trait**: `plugins/omsb/organization/traits/HasFinancialPeriodValidation.php`
4. **Documentation**: `FINANCIAL_PERIOD_IMPLEMENTATION.md` (comprehensive guide)
5. **Updated**: `plugins/omsb/organization/updates/version.yaml` (v1.0.6)

## Key Differences: Inventory vs Financial Periods

| Aspect | InventoryPeriod | FinancialPeriod |
|--------|-----------------|-----------------|
| **Focus** | Stock accuracy | Financial compliance |
| **Closes** | 2-3 days after month-end | 10-15 days after month-end |
| **Soft Close** | No | Yes (AP/AR vs GL) |
| **13th Period** | No | Yes (year-end adjustments) |
| **Valuation** | FIFO/LIFO/Avg | N/A |
| **Integrates With** | Warehouses, GRN, MRI | Budgets, PO, Invoices, GL |

## Why Two Systems?

**Operational vs Accounting:**
- Inventory teams need to close stock movements FAST (2-3 days)
- Finance teams need time for accruals, adjustments, reconciliations (10-15 days)
- Different locking rules (inventory: lock ledger entries; finance: lock ALL transactions)

**Relationship:**
```
FinancialPeriod: Q1 2025 (Jan-Mar) [QUARTERLY]
    ├── InventoryPeriod: 2025-01 [MONTHLY]
    ├── InventoryPeriod: 2025-02 [MONTHLY]
    └── InventoryPeriod: 2025-03 [MONTHLY]
```

## Next Steps

### Immediate (Do First)
1. ✅ Run migration: `php artisan october:migrate`
2. ✅ Create initial periods (see FINANCIAL_PERIOD_IMPLEMENTATION.md "Period Creation Strategy")
3. ✅ Add permission checks in Organization plugin's Plugin.php

### Budget Plugin Integration (Priority)
1. Add `financial_period_id` column to `omsb_budget_budgets` table
2. Add `financial_period_id` column to `omsb_budget_budget_transactions` table
3. Add `HasFinancialPeriodValidation` trait to `BudgetTransaction` model
4. Update budget allocation forms to select financial period
5. Add period filter to budget reports

### Procurement Plugin Integration
1. Add `financial_period_id` to Purchase Orders
2. Add `financial_period_id` to Purchase Invoices
3. Add `HasFinancialPeriodValidation` trait to both models
4. Validate PO/Invoice dates against open periods

### Inventory Integration
1. Coordinate closing sequence (inventory first, financial second)
2. Add dual-period validation to GRN (check both inventory AND financial periods)
3. COGS posting respects financial period locks

## Testing Checklist

- [ ] Create monthly period FY2025-01 (Jan 1-31, 2025)
- [ ] Activate period (status = 'open')
- [ ] Create budget transaction with date in Jan 2025 → Should succeed
- [ ] Soft-close period
- [ ] Try creating another transaction → Should fail (AP/AR closed)
- [ ] Close period fully
- [ ] Try editing existing transaction → Should fail (period closed)
- [ ] Lock period
- [ ] Try reopening → Should fail (locked periods cannot reopen)

## Month-End Close Workflow

**Recommended Sequence:**
```
Day 32-34: Close Inventory Period
  └── php artisan omsb:close-inventory-period 2025-01

Day 35-40: Soft Close Financial Period
  └── FinancialPeriod::find($id)->softClose()
  └── AP/AR teams finalize invoices/payments
  └── GL team posts accruals, adjustments

Day 41-45: Full Close Financial Period
  └── FinancialPeriod::find($id)->close()
  └── Generate financial statements
  └── Management review

Day 46+: Lock Both Periods
  └── InventoryPeriod::find($id)->lock()
  └── FinancialPeriod::find($id)->lock()
  └── Audit finalized, immutable
```

## Common Use Cases

### 1. Budget Variance by Period
```php
$period = FinancialPeriod::where('period_code', 'FY2025-Q1')->first();
$budgets = Budget::where('financial_period_id', $period->id)->get();
$actuals = BudgetTransaction::whereBetween('transaction_date', 
    [$period->start_date, $period->end_date])->sum('amount');
$variance = $budgets->sum('amount') - $actuals;
```

### 2. Year-End Adjustments
```php
// Create 13th period for year-end adjustments
FinancialPeriod::create([
    'period_code' => 'FY2025-13',
    'period_name' => 'Year-End Adjustments FY2025',
    'start_date' => '2025-12-31',
    'end_date' => '2025-12-31',
    'fiscal_year' => 2025,
    'fiscal_month' => 13,
    'is_year_end' => true,
    'status' => 'open'
]);

// Post depreciation, audit adjustments, etc.
```

### 3. Prevent Backdated Transactions
```php
// In BudgetTransaction model
use HasFinancialPeriodValidation;

// Automatically validates on save:
$txn = new BudgetTransaction([
    'transaction_date' => '2024-12-15', // Dec 2024
    'amount' => 5000
]);
$txn->save(); 
// ❌ Throws ValidationException if FY2024-12 is closed
```

## Reference Documentation

- **Full Guide**: `/FINANCIAL_PERIOD_IMPLEMENTATION.md`
- **Model Code**: `plugins/omsb/organization/models/FinancialPeriod.php`
- **Trait Code**: `plugins/omsb/organization/traits/HasFinancialPeriodValidation.php`
- **InventoryPeriod**: `plugins/omsb/inventory/models/InventoryPeriod.php` (existing)

## Support

For questions or issues:
1. Review `FINANCIAL_PERIOD_IMPLEMENTATION.md` (comprehensive guide with examples)
2. Check existing `InventoryPeriod` implementation as reference
3. See OctoberCMS docs: https://docs.octobercms.com/4.x/extend/database/model.html
