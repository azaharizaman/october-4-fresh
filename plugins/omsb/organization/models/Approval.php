<?php namespace Omsb\Organization\Models;

use Model;

/**
 * Approval Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Approval extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_approvals';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'document_type',
        'action',
        'floor_limit',
        'ceiling_limit',
        'budget_ceiling_limit',
        'non_budget_ceiling_limit',
        'from_status',
        'to_status',
        'is_active',
        'effective_from',
        'effective_to',
        'is_delegated',
        'delegated_from',
        'delegated_to',
        'transaction_category',
        'budget_type',
        'service_type',
        'staff_id',
        'site_id',
        'delegated_to_staff_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'staff_id',
        'site_id',
        'delegated_to_staff_id',
        'ceiling_limit',
        'budget_ceiling_limit',
        'non_budget_ceiling_limit',
        'from_status',
        'effective_from',
        'effective_to',
        'delegated_from',
        'delegated_to',
        'transaction_category'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'deleted_at'
    ];
}
