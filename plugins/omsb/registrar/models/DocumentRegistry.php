<?php namespace Omsb\Registrar\Models;

use Model;
use Backend\Facades\BackendAuth;

/**
 * DocumentRegistry Model
 * 
 * Central registry for all controlled documents across the system.
 * Tracks document numbers, states, ownership, and prevents duplication.
 * 
 * This is the heart of the audit trail system - every controlled document
 * must have an entry here with its complete lifecycle tracked.
 */
class DocumentRegistry extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_registrar_document_registries';

    /**
     * @var array dates attributes that should be mutated to dates
     */
    protected $dates = ['deleted_at', 'locked_at', 'voided_at'];

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'document_number',
        'document_type_code',
        'site_id',
        'site_code',
        'year',
        'month',
        'sequence_number',
        'modifier',
        'full_document_number',
        'documentable_type',
        'documentable_id',
        'status',
        'previous_status',
        'is_locked',
        'locked_at',
        'locked_by',
        'lock_reason',
        'is_voided',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
        'metadata'
    ];

    /**
     * @var array jsonable attributes
     */
    protected $jsonable = ['metadata'];

    /**
     * @var array casts
     */
    protected $casts = [
        'is_locked' => 'boolean',
        'is_voided' => 'boolean',
        'year' => 'integer',
        'month' => 'integer',
        'sequence_number' => 'integer'
    ];

    /**
     * @var array validation rules
     */
    public $rules = [
        'document_number' => 'required|max:100',
        'document_type_code' => 'required|exists:omsb_registrar_document_types,code',
        'full_document_number' => 'required|unique:omsb_registrar_document_registries,full_document_number|max:255',
        'documentable_type' => 'required|max:255',
        'documentable_id' => 'required|integer',
        'status' => 'required|max:50',
        'site_code' => 'nullable|max:10',
        'year' => 'required|integer|min:2020|max:2100',
        'sequence_number' => 'required|integer|min:1'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'full_document_number.unique' => 'This document number has already been issued. Document numbers cannot be reused.',
        'documentable_id.unique' => 'This document is already registered in the system.'
    ];

    /**
     * @var array relations
     */
    public $belongsTo = [
        'documentType' => [
            \Omsb\Registrar\Models\DocumentType::class,
            'key' => 'document_type_code',
            'otherKey' => 'code'
        ],
        'site' => [\Omsb\Organization\Models\Site::class],
        'creator' => [\Backend\Models\User::class, 'key' => 'created_by'],
        'updater' => [\Backend\Models\User::class, 'key' => 'updated_by'],
        'locker' => [\Backend\Models\User::class, 'key' => 'locked_by'],
        'voider' => [\Backend\Models\User::class, 'key' => 'voided_by']
    ];

    public $morphTo = [
        'documentable' => []
    ];

    public $hasMany = [
        'auditTrail' => [
            \Omsb\Registrar\Models\DocumentAuditTrail::class,
            'key' => 'document_registry_id'
        ]
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

            // Set initial metadata
            if (!$model->metadata) {
                $model->metadata = [
                    'created_at_timestamp' => now()->timestamp,
                    'created_ip' => request()->ip(),
                    'created_user_agent' => request()->userAgent()
                ];
            }
        });

        static::updating(function ($model) {
            $user = BackendAuth::getUser();
            if ($user) {
                $model->updated_by = $user->id;
            }

            // Track status changes
            if ($model->isDirty('status')) {
                $model->previous_status = $model->getOriginal('status');
                
                // Auto-create audit trail for status changes
                static::updated(function ($model) {
                    if ($model->wasChanged('status')) {
                        \Omsb\Registrar\Models\DocumentAuditTrail::create([
                            'document_registry_id' => $model->id,
                            'document_type_code' => $model->document_type_code,
                            'action' => 'status_change',
                            'old_values' => ['status' => $model->getOriginal('status')],
                            'new_values' => ['status' => $model->status],
                            'reason' => 'Document status updated',
                            'performed_by' => BackendAuth::getUser()?->id
                        ]);
                    }
                });
            }
        });

        // Prevent deletion of issued documents
        static::deleting(function ($model) {
            if (!$model->is_voided) {
                throw new \ValidationException([
                    'document_number' => 'Cannot delete an issued document. Use void operation instead.'
                ]);
            }
        });
    }

    /**
     * Scope for active (non-voided) documents
     */
    public function scopeActive($query)
    {
        return $query->where('is_voided', false);
    }

    /**
     * Scope for voided documents
     */
    public function scopeVoided($query)
    {
        return $query->where('is_voided', true);
    }

    /**
     * Scope for locked documents
     */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    /**
     * Scope by document type
     */
    public function scopeOfType($query, $documentTypeCode)
    {
        return $query->where('document_type_code', $documentTypeCode);
    }

    /**
     * Scope by year
     */
    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope by site
     */
    public function scopeForSite($query, $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Check if document can be edited
     */
    public function canBeEdited()
    {
        // Cannot edit if voided
        if ($this->is_voided) {
            return false;
        }

        // Cannot edit if locked
        if ($this->is_locked) {
            return false;
        }

        // Check document type rules
        if ($this->documentType && is_object($this->documentType)) {
            return $this->documentType->allowsEditingAtStatus($this->status);
        }

        return true;
    }

    /**
     * Lock the document to prevent further editing
     */
    public function lockDocument($reason = null)
    {
        if ($this->is_locked) {
            throw new \Exception("Document {$this->full_document_number} is already locked");
        }

        $user = BackendAuth::getUser();
        
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $user?->id,
            'lock_reason' => $reason ?: 'Document locked for protection'
        ]);

        // Create audit trail
        \Omsb\Registrar\Models\DocumentAuditTrail::create([
            'document_registry_id' => $this->id,
            'document_type_code' => $this->document_type_code,
            'action' => 'lock',
            'old_values' => ['is_locked' => false],
            'new_values' => ['is_locked' => true, 'lock_reason' => $this->lock_reason],
            'reason' => $reason ?: 'Document locked for protection',
            'performed_by' => $user?->id
        ]);

        return true;
    }

    /**
     * Unlock the document (admin only)
     */
    public function unlockDocument($reason = null)
    {
        if (!$this->is_locked) {
            throw new \Exception("Document {$this->full_document_number} is not locked");
        }

        $user = BackendAuth::getUser();
        
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'lock_reason' => null
        ]);

        // Create audit trail
        \Omsb\Registrar\Models\DocumentAuditTrail::create([
            'document_registry_id' => $this->id,
            'document_type_code' => $this->document_type_code,
            'action' => 'unlock',
            'old_values' => ['is_locked' => true],
            'new_values' => ['is_locked' => false],
            'reason' => $reason ?: 'Document unlocked by administrator',
            'performed_by' => $user?->id
        ]);

        return true;
    }

    /**
     * Void the document
     */
    public function voidDocument($reason)
    {
        if ($this->is_voided) {
            throw new \Exception("Document {$this->full_document_number} is already voided");
        }

        if (empty($reason)) {
            throw new \ValidationException(['void_reason' => 'Void reason is required']);
        }

        $user = BackendAuth::getUser();
        
        $this->update([
            'is_voided' => true,
            'voided_at' => now(),
            'voided_by' => $user?->id,
            'void_reason' => $reason,
            'previous_status' => $this->status,
            'status' => 'voided'
        ]);

        // Create audit trail
        \Omsb\Registrar\Models\DocumentAuditTrail::create([
            'document_registry_id' => $this->id,
            'document_type_code' => $this->document_type_code,
            'action' => 'void',
            'old_values' => ['is_voided' => false, 'status' => $this->previous_status],
            'new_values' => ['is_voided' => true, 'status' => 'voided'],
            'reason' => $reason,
            'performed_by' => $user?->id
        ]);

        return true;
    }

    /**
     * Get document's complete audit history
     */
    public function getAuditHistory()
    {
        return $this->auditTrail()
            ->with(['performer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get document age in days
     */
    public function getAgeInDays()
    {
        if (!$this->created_at || !is_object($this->created_at)) {
            return 0;
        }
        
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if document is stale (older than certain days)
     */
    public function isStale($days = 90)
    {
        return $this->getAgeInDays() > $days;
    }

    /**
     * Get document summary for audit reports
     */
    public function getAuditSummary()
    {
        return [
            'document_number' => $this->full_document_number,
            'document_type' => $this->documentType && is_object($this->documentType) ? $this->documentType->name : 'Unknown',
            'status' => $this->status,
            'created_by' => $this->creator && is_object($this->creator) ? $this->creator->full_name : 'Unknown',
            'created_at' => $this->created_at,
            'current_state' => [
                'is_locked' => $this->is_locked,
                'is_voided' => $this->is_voided,
                'can_edit' => $this->canBeEdited()
            ],
            'audit_trail_count' => $this->auditTrail()->count(),
            'age_days' => $this->getAgeInDays()
        ];
    }

    /**
     * Validate document integrity
     */
    public function validateIntegrity()
    {
        $errors = [];

        // Check if documentable still exists
        if (!$this->documentable) {
            $errors[] = "Linked document object no longer exists";
        }

        // Check for duplicate numbers
        $duplicates = static::where('full_document_number', $this->full_document_number)
            ->where('id', '!=', $this->id)
            ->count();
        
        if ($duplicates > 0) {
            $errors[] = "Duplicate document number detected";
        }

        // Check sequence integrity
        $documentType = $this->documentType;
        if ($documentType && is_object($documentType) && $documentType->reset_cycle !== 'never') {
            $expectedSequence = static::where('document_type_code', $this->document_type_code)
                ->where('year', $this->year)
                ->when($documentType->reset_cycle === 'monthly', function($q) {
                    return $q->where('month', $this->month);
                })
                ->where('id', '<', $this->id)
                ->count() + 1;

            if ($this->sequence_number !== $expectedSequence) {
                $errors[] = "Sequence number integrity violation";
            }
        }

        return $errors;
    }
}