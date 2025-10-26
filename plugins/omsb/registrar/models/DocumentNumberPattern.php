<?php namespace Omsb\Registrar\Models;

use Model;
use Carbon\Carbon;

/**
 * DocumentNumberPattern Model
 * 
 * Defines numbering patterns per document type
 * Default pattern: {SITE}-{DOCTYPE}-{YYYY}-{#####}
 *
 * @property int $id
 * @property string $pattern Pattern template
 * @property string|null $prefix Fixed prefix
 * @property string|null $suffix Fixed suffix
 * @property string $reset_interval Reset interval (never, yearly, monthly)
 * @property int $next_number Next number to issue
 * @property int $number_length Padding length for running number
 * @property int|null $current_year Current year (for yearly reset)
 * @property int|null $current_month Current month (for monthly reset)
 * @property bool $is_active Active status
 * @property int $document_type_id Parent document type
 * @property int|null $site_id Site-specific pattern
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class DocumentNumberPattern extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_registrar_document_number_patterns';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'pattern',
        'prefix',
        'suffix',
        'reset_interval',
        'next_number',
        'number_length',
        'current_year',
        'current_month',
        'is_active',
        'document_type_id',
        'site_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'prefix',
        'suffix',
        'current_year',
        'current_month',
        'site_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'pattern' => 'required|max:255',
        'reset_interval' => 'required|in:never,yearly,monthly',
        'next_number' => 'required|integer|min:1',
        'number_length' => 'required|integer|min:1|max:10',
        'is_active' => 'boolean',
        'document_type_id' => 'required|integer|exists:omsb_registrar_document_types,id',
        'site_id' => 'nullable|integer|exists:omsb_organization_sites,id'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'is_active' => 'boolean',
        'next_number' => 'integer',
        'number_length' => 'integer',
        'current_year' => 'integer',
        'current_month' => 'integer'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'document_type' => [
            DocumentType::class
        ],
        'site' => [
            'Omsb\Organization\Models\Site'
        ]
    ];

    public $hasMany = [
        'issued_numbers' => [
            IssuedDocumentNumber::class,
            'key' => 'document_number_pattern_id'
        ]
    ];

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->document_type->code;
        
        if ($this->site) {
            $name .= ' (' . $this->site->code . ')';
        }
        
        return $name . ' - ' . $this->pattern;
    }

    /**
     * Generate next document number
     */
    public function generateNumber(array $variables = []): string
    {
        // Check if reset is needed
        $this->checkAndResetIfNeeded();
        
        // Get the next number
        $number = $this->next_number;
        
        // Prepare replacements
        $replacements = array_merge([
            '{YYYY}' => Carbon::now()->format('Y'),
            '{YY}' => Carbon::now()->format('y'),
            '{MM}' => Carbon::now()->format('m'),
            '{DD}' => Carbon::now()->format('d'),
            '{DOCTYPE}' => $this->document_type->code,
            '{SITE}' => $this->site ? $this->site->code : '',
            '{#####}' => str_pad($number, $this->number_length, '0', STR_PAD_LEFT)
        ], $variables);
        
        // Replace placeholders in pattern
        $documentNumber = $this->pattern;
        
        foreach ($replacements as $placeholder => $value) {
            $documentNumber = str_replace($placeholder, $value, $documentNumber);
        }
        
        // Add prefix and suffix if set
        if ($this->prefix) {
            $documentNumber = $this->prefix . $documentNumber;
        }
        
        if ($this->suffix) {
            $documentNumber = $documentNumber . $this->suffix;
        }
        
        // Increment next_number
        $this->next_number = $number + 1;
        $this->save();
        
        return $documentNumber;
    }

    /**
     * Check if reset is needed based on interval
     */
    protected function checkAndResetIfNeeded(): void
    {
        $now = Carbon::now();
        
        if ($this->reset_interval === 'yearly') {
            $currentYear = $now->year;
            
            if ($this->current_year !== $currentYear) {
                $this->next_number = 1;
                $this->current_year = $currentYear;
                $this->save();
            }
        }
        elseif ($this->reset_interval === 'monthly') {
            $currentYear = $now->year;
            $currentMonth = $now->month;
            
            if ($this->current_year !== $currentYear || $this->current_month !== $currentMonth) {
                $this->next_number = 1;
                $this->current_year = $currentYear;
                $this->current_month = $currentMonth;
                $this->save();
            }
        }
    }

    /**
     * Scope: Active patterns only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by document type
     */
    public function scopeForDocumentType($query, int $documentTypeId)
    {
        return $query->where('document_type_id', $documentTypeId);
    }

    /**
     * Scope: Filter by site
     */
    public function scopeForSite($query, int $siteId = null)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Get document type options for dropdown
     */
    public function getDocumentTypeIdOptions(): array
    {
        return DocumentType::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get site options for dropdown
     */
    public function getSiteIdOptions(): array
    {
        return \Omsb\Organization\Models\Site::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
