# Vendor Migration - Quick Reference

## Files Changed

### 1. Migration File
**Path:** `/plugins/omsb/procurement/updates/create_vendors_table.php`

**Changes:**
- Added 33 new columns for vendor classification, tax info, contact details, address, business info, and credit management
- Changed `status` from ENUM to VARCHAR for legacy compatibility
- Added 5 new indexes (type, is_bumi, is_specialized, is_approved, company_id)

### 2. Model File
**Path:** `/plugins/omsb/procurement/models/Vendor.php`

**Changes:**
- Extended `$fillable` array with 33 new fields
- Extended `$nullable` array with 27 new fields
- Added `$casts` array for 9 boolean/date/decimal fields
- Enhanced validation rules for new fields
- Added new scopes: `scopeBumi()`, `scopeSpecialized()`, `scopeApproved()`, `scopeByType()`
- Updated status checking methods for case-insensitive comparison

### 3. Seeder File (NEW)
**Path:** `/plugins/omsb/procurement/updates/seed_vendors_from_csv.php`

**Purpose:** Import vendor data from CSV file

**Features:**
- Reads from `raw_data/tsi_procurement_vendors_202510281231-procurement_vendors.csv`
- Skips soft-deleted records (deleted_at IS NOT NULL)
- Upserts vendors by code (updates existing, creates new)
- Preserves original timestamps from legacy system
- Transaction-based for data integrity
- Comprehensive data parsing and type conversion

### 4. Documentation Files (NEW)

**ARCHITECTURE_CHANGELOG.md** - Added comprehensive change log entry:
- Background and rationale
- Detailed schema changes
- Business logic impact examples
- Migration path and backward compatibility notes
- Future enhancement suggestions

**docs/migrations/vendor-schema-migration-guide.md** - Complete migration guide:
- Schema comparison (before/after)
- CSV file structure and column mapping
- Step-by-step migration procedure
- Data validation checklist
- Troubleshooting guide
- Post-migration tasks
- Rollback procedure

---

## New Database Columns

| Column | Type | Purpose |
|--------|------|---------|
| `incorporation_date` | DATE | Company incorporation date |
| `sap_code` | VARCHAR | SAP system integration code |
| `is_bumi` | BOOLEAN | Bumiputera vendor status |
| `type` | VARCHAR | Vendor type (Standard, Contractor, etc.) |
| `category` | VARCHAR | Vendor category |
| `is_specialized` | BOOLEAN | Specialized vendor flag |
| `is_precision` | BOOLEAN | Precision vendor flag |
| `is_approved` | BOOLEAN | Pre-approved vendor flag |
| `is_gst` | BOOLEAN | GST registered flag |
| `gst_number` | VARCHAR | GST registration number |
| `gst_type` | VARCHAR | GST type (SR, etc.) |
| `is_foreign` | BOOLEAN | Foreign vendor flag |
| `country_id` | INT | Current country ID |
| `origin_country_id` | INT | Origin country ID |
| `designation` | VARCHAR | Contact person job title |
| `tel_no` | VARCHAR | Telephone number |
| `fax_no` | VARCHAR | Fax number |
| `hp_no` | VARCHAR | Mobile number |
| `email` | VARCHAR | General email address |
| `street` | VARCHAR | Street address |
| `city` | VARCHAR | City |
| `state_id` | INT | State/region ID |
| `postcode` | VARCHAR | Postal code |
| `scope_of_work` | TEXT | Detailed scope of services |
| `service` | VARCHAR | Service category |
| `credit_limit` | DECIMAL(15,2) | Credit limit amount |
| `credit_terms` | VARCHAR | Credit payment terms |
| `credit_updated_at` | TIMESTAMP | Last credit review date |
| `credit_review` | VARCHAR | Credit review status |
| `credit_remarks` | TEXT | Credit management notes |
| `company_id` | INT | Company association (multi-company) |

---

## New Model Features

### New Scopes

```php
// Filter Bumiputera vendors
Vendor::bumi()->get();

// Filter specialized vendors
Vendor::specialized()->get();

// Filter approved vendors
Vendor::approved()->get();

// Filter by vendor type
Vendor::byType('Contractor')->get();
Vendor::byType('Standard Vendor')->get();
```

### Enhanced Status Checking

```php
// Case-insensitive status checking
$vendor->isActive();        // Works with 'Active', 'active', 'ACTIVE'
$vendor->isBlacklisted();   // Works with 'Blacklisted', 'blacklisted', etc.
```

### New Casts

```php
$vendor->is_bumi;              // Boolean
$vendor->is_specialized;       // Boolean
$vendor->is_gst;              // Boolean
$vendor->credit_limit;        // Decimal(2)
$vendor->incorporation_date;  // Carbon date
$vendor->credit_updated_at;   // Carbon datetime
```

---

## Migration Commands

```bash
# 1. Refresh plugin (runs migration)
php artisan plugin:refresh Omsb.Procurement

# 2. Import vendor data from CSV
php artisan db:seed --class="Omsb\Procurement\Updates\SeedVendorsFromCsv"

# 3. Verify import
mysql -u root -p railwayfour -e "SELECT COUNT(*) FROM omsb_procurement_vendors;"
```

---

## Validation Queries

```sql
-- Check vendor type distribution
SELECT type, COUNT(*) as count 
FROM omsb_procurement_vendors 
GROUP BY type 
ORDER BY count DESC;

-- Check Bumiputera vendors
SELECT COUNT(*) FROM omsb_procurement_vendors WHERE is_bumi = 1;

-- Check GST-registered vendors
SELECT COUNT(*) FROM omsb_procurement_vendors WHERE is_gst = 1;

-- Check specialized vendors
SELECT COUNT(*) FROM omsb_procurement_vendors WHERE is_specialized = 1;

-- Check vendors with credit limits
SELECT COUNT(*) FROM omsb_procurement_vendors WHERE credit_limit IS NOT NULL;

-- Sample vendor details
SELECT code, name, type, is_bumi, sap_code, status, credit_limit 
FROM omsb_procurement_vendors 
LIMIT 10;
```

---

## Breaking Changes

⚠️ **Status Field Type Change:**
- **Old:** `ENUM('active', 'inactive', 'blacklisted')`
- **New:** `VARCHAR` (supports legacy values like 'Active', 'Inactive', 'Blacklisted')

**Impact:** Code using exact case-sensitive status comparisons may need updates.

**Mitigation:** Use model methods `isActive()` and `isBlacklisted()` which are case-insensitive.

---

## Backward Compatibility

✅ **Fully Compatible With:**
- Existing vendor relationships (purchase_orders, vendor_quotations, purchaseable_items)
- Existing scopes (scopeActive, scopeByStatus)
- Existing validation rules
- Existing fillable fields
- address_id foreign key relationship

✅ **No Changes Required For:**
- Controllers using vendor relationships
- Views displaying vendor data
- Reports querying vendor information
- Forms submitting vendor data (new fields optional)

---

## CSV Import Statistics (Expected)

```
Total rows processed: ~3,200
Vendors imported/updated: ~2,850
Vendors skipped (deleted): ~350
```

**CSV Location:** `/plugins/omsb/procurement/updates/raw_data/tsi_procurement_vendors_202510281231-procurement_vendors.csv`

---

## Next Steps After Migration

1. ✅ **Verify Schema** - Check all columns exist
2. ✅ **Run Import** - Execute seeder
3. ✅ **Validate Data** - Run validation queries
4. ⏳ **Test Backend** - Test vendor CRUD in admin panel
5. ⏳ **Update Forms** - Add new fields to columns.yaml and fields.yaml (optional)
6. ⏳ **Test Relationships** - Verify PO creation with vendors
7. ⏳ **Address Normalization** - Create Address records from embedded fields
8. ⏳ **Reference Tables** - Link country_id and state_id to reference tables

---

## Support & Troubleshooting

Refer to detailed troubleshooting guide in:
`/docs/migrations/vendor-schema-migration-guide.md`

Common issues:
- CSV file not found → Check file path and permissions
- Duplicate codes → Check CSV for duplicates
- Date parsing errors → Verify date format in CSV
- Boolean conversion issues → Check model casts

---

## Rollback

If needed, restore from backup:
```bash
mysql -u root -p railwayfour < backup_vendors_YYYYMMDD.sql
```

Or restore old migration/model from git:
```bash
git checkout HEAD~1 plugins/omsb/procurement/updates/create_vendors_table.php
git checkout HEAD~1 plugins/omsb/procurement/models/Vendor.php
php artisan plugin:refresh Omsb.Procurement
```
