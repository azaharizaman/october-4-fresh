# OMSB System Architecture Changelog

## Major Architectural Changes and Business Logic Updates

This document tracks significant architectural changes, business logic modifications, and their impacts across the OMSB plugin ecosystem.

---

## October 27, 2025 - Approval System Consolidation

### 🏗️ **Architecture Change: MLAS Integration into Organization Plugin**

#### **Background**
The system previously had two separate approval mechanisms:
- Organization plugin: Basic approval rules (staff-based, amount-based)
- Workflow plugin: MLAS (Multi-Level Approval System) table with overlapping functionality

This duplication created confusion, maintenance overhead, and limited the system's ability to handle complex approval scenarios.

#### **Changes Made**

##### **1. Organization Plugin Enhancements (`omsb_organization_approvals`)**
```sql
-- Added MLAS functionality to existing approvals table:
+ approval_type VARCHAR(20) DEFAULT 'single'  -- single, quorum, majority, unanimous
+ required_approvers INT DEFAULT 1            -- How many approvals needed
+ eligible_approvers INT NULL                 -- Total eligible pool (for quorum)
+ assignment_strategy VARCHAR(20) DEFAULT 'manual' -- manual, position_based, round_robin
+ is_position_based BOOLEAN DEFAULT false
+ eligible_position_ids JSON NULL             -- Array of position IDs
+ eligible_staff_ids JSON NULL                -- Array of staff IDs
+ requires_hierarchy_validation BOOLEAN DEFAULT true
+ minimum_hierarchy_level INT NULL
+ override_individual_limits BOOLEAN DEFAULT false
+ approval_timeout_days INT NULL
+ timeout_action VARCHAR(20) DEFAULT 'revert' -- revert, escalate, auto_approve
+ escalation_approval_rule_id BIGINT NULL
+ rejection_target_status VARCHAR NULL
+ requires_comment_on_rejection BOOLEAN DEFAULT true
+ requires_comment_on_approval BOOLEAN DEFAULT false
+ allows_delegation BOOLEAN DEFAULT true
+ max_delegation_days INT NULL
+ requires_delegation_justification BOOLEAN DEFAULT false
```

##### **2. Workflow Plugin Refocus**
- **Removed**: `omsb_workflow_mlas` table (approval definitions)
- **Added**: `omsb_workflow_instances` table (execution tracking)
- **Added**: `omsb_workflow_actions` table (individual approval actions)
- **Updated**: `MLA` model now aliases to `Organization\Approval`

##### **3. New Workflow Models**

**WorkflowInstance** - Manages ongoing workflow execution:
```php
'workflow_code'         // Unique identifier
'status'               // pending, in_progress, completed, failed, cancelled
'document_type'        // Type of document being approved
'documentable'         // morphTo relationship to actual document
'current_step'         // Current approval step
'approvals_required'   // How many approvals needed for current step
'approvals_received'   // How many received so far
'approval_path'        // JSON array of approval rule IDs in sequence
```

**WorkflowAction** - Tracks individual approval actions:
```php
'action'               // approve, reject, delegate, escalate, comment
'step_sequence'        // Order in the workflow
'staff_id'            // Who took the action
'approval_rule_id'    // Which rule was applied
'comments'            // Approver comments
'action_taken_at'     // When action was performed
```

#### **Business Logic Impact**

##### **Enhanced Multi-Approver Support**
```php
// NEW: "3 out of 5 department heads must approve"
Approval::create([
    'document_type' => 'purchase_request',
    'approval_type' => 'quorum',
    'required_approvers' => 3,
    'eligible_approvers' => 5,
    'assignment_strategy' => 'position_based',
    'eligible_position_ids' => [1, 2, 3, 4, 5]
]);
```

##### **Position-based Assignment**
```php
// NEW: Any Finance Manager at any site can approve
Approval::create([
    'assignment_strategy' => 'position_based',
    'is_position_based' => true,
    'eligible_position_ids' => [$financeManagerPosition->id],
    'allow_external_site_approvers' => true
]);
```

##### **Escalation Chains**
```php
// NEW: Auto-escalate after 5 days
Approval::create([
    'approval_timeout_days' => 5,
    'timeout_action' => 'escalate',
    'escalation_approval_rule_id' => $seniorRule->id
]);
```

#### **Clear Separation of Concerns**

| **Organization Plugin** | **Workflow Plugin** |
|-------------------------|---------------------|
| Approval **Definitions** | Workflow **Execution** |
| WHO can approve | TRACKING ongoing approvals |
| HOW MUCH they can approve | Individual approval ACTIONS |
| WHICH documents | Progress monitoring |
| Approval POLICIES | Overdue management |
| Hierarchy rules | Audit trail |

#### **Migration Strategy**
1. **Zero-breaking Changes**: Existing approval rules continue to work
2. **Enhanced Capabilities**: Added fields provide new functionality
3. **Backward Compatibility**: `MLA` model aliases to `Approval`
4. **Data Migration**: No existing data needs modification

#### **Affected Systems**

##### **Procurement Plugin**
- Purchase Request approvals now support quorum-based approval
- Multi-site purchase orders can have site-specific approval rules
- Enhanced vendor payment approvals with escalation

##### **Inventory Plugin**
- Stock adjustments support multiple approver validation
- Physical count discrepancies can require department head consensus
- Inter-warehouse transfers support site-specific approval rules

##### **Reporting Systems**
- New approval analytics: average approval time, bottleneck identification
- Delegation tracking and compliance reporting
- Overdue workflow monitoring

#### **Benefits Achieved**
✅ **Eliminated Duplication**: Single source of truth for approval rules  
✅ **Enhanced Capabilities**: Complex multi-approver scenarios ("3 of 5")  
✅ **Better Integration**: Seamless with organizational hierarchy  
✅ **Improved Tracking**: Comprehensive audit trail of all actions  
✅ **Scalable Architecture**: Clean separation allows independent evolution  
✅ **Enterprise Ready**: Supports complex corporate approval workflows  

#### **Technical Metrics**
- **Database Tables**: Reduced from 2 approval tables to 1 definition + 2 execution
- **Code Duplication**: Eliminated ~300 lines of duplicate approval logic
- **Query Performance**: Consolidated approval lookups reduce database calls
- **Maintenance**: Single codebase for approval definitions reduces bugs

---

## Future Architecture Changes

### Planned Q1 2026
- **Notification System**: Real-time approval notifications via WebSocket
- **Mobile Integration**: Native mobile app for approval workflows
- **API Gateway**: RESTful APIs for external system integration

### Proposed Q2 2026  
- **AI-Powered Routing**: Machine learning for optimal approval routing
- **Blockchain Audit**: Immutable approval trail for compliance
- **Multi-Tenant Support**: Support for multiple organizations in single instance

---

## Change Impact Assessment Template

For future changes, use this template:

### **Change Overview**
- **Date**: 
- **Type**: Architecture | Business Logic | Performance | Security
- **Scope**: Plugin(s) affected
- **Breaking Changes**: Yes/No

### **Business Justification**
- **Problem**: What issue does this solve?
- **Solution**: How does this change address it?
- **Benefits**: Measurable improvements expected

### **Technical Details**
- **Database Changes**: Schema modifications
- **API Changes**: New/modified endpoints
- **Code Changes**: Files/classes affected
- **Dependencies**: New requirements or removed dependencies

### **Impact Analysis**
- **Affected Plugins**: List with specific impacts
- **Migration Required**: Yes/No, with strategy
- **Backward Compatibility**: Assessment and mitigation
- **Performance Impact**: Expected changes
- **Testing Requirements**: Validation needed

### **Rollout Plan**
- **Development**: Implementation timeline
- **Testing**: QA strategy
- **Deployment**: Production rollout plan
- **Documentation**: Updates required
- **Training**: User education needs

---

## Documentation Standards

### **When to Document Changes**
1. **Architecture Changes**: Any modification affecting multiple plugins
2. **Business Logic Changes**: New workflows or rule modifications  
3. **Database Schema Changes**: Table/column additions/modifications
4. **Integration Changes**: New external system connections
5. **Performance Optimizations**: Significant performance improvements
6. **Security Enhancements**: Authentication, authorization, or encryption changes

### **Documentation Requirements**
1. **Update Plugin READMEs**: Reflect current functionality
2. **Update Copilot Instructions**: Keep AI context current
3. **Update This Changelog**: Record architectural impacts
4. **Create Migration Guides**: For breaking changes
5. **Update API Documentation**: For interface changes

### **Review Process**
1. **Technical Review**: Architecture team validates technical soundness
2. **Business Review**: Product team validates business value
3. **Documentation Review**: Documentation team ensures completeness
4. **Approval**: Senior architect signs off on major changes

---

*This changelog ensures that architectural decisions and their business impacts are preserved for future reference and onboarding.*