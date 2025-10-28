<?php namespace Omsb\Procurement\Models;

use Model;

/**
 * VendorShareholder Model
 * 
 * Represents shareholders/directors of vendor companies
 *
 * @property int $id
 * @property int $vendor_id
 * @property string $name Shareholder name
 * @property string|null $ic_no IC/Passport number
 * @property string|null $designation Position/role in company
 * @property string|null $category Shareholder category
 * @property string|null $share Share percentage or amount
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class VendorShareholder extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_procurement_vendor_shareholders';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'vendor_id',
        'name',
        'ic_no',
        'designation',
        'category',
        'share'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'ic_no',
        'designation',
        'category',
        'share'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'vendor_id' => 'required|integer|exists:omsb_procurement_vendors,id',
        'name' => 'required|max:255',
        'ic_no' => 'nullable|max:255',
        'designation' => 'nullable|max:255',
        'category' => 'nullable|max:255',
        'share' => 'nullable|max:255'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'vendor_id.required' => 'Vendor is required',
        'vendor_id.exists' => 'Selected vendor does not exist',
        'name.required' => 'Shareholder name is required'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'deleted_at'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'vendor' => [
            Vendor::class,
            'key' => 'vendor_id'
        ]
    ];

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        $display = $this->name;
        
        if ($this->designation) {
            $display .= ' (' . $this->designation . ')';
        }
        
        if ($this->share) {
            $display .= ' - ' . $this->share . '%';
        }
        
        return $display;
    }

    /**
     * Get formatted share value
     */
    public function getFormattedShareAttribute(): string
    {
        if (!$this->share) {
            return 'N/A';
        }
        
        return $this->share . '%';
    }

    /**
     * Scope: Filter by vendor
     */
    public function scopeByVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if shareholder is a company (not individual)
     */
    public function isCompany(): bool
    {
        // If no IC number or IC looks like registration number, likely a company
        return empty($this->ic_no) || 
               !preg_match('/^\d{6}-\d{2}-\d{4}$/', $this->ic_no);
    }
}
