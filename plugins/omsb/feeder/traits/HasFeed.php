<?php namespace Omsb\Feeder\Traits;

use Omsb\Feeder\Models\Feed;
use BackendAuth;
use Event;

/**
 * HasFeed Trait
 * 
 * Simplifies integration of Feeder plugin into any model.
 * Automatically creates feed entries for CRUD operations and custom actions.
 * 
 * Usage:
 * ```php
 * class PurchaseOrder extends Model
 * {
 *     use HasFeed;
 *     
 *     protected $feedMessageTemplate = '{actor} {action} {model} #{model_identifier}';
 *     protected $feedableActions = ['created', 'updated', 'deleted', 'approved', 'rejected'];
 *     protected $feedType = 'activity';
 * }
 * ```
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html#model-traits
 */
trait HasFeed
{
    /**
     * Boot the HasFeed trait for a model.
     */
    public static function bootHasFeed(): void
    {
        // Only attach listeners if auto-feed is enabled
        if (static::isAutoFeedEnabled()) {
            static::created(function ($model) {
                $model->createFeedEntry('created');
            });

            static::updated(function ($model) {
                // Only create feed if significant fields changed
                if ($model->shouldCreateUpdateFeed()) {
                    $model->createFeedEntry('updated');
                }
            });

            static::deleted(function ($model) {
                $model->createFeedEntry('deleted');
            });
        }
    }

    /**
     * Initialize the HasFeed trait for an instance.
     */
    public function initializeHasFeed(): void
    {
        // Define morph many relationship if not already defined
        if (!isset($this->morphMany['feeds'])) {
            $this->morphMany['feeds'] = [
                Feed::class,
                'name' => 'feedable',
                'delete' => true
            ];
        }
    }

    /**
     * Create a feed entry for this model
     * 
     * @param string $actionType Action performed (created, updated, deleted, approved, etc.)
     * @param array $additionalData Extra metadata to store with feed
     * @param string|null $customMessage Override default message template
     * @return Feed|null Created feed instance or null on failure
     */
    public function createFeedEntry(
        string $actionType,
        array $additionalData = [],
        ?string $customMessage = null
    ): ?Feed {
        // Check if this action should create a feed
        if (!$this->shouldCreateFeedForAction($actionType)) {
            return null;
        }

        // Get current user (may be null for system actions)
        $user = BackendAuth::check() ? BackendAuth::getUser() : null;

        // Generate feed message
        $message = $customMessage ?? $this->generateFeedMessage($actionType, $user);

        // Prepare feed data
        $feedData = [
            'user_id' => $user ? $user->id : null,
            'action_type' => $actionType,
            'feedable_type' => get_class($this),
            'feedable_id' => $this->id,
            'title' => $message,
            'body' => $this->getFeedBody($actionType),
            'additional_data' => array_merge(
                $this->getDefaultFeedMetadata(),
                $additionalData
            )
        ];

        try {
            $feed = Feed::create($feedData);

            // Fire event for feed creation
            Event::fire('omsb.feeder.feed.created', [$feed, $this]);

            return $feed;
        } catch (\Exception $e) {
            // Log error but don't break main operation
            \Log::error("Failed to create feed entry: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Create feed for a custom action (approve, reject, submit, etc.)
     * 
     * @param string $action Action name
     * @param array $additionalData Extra metadata
     * @param string|null $customMessage Override message
     * @return Feed|null
     */
    public function recordAction(
        string $action,
        array $additionalData = [],
        ?string $customMessage = null
    ): ?Feed {
        return $this->createFeedEntry($action, $additionalData, $customMessage);
    }

    /**
     * Generate feed message from template
     * 
     * @param string $actionType
     * @param \Backend\Models\User|null $user
     * @return string
     */
    protected function generateFeedMessage(string $actionType, $user = null): string
    {
        $template = $this->getFeedMessageTemplate();

        $replacements = [
            '{actor}' => $user ? ($user->first_name . ' ' . $user->last_name) : 'System',
            '{action}' => $this->formatActionType($actionType),
            '{model}' => $this->getFeedModelName(),
            '{model_identifier}' => $this->getFeedModelIdentifier(),
            '{timestamp}' => now()->format('Y-m-d H:i:s')
        ];

        // Add model-specific placeholders
        $replacements = array_merge($replacements, $this->getFeedTemplatePlaceholders());

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    /**
     * Get feed message template
     * Override in model to customize
     * 
     * @return string
     */
    protected function getFeedMessageTemplate(): string
    {
        return $this->feedMessageTemplate ?? '{actor} {action} {model} #{model_identifier}';
    }

    /**
     * Get custom placeholders for message template
     * Override in model to add custom replacements
     * 
     * @return array ['placeholder' => 'value']
     */
    protected function getFeedTemplatePlaceholders(): array
    {
        return [];
    }

    /**
     * Get feed body content (optional detailed description)
     * Override in model to provide detailed body
     * 
     * @param string $actionType
     * @return string|null
     */
    protected function getFeedBody(string $actionType): ?string
    {
        return null;
    }

    /**
     * Get default metadata to include in every feed
     * Override to add model-specific metadata
     * 
     * @return array
     */
    protected function getDefaultFeedMetadata(): array
    {
        $metadata = [
            'model_class' => get_class($this),
            'model_id' => $this->id,
            'timestamp' => now()->toIso8601String()
        ];

        // Add commonly tracked fields if they exist
        $trackedFields = ['status', 'total_amount', 'document_number', 'code', 'name'];
        foreach ($trackedFields as $field) {
            if (isset($this->$field)) {
                $metadata[$field] = $this->$field;
            }
        }

        return $metadata;
    }

    /**
     * Format action type for display
     * 
     * @param string $actionType
     * @return string
     */
    protected function formatActionType(string $actionType): string
    {
        $formatted = str_replace('_', ' ', $actionType);
        return ucfirst($formatted);
    }

    /**
     * Get model name for feeds
     * Override to customize display name
     * 
     * @return string
     */
    protected function getFeedModelName(): string
    {
        return class_basename(get_class($this));
    }

    /**
     * Get model identifier for feeds
     * Override to use different identifier (e.g., document_number instead of id)
     * 
     * @return string
     */
    protected function getFeedModelIdentifier(): string
    {
        // Prefer document_number, code, or name over id
        if (isset($this->document_number)) {
            return $this->document_number;
        }
        if (isset($this->code)) {
            return $this->code;
        }
        if (isset($this->name)) {
            return $this->name;
        }
        return (string) $this->id;
    }

    /**
     * Check if auto-feed is enabled for this model
     * 
     * @return bool
     */
    protected static function isAutoFeedEnabled(): bool
    {
        return property_exists(static::class, 'autoFeedEnabled')
            ? static::$autoFeedEnabled ?? true
            : true;
    }

    /**
     * Check if feed should be created for this action
     * 
     * @param string $actionType
     * @return bool
     */
    protected function shouldCreateFeedForAction(string $actionType): bool
    {
        // If feedableActions is not defined, allow all actions
        if (!property_exists($this, 'feedableActions')) {
            return true;
        }

        return in_array($actionType, $this->feedableActions ?? []);
    }

    /**
     * Determine if update should create a feed entry
     * Override to customize logic (e.g., only if certain fields changed)
     * 
     * @return bool
     */
    protected function shouldCreateUpdateFeed(): bool
    {
        // If significant fields are defined, check if any changed
        if (property_exists($this, 'feedSignificantFields')) {
            foreach ($this->feedSignificantFields as $field) {
                if ($this->isDirty($field)) {
                    return true;
                }
            }
            return false;
        }

        // By default, create feed for any update
        return true;
    }

    /**
     * Get all feeds for this model
     * 
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function feeds()
    {
        return $this->morphMany(Feed::class, 'feedable');
    }

    /**
     * Get recent feeds (last N entries)
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentFeeds(int $limit = 10)
    {
        return $this->feeds()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get feeds by action type
     * 
     * @param string $actionType
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFeedsByAction(string $actionType)
    {
        return $this->feeds()
            ->where('action_type', $actionType)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get feed timeline (formatted for display)
     * 
     * @return array
     */
    public function getFeedTimeline(): array
    {
        return $this->feeds()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($feed) {
                return [
                    'id' => $feed->id,
                    'action' => $feed->action_type,
                    'message' => $feed->title,
                    'user' => $feed->user ? $feed->user->full_name : 'System',
                    'timestamp' => $feed->created_at->diffForHumans(),
                    'metadata' => $feed->additional_data
                ];
            })
            ->toArray();
    }

    /**
     * Check if model has any feeds
     * 
     * @return bool
     */
    public function hasFeeds(): bool
    {
        return $this->feeds()->exists();
    }

    /**
     * Get feed count
     * 
     * @return int
     */
    public function getFeedCount(): int
    {
        return $this->feeds()->count();
    }

    /**
     * Delete all feeds for this model
     * Use with caution - feeds are meant to be immutable
     * 
     * @return int Number of deleted feeds
     */
    public function deleteAllFeeds(): int
    {
        return $this->feeds()->delete();
    }
}
