<?php namespace Omsb\Registrar\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * IssuedDocumentNumber Model
 * 
 * Tracks all issued document numbers to avoid duplication
 *
 * @property int $id
 * @property string $document_number Issued document number
 * @property \Carbon\Carbon $issued_date Issue date
 * @property string $documentable_type Polymorphic document type
 * @property int $documentable_id Polymorphic document ID
 * @property string $status Status (active, cancelled, voided)
 * @property int $document_type_id Parent document type
 * @property int $document_number_pattern_id Pattern used
 * @property int|null $issued_by User who issued
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class IssuedDocumentNumber extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_registrar_issued_document_numbers';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'document_number',
        'issued_date',
        'documentable_type',
        'documentable_id',
        'status',
        'document_type_id',
        'document_number_pattern_id',
        'issued_by'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'issued_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'document_number' => 'required|unique:omsb_registrar_issued_document_numbers,document_number',
        'issued_date' => 'required|date',
        'documentable_type' => 'required|max:255',
        'documentable_id' => 'required|integer',
        'status' => 'required|in:active,cancelled,voided',
        'document_type_id' => 'required|integer|exists:omsb_registrar_document_types,id',
        'document_number_pattern_id' => 'required|integer|exists:omsb_registrar_document_number_patterns,id',
        'issued_by' => 'nullable|integer|exists:backend_users,id'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'issued_date',
        'deleted_at'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'document_type' => [
            DocumentType::class
        ],
        'document_number_pattern' => [
            DocumentNumberPattern::class
        ],
        'issuer' => [
            \Backend\Models\User::class,
            'key' => 'issued_by'
        ]
    ];

    public $morphTo = [
        'documentable' => []
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-set issued_by and issued_date
        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->issued_by = BackendAuth::getUser()->id;
            }
            
            if (!$model->issued_date) {
                $model->issued_date = Carbon::now();
            }
        });
    }

    /**
     * Issue a new document number
     */
    public static function issueNumber(
        DocumentNumberPattern $pattern,
        string $documentableType,
        int $documentableId,
        array $variables = []
    ): self {
        $documentNumber = $pattern->generateNumber($variables);
        
        return self::create([
            'document_number' => $documentNumber,
            'issued_date' => Carbon::now(),
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'status' => 'active',
            'document_type_id' => $pattern->document_type_id,
            'document_number_pattern_id' => $pattern->id
        ]);
    }

    /**
     * Cancel this document number
     */
    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }

    /**
     * Void this document number
     */
    public function void(): void
    {
        $this->status = 'voided';
        $this->save();
    }

    /**
     * Check if number is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope: Active numbers only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter by document type
     */
    public function scopeForDocumentType($query, int $documentTypeId)
    {
        return $query->where('document_type_id', $documentTypeId);
    }

    /**
     * Scope: Filter by document
     */
    public function scopeForDocument($query, string $type, int $id)
    {
        return $query->where('documentable_type', $type)
                     ->where('documentable_id', $id);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Recent first
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('issued_date', 'desc');
    }

    /**
     * Get issued number for a document
     */
    public static function getForDocument(string $type, int $id): ?self
    {
        return self::forDocument($type, $id)->first();
    }

    /**
     * Check if document number exists
     */
    public static function numberExists(string $documentNumber): bool
    {
        return self::where('document_number', $documentNumber)->exists();
    }
}
