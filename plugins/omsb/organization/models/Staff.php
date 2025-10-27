<?php namespace Omsb\Organization\Models;

use Model;

/**
 * Staff Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Staff extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_staff';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'staff_number',
        'is_manager',
        'date_join',
        'date_resigned',
        'position',
        'qualification',
        'contact_no',
        'user_id',
        'site_id',
        'company_id',
        'service_code'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'user_id',
        'site_id',
        'company_id',
        'service_code'
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

    /**
     * Get service details for this staff
     */
    public function getServiceAttribute()
    {
        return ServiceSettings::getServiceByCode($this->service_code);
    }

    /**
     * Get service name
     */
    public function getServiceNameAttribute()
    {
        return ServiceSettings::getServiceName($this->service_code);
    }

    /**
     * Get service color
     */
    public function getServiceColorAttribute()
    {
        return ServiceSettings::getServiceColor($this->service_code);
    }

    /**
     * Check if staff belongs to specific service
     */
    public function belongsToService($serviceCode)
    {
        return $this->service_code === $serviceCode;
    }

    /**
     * Scope: Filter by service
     */
    public function scopeByService($query, $serviceCode)
    {
        return $query->where('service_code', $serviceCode);
    }

    /**
     * Get staff in same service
     */
    public function getSameServiceStaff()
    {
        if (!$this->service_code) {
            return collect();
        }

        return self::byService($this->service_code)
            ->where('id', '!=', $this->id)
            ->get();
    }
}
