<?php namespace Omsb\Workflow\Models;

/**
 * MLA (Multi Level Approval) Model
 * 
 * This is now an alias to the Organization plugin's Approval model.
 * All approval definitions are managed by the Organization plugin.
 * 
 * ARCHITECTURAL CHANGE (October 2025):
 * - MLAS functionality merged into Organization plugin's Approval table
 * - Workflow plugin now handles only execution tracking (WorkflowInstance, WorkflowAction)
 * - This model maintained for backward compatibility
 * 
 * @deprecated Use \Omsb\Organization\Models\Approval instead
 * @see /plugins/omsb/organization/README.md for approval system documentation
 * @see /plugins/omsb/workflow/README.md for workflow execution documentation
 * @see /ARCHITECTURE_CHANGELOG.md for details on this architectural change
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MLA extends \Omsb\Organization\Models\Approval
{
    // This class serves as an alias for backward compatibility
    // All functionality is now handled by the Organization plugin's Approval model
}
