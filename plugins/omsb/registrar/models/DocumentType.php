<?php namespace Omsb\Registrar\Models;

use Model;
use Backend\Facades\BackendAuth;

/**
 * DocumentType Model
 * 
 * Defines document numbering configuration for different document types.
 * Each document type can have its own numbering pattern, reset cycles,
 * modifiers, and business rules.
 */
class DocumentType extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_registrar_document_types';

    /**
     * @var array dates attributes that should be mutated to dates
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'numbering_pattern',
        'prefix_template',
        'suffix_template',
        'reset_cycle',
        'starting_number',
        'current_number',
        'number_length',
        'increment_by',
        'supports_modifiers',
        'modifier_separator',
        'modifier_options',
        'requires_site_code',
        'requires_year',
        'requires_month',
        'is_active',
        'protect_after_status',
        'void_only_statuses',
        'created_by',
        'updated_by'
    ];

    /**
     * @var array jsonable attributes
     */
    protected $jsonable = [
        'modifier_options',
        'void_only_statuses'
    ];

    /**
     * @var array casts
     */
    protected $casts = [
        'supports_modifiers' => 'boolean',
        'requires_site_code' => 'boolean',
        'requires_year' => 'boolean',
        'requires_month' => 'boolean',
        'is_active' => 'boolean',
        'starting_number' => 'integer',
        'current_number' => 'integer',
        'number_length' => 'integer',
        'increment_by' => 'integer'
    ];

    /**
     * @var array validation rules
     */
    public $rules = [
        'code' => 'required|unique:omsb_registrar_document_types,code|max:20|alpha_dash',
        'name' => 'required|max:100',
        'numbering_pattern' => 'required|max:255',
        'reset_cycle' => 'required|in:never,yearly,monthly',
        'starting_number' => 'required|integer|min:1',
        'number_length' => 'required|integer|min:1|max:10',
        'increment_by' => 'required|integer|min:1',
        'protect_after_status' => 'nullable|max:50',
        'modifier_separator' => 'nullable|max:5'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'code.unique' => 'Document type code must be unique',
        'code.alpha_dash' => 'Document type code can only contain letters, numbers, dashes and underscores'
    ];

    /**
     * @var array relations
     */
    public $hasMany = [
        'registries' => [
            \Omsb\Registrar\Models\DocumentRegistry::class,
            'key' => 'document_type_code',
            'otherKey' => 'code'
        ]
    ];

    public $belongsTo = [
        'creator' => [\Backend\Models\User::class, 'key' => 'created_by'],
        'updater' => [\Backend\Models\User::class, 'key' => 'updated_by']
    ];

    /**
     * Boot the model
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $user = BackendAuth::getUser();
            if ($user) {
                $model->created_by = $user->id;
                $model->updated_by = $user->id;
            }
        });

        static::updating(function ($model) {
            $user = BackendAuth::getUser();
            if ($user) {
                $model->updated_by = $user->id;
            }
        });
    }

    /**
     * Scope for active document types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get next document number for this type
     */
    public function getNextNumber($siteCode = null, $modifiers = [])
    {
        $service = new \Omsb\Registrar\Services\DocumentNumberingService();
        return $service->generateDocumentNumber($this->code, $siteCode, $modifiers);
    }

    /**
     * Check if document supports modifiers
     */
    public function hasModifierSupport()
    {
        return $this->supports_modifiers && !empty($this->modifier_options);
    }

    /**
     * Get available modifier options
     */
    public function getAvailableModifiers()
    {
        if (!$this->hasModifierSupport()) {
            return [];
        }

        return $this->modifier_options ?: [];
    }

    /**
     * Check if status allows editing
     */
    public function allowsEditingAtStatus($status)
    {
        if (!$this->protect_after_status) {
            return true;
        }

        // If current status is the protected status or beyond, no editing
        $protectedStatuses = $this->void_only_statuses ?: [];
        
        return !in_array($status, $protectedStatuses) && 
               $status !== $this->protect_after_status;
    }

    /**
     * Get formatted numbering pattern example
     */
    public function getPatternExample($siteCode = 'HQ')
    {
        $pattern = $this->numbering_pattern;
        $year = date('Y');
        $month = date('m');
        $number = str_pad($this->starting_number, $this->number_length, '0', STR_PAD_LEFT);

        // Replace pattern tokens
        $pattern = str_replace('{SITE}', $siteCode, $pattern);
        $pattern = str_replace('{YYYY}', $year, $pattern);
        $pattern = str_replace('{MM}', $month, $pattern);
        $pattern = str_replace('{######}', $number, $pattern);

        // Add modifier example if supported
        if ($this->hasModifierSupport() && !empty($this->modifier_options)) {
            $firstModifier = array_keys($this->modifier_options)[0];
            $pattern .= $this->modifier_separator . '(' . $firstModifier . ')';
        }

        return $pattern;
    }

    /**
     * Reset numbering cycle
     */
    public function resetNumbering($newStartNumber = null)
    {
        $this->current_number = $newStartNumber ?: $this->starting_number;
        $this->save();

        // Log the reset action
        \Omsb\Registrar\Models\DocumentAuditTrail::create([
            'document_type_code' => $this->code,
            'action' => 'numbering_reset',
            'old_values' => null,
            'new_values' => ['reset_to' => $this->current_number],
            'reason' => 'Numbering cycle reset',
            'performed_by' => BackendAuth::getUser()?->id
        ]);
    }

    /**
     * Get statistics for this document type
     */
    public function getStatistics()
    {
        $total = $this->registries()->count();
        $active = $this->registries()->where('status', '!=', 'voided')->count();
        $voided = $this->registries()->where('status', 'voided')->count();
        $thisYear = $this->registries()->whereYear('created_at', date('Y'))->count();

        return [
            'total_documents' => $total,
            'active_documents' => $active,
            'voided_documents' => $voided,
            'this_year_count' => $thisYear,
            'next_number' => $this->current_number + $this->increment_by
        ];
    }
}