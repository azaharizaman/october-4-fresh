<?php namespace Omsb\Registrar\Models;

use Model;
use Backend\Facades\BackendAuth;

/**
 * DocumentAuditTrail Model
 * 
 * Tracks all changes and actions performed on documents.
 * Provides complete audit trail for compliance and fraud prevention.
 */
class DocumentAuditTrail extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'omsb_registrar_document_audit_trails';

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'document_registry_id',
        'document_type_code',
        'action',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'performed_by',
        'performed_at',
        'metadata'
    ];

    /**
     * @var array jsonable attributes
     */
    protected $jsonable = [
        'old_values',
        'new_values',
        'metadata'
    ];

    /**
     * @var array dates
     */
    protected $dates = ['performed_at'];

    /**
     * @var array casts
     */
    protected $casts = [
        'performed_at' => 'datetime'
    ];

    /**
     * @var array validation rules
     */
    public $rules = [
        'action' => 'required|max:50',
        'reason' => 'nullable|max:500'
    ];

    /**
     * @var array relations
     */
    public $belongsTo = [
        'documentRegistry' => [\Omsb\Registrar\Models\DocumentRegistry::class],
        'documentType' => [
            \Omsb\Registrar\Models\DocumentType::class,
            'key' => 'document_type_code',
            'otherKey' => 'code'
        ],
        'performer' => [\Backend\Models\User::class, 'key' => 'performed_by']
    ];

    /**
     * Boot the model
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->performed_by) {
                $user = BackendAuth::getUser();
                $model->performed_by = $user?->id;
            }

            if (!$model->performed_at) {
                $model->performed_at = now();
            }

            $model->ip_address = request()->ip();
            $model->user_agent = request()->userAgent();
        });
    }

    /**
     * Scope for specific actions
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for recent activities
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('performed_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted action description
     */
    public function getActionDescription()
    {
        $descriptions = [
            'create' => 'Document created',
            'update' => 'Document updated',
            'status_change' => 'Status changed',
            'lock' => 'Document locked',
            'unlock' => 'Document unlocked',
            'void' => 'Document voided',
            'numbering_reset' => 'Numbering reset'
        ];

        return $descriptions[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Get change summary
     */
    public function getChangeSummary()
    {
        if (!$this->old_values || !$this->new_values) {
            return $this->getActionDescription();
        }

        $changes = [];
        $oldValues = is_array($this->old_values) ? $this->old_values : json_decode($this->old_values, true);
        $newValues = is_array($this->new_values) ? $this->new_values : json_decode($this->new_values, true);

        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? 'null';
            $changes[] = "{$field}: {$oldValue} → {$newValue}";
        }

        return implode(', ', $changes);
    }
}