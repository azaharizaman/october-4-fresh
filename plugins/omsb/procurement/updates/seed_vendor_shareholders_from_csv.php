<?php namespace Omsb\Procurement\Updates;

use Omsb\Procurement\Models\Vendor;
use Omsb\Procurement\Models\VendorShareholder;
use October\Rain\Database\Updates\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SeedVendorShareholdersFromCsv Seeder
 * 
 * Seeds vendor shareholder data from CSV dump file
 * Only imports records where deleted_at is NULL
 * Maps old vendor IDs to new vendor records using vendor code
 */
class SeedVendorShareholdersFromCsv extends Seeder
{
    /**
     * @var array Mapping of old vendor ID to vendor code
     */
    protected $vendorIdMapping = [];

    /**
     * Run the seeder
     */
    public function run()
    {
        $vendorsCsvFile = __DIR__ . '/raw_data/tsi_procurement_vendors_202510281231-procurement_vendors.csv';
        $shareholdersCsvFile = __DIR__ . '/raw_data/procurement_vendors_shareholders-202510281256.csv';
        
        if (!file_exists($vendorsCsvFile)) {
            $this->command->error("Vendors CSV file not found: {$vendorsCsvFile}");
            return;
        }

        if (!file_exists($shareholdersCsvFile)) {
            $this->command->error("Shareholders CSV file not found: {$shareholdersCsvFile}");
            return;
        }

        // Step 1: Build vendor ID mapping from vendors CSV
        $this->command->info("Building vendor ID mapping from vendors CSV...");
        $this->buildVendorIdMapping($vendorsCsvFile);
        $this->command->info("Vendor ID mapping built: " . count($this->vendorIdMapping) . " mappings");

        // Step 2: Import shareholders
        $this->command->info("Reading shareholders CSV file: {$shareholdersCsvFile}");
        
        $handle = fopen($shareholdersCsvFile, 'r');
        
        if ($handle === false) {
            $this->command->error("Failed to open shareholders CSV file");
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
        $skippedDeletedCount = 0;
        $skippedMissingVendorCount = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowCount++;
                
                // Create associative array from headers and row data
                $data = array_combine($headers, $row);
                
                // Skip if deleted_at is not null (record is soft-deleted)
                if (!empty($data['deleted_at']) && $data['deleted_at'] !== 'NULL') {
                    $skippedDeletedCount++;
                    continue;
                }

                // Get old vendor ID
                $oldVendorId = $this->cleanValue($data['vendor_id']);
                
                if (empty($oldVendorId)) {
                    $this->command->warn("Row {$rowCount}: No vendor_id found, skipping");
                    $skippedMissingVendorCount++;
                    continue;
                }

                // Map old vendor ID to vendor code, then find vendor
                if (!isset($this->vendorIdMapping[$oldVendorId])) {
                    $this->command->warn("Row {$rowCount}: Vendor ID {$oldVendorId} not found in mapping, skipping");
                    $skippedMissingVendorCount++;
                    continue;
                }

                $vendorCode = $this->vendorIdMapping[$oldVendorId];
                $vendor = Vendor::where('code', $vendorCode)->first();

                if (!$vendor) {
                    $this->command->warn("Row {$rowCount}: Vendor with code {$vendorCode} not found in database, skipping");
                    $skippedMissingVendorCount++;
                    continue;
                }

                // Parse and insert shareholder data
                $shareholderData = $this->parseShareholderData($data, $vendor->id);
                
                // Check if shareholder already exists (by vendor_id, name, and ic_no)
                $existingShareholder = VendorShareholder::withTrashed()
                    ->where('vendor_id', $shareholderData['vendor_id'])
                    ->where('name', $shareholderData['name'])
                    ->where('ic_no', $shareholderData['ic_no'])
                    ->first();
                
                if ($existingShareholder) {
                    // Update existing shareholder
                    $existingShareholder->update($shareholderData);
                    $this->command->info("Updated shareholder: {$shareholderData['name']} for vendor {$vendorCode}");
                } else {
                    // Create new shareholder
                    VendorShareholder::create($shareholderData);
                    $this->command->info("Imported shareholder: {$shareholderData['name']} for vendor {$vendorCode}");
                }
                
                $importedCount++;
            }

            DB::commit();
            
            fclose($handle);
            
            $this->command->info("===========================================");
            $this->command->info("Shareholder import completed successfully!");
            $this->command->info("Total rows processed: {$rowCount}");
            $this->command->info("Shareholders imported/updated: {$importedCount}");
            $this->command->info("Shareholders skipped (deleted): {$skippedDeletedCount}");
            $this->command->info("Shareholders skipped (vendor not found): {$skippedMissingVendorCount}");
            $this->command->info("===========================================");
            
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            
            $this->command->error("Error importing shareholders: " . $e->getMessage());
            $this->command->error("Stack trace: " . $e->getTraceAsString());
            
            throw $e;
        }
    }

    /**
     * Build mapping of old vendor ID to vendor code from vendors CSV
     * 
     * @param string $csvFile Path to vendors CSV file
     */
    protected function buildVendorIdMapping(string $csvFile): void
    {
        $handle = fopen($csvFile, 'r');
        
        if ($handle === false) {
            throw new \Exception("Failed to open vendors CSV file for mapping");
        }

        // Read header row
        $headers = fgetcsv($handle);
        $headers = array_map(function($header) {
            return trim($header, '"');
        }, $headers);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            
            $oldId = $this->cleanValue($data['id']);
            $code = $this->cleanValue($data['code']);
            
            if ($oldId && $code) {
                $this->vendorIdMapping[$oldId] = $code;
            }
        }

        fclose($handle);
    }

    /**
     * Parse shareholder data from CSV row
     * 
     * @param array $data CSV row data
     * @param int $vendorId New vendor ID from database
     * @return array Parsed shareholder data
     */
    protected function parseShareholderData(array $data, int $vendorId): array
    {
        return [
            'vendor_id' => $vendorId,
            'name' => $this->cleanValue($data['name']),
            'ic_no' => $this->cleanValue($data['ic_no']),
            'designation' => $this->cleanValue($data['designation']),
            'category' => $this->cleanValue($data['category']),
            'share' => $this->cleanValue($data['share']),
            
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
