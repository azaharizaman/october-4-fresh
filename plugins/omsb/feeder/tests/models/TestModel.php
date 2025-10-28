<?php namespace Omsb\Feeder\Tests\Models;

use Model;
use Omsb\Feeder\Traits\HasFeed;

/**
 * Test Model for HasFeed Trait Testing
 * 
 * Provides a simple model implementation for unit testing
 * the HasFeed trait functionality without external dependencies.
 */
class TestModel extends Model
{
    use HasFeed;

    public $table = 'test_models';

    protected $fillable = [
        'name',
        'code',
        'status',
        'description',
    ];

    /**
     * HasFeed trait configuration
     */
    protected $feedMessageTemplate = '{actor} {action} {model} "{name}"';
    protected $feedableActions = ['created', 'updated', 'deleted', 'approved', 'rejected', 'completed'];
    protected $feedSignificantFields = ['name', 'code', 'status'];

    /**
     * Override to provide custom placeholders for testing
     */
    protected function getFeedTemplatePlaceholders(): array
    {
        return [
            '{custom_code}' => $this->code ?? 'N/A',
        ];
    }
}
