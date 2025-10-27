<?php namespace Omsb\Organization\Models;

use Model;

/**
 * Service Department Settings Model
 * 
 * Manages service departments configuration for the organization.
 * Services are used for staff segregation, budget allocation, procurement workflows,
 * and inventory item categorization.
 */
class ServiceSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_service_settings';

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'services'
    ];

    /**
     * @var array jsonable attributes
     */
    protected $jsonable = ['services'];

    /**
     * @var array validation rules
     */
    public $rules = [
        'services' => 'required|array'
    ];

    /**
     * @var array Default service departments
     */
    public function initSettingsData()
    {
        $this->services = [
            'FMS' => [
                'code' => 'FMS',
                'name' => 'Facility Management Service',
                'description' => 'General facility management and maintenance',
                'is_active' => true,
                'color' => '#2563eb', // Blue
                'budget_prefix' => 'FMS',
                'approval_threshold' => 50000
            ],
            'FEMS' => [
                'code' => 'FEMS',
                'name' => 'Facility Engineering Management Service',
                'description' => 'Engineering and technical facility services',
                'is_active' => true,
                'color' => '#dc2626', // Red
                'budget_prefix' => 'FEMS',
                'approval_threshold' => 75000
            ],
            'BEMS' => [
                'code' => 'BEMS',
                'name' => 'Biomedical Equipment Maintenance Service',
                'description' => 'Medical equipment maintenance and calibration',
                'is_active' => true,
                'color' => '#16a34a', // Green
                'budget_prefix' => 'BEMS',
                'approval_threshold' => 100000
            ],
            'CLS' => [
                'code' => 'CLS',
                'name' => 'Cleaning Service',
                'description' => 'Cleaning and sanitation services',
                'is_active' => true,
                'color' => '#ea580c', // Orange
                'budget_prefix' => 'CLS',
                'approval_threshold' => 30000
            ],
            'HWMS' => [
                'code' => 'HWMS',
                'name' => 'Hospital Waste Management Service',
                'description' => 'Medical and general waste management',
                'is_active' => true,
                'color' => '#7c2d12', // Brown
                'budget_prefix' => 'HWMS',
                'approval_threshold' => 40000
            ],
            'LLS' => [
                'code' => 'LLS',
                'name' => 'Linen and Laundry Service',
                'description' => 'Laundry and linen management services',
                'is_active' => true,
                'color' => '#7c3aed', // Purple
                'budget_prefix' => 'LLS',
                'approval_threshold' => 35000
            ],
            'ADM' => [
                'code' => 'ADM',
                'name' => 'Administration',
                'description' => 'Administrative and general management',
                'is_active' => true,
                'color' => '#374151', // Gray
                'budget_prefix' => 'ADM',
                'approval_threshold' => 25000
            ]
        ];
    }

    /**
     * Get all active services
     */
    public static function getActiveServices()
    {
        $instance = self::getServiceInstance();
        $services = $instance->services ?? [];
        
        return collect($services)->filter(function ($service) {
            return $service['is_active'] ?? true;
        })->toArray();
    }

    /**
     * Get or create service settings instance
     */
    protected static function getServiceInstance()
    {
        $instance = self::first();
        
        if (!$instance) {
            $instance = new self();
            $instance->initSettingsData();
            $instance->save();
        }
        
        return $instance;
    }

    /**
     * Get service by code
     */
    public static function getServiceByCode($code)
    {
        $services = self::getActiveServices();
        return $services[$code] ?? null;
    }

    /**
     * Get services as dropdown options
     */
    public static function getServiceOptions()
    {
        $services = self::getActiveServices();
        $options = [];
        
        foreach ($services as $code => $service) {
            $options[$code] = $service['name'] . ' (' . $code . ')';
        }
        
        return $options;
    }

    /**
     * Get services for dropdown with simple format
     */
    public static function getServiceDropdownOptions()
    {
        $services = self::getActiveServices();
        $options = [];
        
        foreach ($services as $code => $service) {
            $options[$code] = $service['name'];
        }
        
        return $options;
    }

    /**
     * Get service name by code
     */
    public static function getServiceName($code)
    {
        $service = self::getServiceByCode($code);
        return $service ? $service['name'] : $code;
    }

    /**
     * Get service color by code
     */
    public static function getServiceColor($code)
    {
        $service = self::getServiceByCode($code);
        return $service ? $service['color'] : '#6b7280';
    }

    /**
     * Get services with budget prefix filter
     */
    public static function getServicesWithBudgetPrefix($prefix = null)
    {
        $services = self::getActiveServices();
        
        if (!$prefix) {
            return $services;
        }
        
        return collect($services)->filter(function ($service) use ($prefix) {
            return ($service['budget_prefix'] ?? '') === $prefix;
        })->toArray();
    }

    /**
     * Validate service code
     */
    public static function isValidServiceCode($code)
    {
        $services = self::getActiveServices();
        return isset($services[$code]);
    }

    /**
     * Get approval threshold for service
     */
    public static function getApprovalThreshold($code)
    {
        $service = self::getServiceByCode($code);
        return $service ? ($service['approval_threshold'] ?? 50000) : 50000;
    }

    /**
     * Get all service codes
     */
    public static function getServiceCodes()
    {
        return array_keys(self::getActiveServices());
    }

    /**
     * Check if service requires special approval workflow
     */
    public static function requiresSpecialApproval($code, $amount = 0)
    {
        $threshold = self::getApprovalThreshold($code);
        return $amount > $threshold;
    }
}