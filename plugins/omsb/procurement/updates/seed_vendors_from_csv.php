<?php namespace Omsb\Procurement\Updates;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Omsb\Procurement\Models\Vendor;
use October\Rain\Database\Updates\Seeder;

/**
 * SeedVendorsFromCsv Seeder
 * 
 * Seeds vendor data from CSV dump file (TSI procurement vendors export)
 * Only imports records where deleted_at is NULL
 */
class SeedVendorsFromCsv extends Seeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        $csvFile = __DIR__ . '/raw_data/tsi_procurement_vendors_202510281231-procurement_vendors.csv';
        
        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: {$csvFile}");
            return;
        }

        $this->command->info("Reading CSV file: {$csvFile}");
        
        $handle = fopen($csvFile, 'r');
        
        if ($handle === false) {
            $this->command->error("Failed to open CSV file");
            return;
        }

        // Read header row
        $headers = fgetcsv($handle);
        
        // Remove quotes from headers
        $headers = array_map(function($header) {
            return trim($header, '"');
        }, $headers);

        $rowCount = 0;
        $importedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowCount++;
                
                // Create associative array from headers and row data
                $data = array_combine($headers, $row);
                
                // Skip if deleted_at is not null (record is soft-deleted)
                if (!empty($data['deleted_at']) && $data['deleted_at'] !== 'NULL') {
                    $skippedCount++;
                    continue;
                }

                // Parse and insert vendor data
                $vendorData = $this->parseVendorData($data);
                
                // Check if vendor already exists by code
                $existingVendor = Vendor::withTrashed()->where('code', $vendorData['code'])->first();
                
                if ($existingVendor) {
                    // Update existing vendor
                    $existingVendor->update($vendorData);
                    $this->command->info("Updated vendor: {$vendorData['code']} - {$vendorData['name']}");
                } else {
                    // Create new vendor
                    Vendor::create($vendorData);
                    $this->command->info("Imported vendor: {$vendorData['code']} - {$vendorData['name']}");
                }
                
                $importedCount++;
            }

            DB::commit();
            
            fclose($handle);
            
            $this->command->info("===========================================");
            $this->command->info("Vendor import completed successfully!");
            $this->command->info("Total rows processed: {$rowCount}");
            $this->command->info("Vendors imported/updated: {$importedCount}");
            $this->command->info("Vendors skipped (deleted): {$skippedCount}");
            $this->command->info("===========================================");
            
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            
            $this->command->error("Error importing vendors: " . $e->getMessage());
            $this->command->error("Stack trace: " . $e->getTraceAsString());
            
            throw $e;
        }
    }

    /**
     * Parse vendor data from CSV row
     * 
     * @param array $data CSV row data
     * @return array Parsed vendor data
     */
    protected function parseVendorData(array $data): array
    {
        return [
            'code' => $this->cleanValue($data['code']),
            'name' => $this->cleanValue($data['name']),
            'registration_number' => $this->cleanValue($data['registration_number']),
            'incorporation_date' => $this->parseDate($data['incorporation_date']),
            'sap_code' => $this->cleanValue($data['sap_code']),
            
            // Vendor classification
            'is_bumi' => $this->parseBoolean($data['is_bumi']),
            'type' => $this->cleanValue($data['type']),
            'category' => $this->cleanValue($data['category']),
            'is_specialized' => $this->parseBoolean($data['is_specialized']),
            'is_precision' => $this->parseBoolean($data['is_precision'] ?? '0'),
            'is_approved' => $this->parseBoolean($data['is_approved']),
            
            // Tax/GST information
            'is_gst' => $this->parseBoolean($data['is_gst']),
            'gst_number' => $this->cleanValue($data['gst_number']),
            'gst_type' => $this->cleanValue($data['gst_type']),
            
            // Country/origin information
            'is_foreign' => $this->parseBoolean($data['is_foreign']),
            'country_id' => $this->parseInteger($data['country_id']),
            'origin_country_id' => $this->parseInteger($data['origin_country_id']),
            
            // Contact information
            'contact_person' => $this->cleanValue($data['contact_person']),
            'designation' => $this->cleanValue($data['designation']),
            'contact_email' => $this->cleanValue($data['contact_email'] ?? $data['email']),
            'tel_no' => $this->cleanValue($data['tel_no']),
            'fax_no' => $this->cleanValue($data['fax_no']),
            'hp_no' => $this->cleanValue($data['hp_no']),
            'email' => $this->cleanValue($data['email']),
            
            // Address fields
            'street' => $this->cleanValue($data['street']),
            'city' => $this->cleanValue($data['city']),
            'state_id' => $this->parseInteger($data['state_id']),
            'postcode' => $this->cleanValue($data['postcode']),
            
            // Business information
            'scope_of_work' => $this->cleanValue($data['scope_of_work']),
            'service' => $this->cleanValue($data['service']),
            
            // Credit management
            'credit_limit' => $this->parseDecimal($data['credit_limit']),
            'credit_terms' => $this->cleanValue($data['credit_terms']),
            'credit_updated_at' => $this->parseDateTime($data['credit_updated_at']),
            'credit_review' => $this->cleanValue($data['credit_review']),
            'credit_remarks' => $this->cleanValue($data['credit_remarks']),
            
            // Status and payment
            'status' => $this->cleanValue($data['status']) ?: 'Active',
            'payment_terms' => 'net_30', // Default payment terms
            
            // Company association
            'company_id' => $this->parseInteger($data['company_id']),
            
            // Timestamps (preserve original timestamps)
            'created_at' => $this->parseDateTime($data['created_at']),
            'updated_at' => $this->parseDateTime($data['updated_at']),
        ];
    }

    /**
     * Clean string value
     */
    protected function cleanValue(?string $value): ?string
    {
        if (empty($value) || $value === 'NULL' || $value === '""' || $value === "''") {
            return null;
        }
        
        return trim($value);
    }

    /**
     * Parse boolean value
     */
    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        $value = $this->cleanValue($value);
        
        if ($value === null || $value === '' || $value === '0' || strtolower($value) === 'false') {
            return false;
        }
        
        return true;
    }

    /**
     * Parse integer value
     */
    protected function parseInteger($value): ?int
    {
        $value = $this->cleanValue($value);
        
        if ($value === null || $value === '') {
            return null;
        }
        
        return (int) $value;
    }

    /**
     * Parse decimal value
     */
    protected function parseDecimal($value): ?float
    {
        $value = $this->cleanValue($value);
        
        if ($value === null || $value === '') {
            return null;
        }
        
        return (float) $value;
    }

    /**
     * Parse date value (YYYY-MM-DD format)
     */
    protected function parseDate($value): ?string
    {
        $value = $this->cleanValue($value);
        
        if ($value === null || $value === '') {
            return null;
        }
        
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse datetime value
     */
    protected function parseDateTime($value): ?string
    {
        $value = $this->cleanValue($value);
        
        if ($value === null || $value === '') {
            return null;
        }
        
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
