<?php namespace Omsb\Procurement\Models;

use Model;

/**
 * ItemCategory Model
 * 
 * Represents categories for organizing purchaseable items
 *
 * @property int $id
 * @property string $code Unique category code
 * @property string $name Category name
 * @property string|null $description Category description
 * @property bool $is_active Active status
 * @property int|null $parent_id Parent category
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class ItemCategory extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_procurement_item_categories';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'parent_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'description',
        'parent_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_procurement_item_categories,code',
        'name' => 'required|max:255',
        'is_active' => 'boolean'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'code.required' => 'Category code is required',
        'code.unique' => 'This category code is already in use',
        'name.required' => 'Category name is required'
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
    public $belongsTo = [
        'parent' => [
            ItemCategory::class,
            'key' => 'parent_id'
        ]
    ];

    public $hasMany = [
        'children' => [
            ItemCategory::class,
            'key' => 'parent_id'
        ],
        'purchaseable_items' => [
            PurchaseableItem::class,
            'key' => 'item_category_id'
        ]
    ];

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Get full hierarchy name
     */
    public function getFullNameAttribute(): string
    {
        $names = [$this->name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($names, $parent->name);
            $parent = $parent->parent;
        }
        
        return implode(' > ', $names);
    }

    /**
     * Scope: Active categories only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Root categories (no parent)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get category options for dropdown
     */
    public function getParentIdOptions(): array
    {
        // Exclude self and children to prevent circular references
        return self::where('id', '!=', $this->id ?? 0)
            ->whereNull('deleted_at')
            ->active()
            ->orderBy('name')
            ->get()
            ->pluck('full_name', 'id')
            ->all();
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    /**
     * Get all descendant categories
     */
    public function getDescendants(): array
    {
        $descendants = [];
        
        foreach ($this->children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, $child->getDescendants());
        }
        
        return $descendants;
    }
}
