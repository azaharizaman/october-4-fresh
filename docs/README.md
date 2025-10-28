# OMSB System Documentation

Welcome to the OMSB (One Medicare Sdn Bhd) system documentation. This directory contains comprehensive documentation for all plugins and modules in the system.

## Documentation Structure

### Plugin Documentation

The `plugins/` directory contains detailed documentation for individual OMSB plugins:

- **[Workflow Plugin](plugins/workflow.md)** - Workflow execution and tracking system for approval processes
  - Purpose and capabilities
  - Architecture and models
  - Service layer API reference
  - Integration points with other plugins
  - Usage examples
  - Future improvements

## Quick Links

### Core Plugins
- **Organization** - Organizational structure, staff management, and approval rule definitions
- **Workflow** - [View Documentation](plugins/workflow.md) - Workflow execution and approval tracking
- **Procurement** - Purchase orders, vendor management, and procurement operations
- **Inventory** - Warehouse management and inventory operations
- **Registrar** - Document numbering and registration

### Support Plugins
- **Feeder** - Activity tracking and audit trail
- **Budget** - Budget management and allocation

## Contributing to Documentation

When documenting a new plugin or updating existing documentation:

1. Create a new markdown file in the appropriate subfolder (e.g., `plugins/pluginname.md`)
2. Follow the standard documentation structure:
   - Overview
   - Purpose and capabilities
   - Architecture
   - Models (with field descriptions and relationships)
   - Services (with API reference)
   - Integration points
   - Usage examples
   - Future improvements
3. Update this README to link to the new documentation
4. Use clear, concise language with code examples
5. Include diagrams where helpful (using Mermaid or ASCII art)

## Documentation Standards

### Sections to Include

#### 1. Overview
Brief introduction to the plugin/module, its namespace, version, and dependencies.

#### 2. Purpose
Clear statement of what the plugin does and what it doesn't do.

#### 3. Core Capabilities
List of main features and functionalities.

#### 4. Architecture
- Data flow diagrams
- Plugin structure
- Separation of concerns
- Design patterns used

#### 5. Models
For each model, document:
- Purpose and description
- Table name and namespace
- Key fields with types and descriptions
- Relationships (belongsTo, hasMany, morphTo, etc.)
- Scopes
- Methods

#### 6. Services
For each service class, document:
- Purpose
- Public methods with parameters, return types, and examples
- Usage patterns

#### 7. Integration Points
How the plugin integrates with:
- Other OMSB plugins
- External systems
- Frontend/backend

#### 8. API Reference
Public endpoints and methods that other plugins can use.

#### 9. Usage Examples
Real-world code examples showing:
- Basic usage
- Advanced scenarios
- Common patterns
- Error handling

#### 10. Future Improvements
Planned features and enhancement opportunities.

## Need Help?

- Check the [Copilot Instructions](../.github/copilot-instructions.md) for project-specific conventions
- Review the [Architecture Changelog](../ARCHITECTURE_CHANGELOG.md) for major changes
- Refer to [OctoberCMS Documentation](https://docs.octobercms.com/4.x/) for framework-specific information

---

**Last Updated**: January 2024  
**Maintained By**: OMSB Development Team
