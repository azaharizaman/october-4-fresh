# Vendor Shareholders Implementation - Summary

## Overview

Added comprehensive support for tracking vendor shareholders/directors with data import from legacy TSI system.

---

## Files Created

### 1. Migration File
**Path:** `/plugins/omsb/procurement/updates/create_vendor_shareholders_table.php`

**Table:** `omsb_procurement_vendor_shareholders`

**Columns:**
- `id` - Primary key
- `vendor_id` - Foreign key to vendors (CASCADE on delete)
- `name` - Shareholder/director name (required)
- `ic_no` - IC/Passport number (nullable)
- `designation` - Position/role (nullable)
- `category` - Category classification (nullable)
- `share` - Share percentage or amount (nullable)
- `created_at`, `updated_at` - Timestamps
- `deleted_at` - Soft delete timestamp

**Indexes:**
- `idx_shareholders_vendor_id` - Fast vendor lookup
- `idx_shareholders_name` - Name search
- `idx_shareholders_ic_no` - IC number search
- `idx_shareholders_deleted_at` - Soft delete queries

**Foreign Key:**
- `fk_shareholders_vendor` - References `omsb_procurement_vendors(id)` ON DELETE CASCADE

### 2. Model File
**Path:** `/plugins/omsb/procurement/models/VendorShareholder.php`

**Features:**
- Validation rules for all fields
- Soft delete support
- BelongsTo relationship to Vendor
- Custom accessor: `getDisplayNameAttribute()` - Formats name with designation and share
- Custom accessor: `getFormattedShareAttribute()` - Returns share with % symbol
- Scope: `scopeByVendor($vendorId)` - Filter by vendor
- Scope: `scopeByCategory($category)` - Filter by category
- Method: `isCompany()` - Detects if shareholder is a company vs individual

### 3. Seeder File
**Path:** `/plugins/omsb/procurement/updates/seed_vendor_shareholders_from_csv.php`

**Purpose:** Import shareholder data from legacy CSV with vendor ID mapping

**Features:**
- Reads vendor CSV to build old ID → code mapping
- Maps legacy vendor IDs to new vendor records
- Skips soft-deleted records (deleted_at IS NOT NULL)
- Upserts shareholders (updates existing, creates new)
- Preserves original timestamps from legacy system
- Transaction-based for data integrity
- Comprehensive error handling and reporting

**Statistics Reported:**
- Total rows processed
- Shareholders imported/updated
- Shareholders skipped (deleted)
- Shareholders skipped (vendor not found)

### 4. Controller File
**Path:** `/plugins/omsb/procurement/controllers/Vendors.php`

**Implements:**
- `FormController` - CRUD operations
- `ListController` - List view
- `RelationController` - Manage related records

**Permissions:** `omsb.procurement.vendors`

### 5. Relation Configuration
**Path:** `/plugins/omsb/procurement/controllers/vendors/config_relation.yaml`

**Relations Configured:**
- `shareholders` - Manage shareholders/directors (create, edit, delete)
- `purchase_orders` - View/manage vendor POs
- `vendor_quotations` - View/manage vendor quotations

### 6. UI Configuration Files

**Columns:** `/plugins/omsb/procurement/models/vendorshareholder/columns.yaml`
- Name, IC/Passport No, Position/Role, Category, Share (%), Created

**Fields:** `/plugins/omsb/procurement/models/vendorshareholder/fields.yaml`
- Name (required), IC/Passport Number, Position/Role, Category, Share Percentage
- System Info tab with created_at and updated_at

---

## Model Relationship Updates

### Vendor Model
**Path:** `/plugins/omsb/procurement/models/Vendor.php`

**Added hasMany Relationship:**
```php
'shareholders' => [
    VendorShareholder::class,
    'key' => 'vendor_id'
]
```

**Usage Examples:**
```php
// Get all shareholders for a vendor
$shareholders = $vendor->shareholders;

// Get active shareholders only
$activeShareholders = $vendor->shareholders()->whereNull('deleted_at')->get();

// Count shareholders
$shareholderCount = $vendor->shareholders()->count();

// Check if vendor has shareholders
if ($vendor->shareholders()->exists()) {
    // Has shareholders
}
```

---

## Migration Order

Migrations run alphabetically, ensuring correct order:

1. **v1.0.2** - `create_vendors_table.php` (vendors created first)
2. **v1.0.3** - `create_vendor_shareholders_table.php` (shareholders after vendors)
3. **v1.0.4** - `seed_vendors_from_csv.php` (seed vendors first)
4. **v1.0.5** - `seed_vendor_shareholders_from_csv.php` (seed shareholders after vendors)

---

## CSV Data Mapping

### Source Files

1. **Vendors CSV:** `raw_data/tsi_procurement_vendors_202510281231-procurement_vendors.csv`
   - Contains old `id` and new `code` columns
   - Used to build ID mapping

2. **Shareholders CSV:** `raw_data/procurement_vendors_shareholders-202510281256.csv`
   - Contains `vendor_id` (old vendor ID from legacy system)
   - ~918 total records (including deleted)

### Mapping Process

```
Legacy vendor_id → Vendor code → New vendor record
     1385        →   VS900001  →   Vendor::where('code', 'VS900001')->first()
```

**Example:**
```
Shareholder CSV: vendor_id = 1385
1. Find in mapping: 1385 → VS900001
2. Find vendor: Vendor::where('code', 'VS900001')->first()
3. Create shareholder with vendor->id
```

---

## Running the Migration

### Step-by-Step Process

```bash
# 1. Refresh plugin (runs migrations in order)
php artisan plugin:refresh Omsb.Procurement

# Output:
# - Creates omsb_procurement_vendors table
# - Creates omsb_procurement_vendor_shareholders table
# - Seeds vendors from CSV (~2,850 records)
# - Seeds shareholders from CSV (~700-800 active records)

# 2. Verify shareholder import
mysql -u root -p railwayfour -e "
SELECT 
    v.code,
    v.name as vendor_name,
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

### Expected Results

```
Total shareholders imported: ~700-800 active records
Skipped (deleted): ~100-200 soft-deleted records
Skipped (vendor not found): Depends on vendor import success
```

---

## Validation Queries

### Check Shareholder Distribution

```sql
-- Vendors with most shareholders
SELECT 
    v.code,
    v.name,
    COUNT(s.id) as shareholder_count
FROM omsb_procurement_vendors v
INNER JOIN omsb_procurement_vendor_shareholders s ON v.id = s.vendor_id
WHERE s.deleted_at IS NULL
GROUP BY v.id
ORDER BY shareholder_count DESC
LIMIT 20;

-- Shareholders by designation
SELECT 
    designation,
    COUNT(*) as count
FROM omsb_procurement_vendor_shareholders
WHERE deleted_at IS NULL AND designation IS NOT NULL
GROUP BY designation
ORDER BY count DESC;

-- Shareholders by category
SELECT 
    category,
    COUNT(*) as count
FROM omsb_procurement_vendor_shareholders
WHERE deleted_at IS NULL
GROUP BY category
ORDER BY count DESC;

-- Vendors without shareholders
SELECT 
    code,
    name,
    type
FROM omsb_procurement_vendors v
WHERE NOT EXISTS (
    SELECT 1 
    FROM omsb_procurement_vendor_shareholders s 
    WHERE s.vendor_id = v.id AND s.deleted_at IS NULL
)
ORDER BY name
LIMIT 20;
```

---

## Backend UI Usage

### Accessing Shareholders

1. Navigate to **Procurement → Vendors**
2. Click on a vendor to edit
3. Click the **Shareholders/Directors** tab
4. Use toolbar buttons to:
   - **Create** - Add new shareholder
   - **Delete** - Remove shareholder (soft delete)

### Shareholder Form Fields

**Required:**
- **Name** - Full name of shareholder/director

**Optional:**
- **IC/Passport Number** - Identification number
- **Position/Role** - e.g., Director, CEO, Managing Director, Partner
- **Category** - Classification (0/1 in legacy data)
- **Share Percentage** - Ownership percentage (e.g., 50, 30%, etc.)

### Display Format

**List View:**
```
Name                    | IC/Passport No      | Position/Role       | Share (%)
-----------------------|---------------------|---------------------|----------
SUZAN ANAK MINCHONG    | 830206-13-5676     | DIRECTOR            | 70
MOHAMAD IBRAHIM B.     | 810721-13-5787     | DIRECTOR            | 30
```

**Display Name Accessor:**
```
"SUZAN ANAK MINCHONG (DIRECTOR) - 70%"
```

---

## Model Usage Examples

### In Controllers

```php
use Omsb\Procurement\Models\Vendor;
use Omsb\Procurement\Models\VendorShareholder;

// Get vendor with shareholders
$vendor = Vendor::with('shareholders')->find(1);

// Display shareholders
foreach ($vendor->shareholders as $shareholder) {
    echo $shareholder->display_name . "\n";
}

// Add new shareholder
$shareholder = VendorShareholder::create([
    'vendor_id' => $vendor->id,
    'name' => 'John Doe',
    'ic_no' => '800101-01-1234',
    'designation' => 'DIRECTOR',
    'share' => '25'
]);

// Find shareholders by IC
$shareholder = VendorShareholder::where('ic_no', '830206-13-5676')->first();

// Get all directors
$directors = VendorShareholder::where('designation', 'DIRECTOR')->get();

// Get shareholders with more than 50% share
$majorShareholders = VendorShareholder::where('share', '>', '50')->get();

// Check if shareholder is a company
if ($shareholder->isCompany()) {
    // Handle corporate shareholder
}
```

### In Views (Twig)

```twig
{# Display vendor shareholders #}
{% if record.shareholders.count() %}
    <h3>Shareholders/Directors</h3>
    <ul>
        {% for shareholder in record.shareholders %}
            <li>
                {{ shareholder.display_name }}
                {% if shareholder.ic_no %}
                    <br><small>IC: {{ shareholder.ic_no }}</small>
                {% endif %}
            </li>
        {% endfor %}
    </ul>
{% else %}
    <p>No shareholders recorded</p>
{% endif %}
```

---

## Data Quality Notes

### IC Number Formats

**Individual IC (Malaysian):**
```
Format: YYMMDD-SS-####
Example: 830206-13-5676
Pattern: 6 digits - 2 digits - 4 digits
```

**Passport/Foreign:**
```
Examples: G24109480, C3840512
Variable format
```

**Company Registration:**
```
Examples: 277436-D, blank
```

### Share Values

**Formats Found:**
- Percentage: `70`, `30`, `50`
- Decimal: `7.5`, `15.5`
- Empty/NULL: Some shareholders have no share value

### Designation Values

Common designations in data:
- DIRECTOR
- MANAGING DIRECTOR
- CEO
- MANAGER
- ASSISTANT MANAGER
- PARTNER
- OWNER
- PROJECT MANAGER

### Category Values

Legacy category field:
- `0` - Possibly non-Malaysian or corporate
- `1` - Possibly Malaysian or individual
- Empty - Unknown

---

## Troubleshooting

### Issue: Shareholder Import Fails with "Vendor Not Found"

**Cause:** Legacy vendor ID not mapped or vendor not imported

**Solution:**
1. Check vendor import completed successfully
2. Verify vendor exists: `SELECT * FROM omsb_procurement_vendors WHERE code = 'VRC-00001';`
3. Check mapping built correctly in seeder
4. Re-run vendor import if needed

### Issue: Duplicate Shareholders

**Cause:** Multiple shareholders with same name and IC

**Solution:**
Seeder uses upsert logic based on:
- `vendor_id`
- `name`
- `ic_no`

If duplicates exist, update seeder uniqueness check or manually review data.

### Issue: Share Percentages Don't Add to 100%

**Cause:** Data quality issue in legacy system or incomplete records

**Solution:**
This is informational only. Add validation in frontend if needed:

```php
// In Vendor model
public function getTotalShareAttribute(): float
{
    return $this->shareholders()->sum('share');
}

// Validation
if ($vendor->total_share != 100) {
    // Warn or flag for review
}
```

---

## Future Enhancements

1. **Share Validation:** Add validation to ensure shares sum to 100%
2. **IC Validation:** Validate Malaysian IC format
3. **Historical Tracking:** Track shareholder changes over time
4. **Ownership Reports:** Generate ownership structure reports
5. **Document Upload:** Attach shareholder documents (e.g., shareholding certificates)
6. **Relationship Tracking:** Link related shareholders across multiple vendors

---

## Summary

✅ **Migration Created:** `create_vendor_shareholders_table.php`  
✅ **Model Created:** `VendorShareholder.php`  
✅ **Seeder Created:** `seed_vendor_shareholders_from_csv.php`  
✅ **Controller Created:** `Vendors.php`  
✅ **Relation Config Created:** `config_relation.yaml`  
✅ **UI Config Created:** columns.yaml, fields.yaml  
✅ **Vendor Model Updated:** Added shareholders relationship  
✅ **Version File Updated:** Added migration and seeder entries  

**Migration Order:** ✅ Correct (vendors → shareholders)  
**Seeder Order:** ✅ Correct (vendors → shareholders)  
**Data Mapping:** ✅ Old vendor IDs mapped to new codes  
**Soft Deletes:** ✅ Skipped in import  
**Syntax Validation:** ✅ All files error-free

**Ready to Run!** 🚀
