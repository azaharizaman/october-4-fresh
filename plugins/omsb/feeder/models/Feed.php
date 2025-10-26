<?php namespace Omsb\Feeder\Models;

use Model;

/**
 * Feed Model
 *
 * Tracks user activities across the system with polymorphic relationships
 * to any model that needs activity logging.
 * 
 * Feeds are system-generated and cannot be edited or deleted once created.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Feed extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'omsb_feeder_feeds';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'user_id',
        'action_type',
        'feedable_type',
        'feedable_id',
        'title',
        'body',
        'additional_data',
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'user_id',
        'feedable_id',
        'title',
        'body',
    ];

    /**
     * @var array jsonable fields
     */
    protected $jsonable = ['additional_data'];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'action_type' => 'required|string|max:50',
        'feedable_type' => 'required|string|max:255',
        'feedable_id' => 'required|integer',
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [];

    /**
     * @var array morphTo relationships
     */
    public $morphTo = [
        'feedable' => []
    ];

    /**
     * @var array belongsTo relationships
     */
    public $belongsTo = [
        'user' => [\Backend\Models\User::class]
    ];

    /**
     * Get a formatted description of the feed action
     *
     * @return string
     */
    public function getDescriptionAttribute(): string
    {
        $userName = $this->user ? $this->user->first_name . ' ' . $this->user->last_name : 'System';
        $action = $this->action_type;
        $modelName = class_basename($this->feedable_type);
        
        return "{$userName} {$action} {$modelName}";
    }

    /**
     * Scope to filter by action type
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $actionType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope to filter by feedable type
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $feedableType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFeedableType($query, string $feedableType)
    {
        return $query->where('feedable_type', $feedableType);
    }

    /**
     * Scope to filter by user
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Prevent deletion of feed records
     *
     * @return bool
     */
    public function beforeDelete()
    {
        return false;
    }

    /**
     * Prevent updates to feed records after creation
     *
     * @return void
     */
    public function beforeUpdate()
    {
        if ($this->exists && $this->isDirty()) {
            throw new \Exception('Feed records cannot be modified once created.');
        }
    }
}
