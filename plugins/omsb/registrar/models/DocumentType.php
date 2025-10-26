<?php namespace Omsb\Registrar\Models;

use Model;

/**
 * DocumentType Model
 * 
 * Defines document types for numbering
 *
 * @property int $id
 * @property string $code Unique document code (e.g., PR, PO, MRN)
 * @property string $name Document name
 * @property string|null $description Document description
 * @property string $model_class Fully qualified class name
 * @property bool $is_active Active status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
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
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'model_class',
        'is_active'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'description'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_registrar_document_types,code',
        'name' => 'required|max:255',
        'model_class' => 'required|max:255',
        'is_active' => 'boolean'
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
        'is_active' => 'boolean'
    ];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'number_patterns' => [
            DocumentNumberPattern::class,
            'key' => 'document_type_id'
        ],
        'issued_numbers' => [
            IssuedDocumentNumber::class,
            'key' => 'document_type_id'
        ]
    ];

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Scope: Active document types only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get pattern for a specific site
     */
    public function getPatternForSite(int $siteId = null): ?DocumentNumberPattern
    {
        return $this->number_patterns()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get default pattern (no site specific)
     */
    public function getDefaultPattern(): ?DocumentNumberPattern
    {
        return $this->number_patterns()
            ->whereNull('site_id')
            ->where('is_active', true)
            ->first();
    }
}
