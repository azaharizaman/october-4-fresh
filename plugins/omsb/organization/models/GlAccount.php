<?php namespace Omsb\Organization\Models;

use Model;

/**
 * GLAccount Model
 * 
 * Chart of accounts entries at site level for financial tracking
 *
 * @property int $id
 * @property string $account_code Unique account code
 * @property string $account_name Account name
 * @property string|null $description Account description
 * @property string $account_type Account type (asset, liability, equity, revenue, expense, contra)
 * @property string|null $account_subtype Account subtype
 * @property bool $is_active Active status
 * @property bool $is_header Header account (cannot have transactions)
 * @property int $level Account hierarchy level
 * @property int $site_id Owning site
 * @property int|null $parent_account_id Parent account
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class GlAccount extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_gl_accounts';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'account_code',
        'account_name',
        'description',
        'account_type',
        'account_subtype',
        'is_active',
        'is_header',
        'level',
        'site_id',
        'parent_account_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'description',
        'account_subtype',
        'parent_account_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'account_code' => 'required|unique:omsb_organization_gl_accounts,account_code',
        'account_name' => 'required|max:255',
        'account_type' => 'required|in:asset,liability,equity,revenue,expense,contra',
        'account_subtype' => 'nullable|in:current_asset,fixed_asset,current_liability,long_term_liability,capital,retained_earnings,operating_revenue,non_operating_revenue,operating_expense,non_operating_expense,contra_asset,contra_revenue',
        'is_active' => 'boolean',
        'is_header' => 'boolean',
        'level' => 'required|integer|min:1',
        'site_id' => 'required|integer|exists:omsb_organization_sites,id',
        'parent_account_id' => 'nullable|integer|exists:omsb_organization_gl_accounts,id'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'account_code.required' => 'Account code is required',
        'account_code.unique' => 'This account code is already in use',
        'account_name.required' => 'Account name is required',
        'account_type.required' => 'Account type is required',
        'site_id.required' => 'Site is required'
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
        'is_header' => 'boolean',
        'level' => 'integer'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'site' => [
            Site::class
        ],
        'parent_account' => [
            GlAccount::class,
            'key' => 'parent_account_id'
        ]
    ];

    public $hasMany = [
        'child_accounts' => [
            GlAccount::class,
            'key' => 'parent_account_id'
        ],
        'purchaseable_items' => [
            'Omsb\Procurement\Models\PurchaseableItem',
            'key' => 'gl_account_id'
        ]
    ];

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->account_code . ' - ' . $this->account_name;
    }

    /**
     * Get account type label
     */
    public function getAccountTypeLabelAttribute(): string
    {
        $labels = [
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'revenue' => 'Revenue',
            'expense' => 'Expense',
            'contra' => 'Contra'
        ];
        
        return $labels[$this->account_type] ?? $this->account_type;
    }

    /**
     * Get full hierarchy name
     */
    public function getFullNameAttribute(): string
    {
        $names = [$this->account_name];
        $parent = $this->parent_account;
        
        while ($parent) {
            array_unshift($names, $parent->account_name);
            $parent = $parent->parent_account;
        }
        
        return implode(' > ', $names);
    }

    /**
     * Scope: Active accounts only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Non-header accounts (can have transactions)
     */
    public function scopeTransactable($query)
    {
        return $query->where('is_header', false);
    }

    /**
     * Scope: Filter by account type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope: Filter by site
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Check if account can have transactions
     */
    public function canHaveTransactions(): bool
    {
        return !$this->is_header && $this->is_active;
    }

    /**
     * Check if account has child accounts
     */
    public function hasChildren(): bool
    {
        return $this->child_accounts()->count() > 0;
    }

    /**
     * Get parent account options for dropdown
     */
    public function getParentAccountIdOptions(): array
    {
        // Exclude self and children to prevent circular references
        return self::where('id', '!=', $this->id ?? 0)
            ->where('site_id', $this->site_id ?? 0)
            ->whereNull('deleted_at')
            ->active()
            ->orderBy('account_code')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }

    /**
     * Get site options for dropdown
     */
    public function getSiteIdOptions(): array
    {
        return Site::active()
            ->orderBy('name')
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
