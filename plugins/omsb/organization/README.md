# Organization Plugin

## Overview

The Organization plugin manages the hierarchical structure of companies, sites, warehouses, and staff for One Medicare Sdn Bhd. It provides a comprehensive system for organizing business entities with multi-level hierarchies.

## Key Concepts

### Models

#### Company
The Company model represents business entities within the organization. Companies can form hierarchical structures by referencing parent companies, allowing for complex organizational trees.

**Features:**
- Hierarchical structure with parent-child relationships
- Multiple addresses per company
- Primary address selection from company addresses
- Multiple sites per company
- Comprehensive validation rules

**Relationships:**
- `belongsTo`: `parent` (Company), `address` (Address)
- `hasMany`: `children` (Company), `addresses` (Address), `sites` (Site)

**Key Methods:**
- `getDisplayNameAttribute()`: Returns formatted display name (code - name)
- `getParentIdOptions()`: Returns dropdown options for parent company selection (prevents circular references)
- `getAddressIdOptions()`: Returns dropdown options for primary address selection (only addresses belonging to this company)

**Validation:**
- `code`: Required, unique across all companies
- `name`: Required, minimum 3 characters

#### Address
Address model stores physical addresses that can be referenced by companies, sites, and warehouses. This centralized approach minimizes errors and reduces the need to update addresses in multiple locations.

**Features:**
- Defined at company level
- Complete address information (street, city, state, postcode, country, region)
- Unique constraint on full address to prevent duplicates
- Formatted address display

**Relationships:**
- `belongsTo`: `company` (Company)

**Key Methods:**
- `getFullAddressAttribute()`: Returns formatted complete address string

**Validation:**
- All address fields required except region
- Unique constraint on combined address fields

#### Site
Sites represent physical locations or branches within the organization.

**Relationships:**
- `belongsTo`: `parent` (Site), `company` (Company), `address` (Address)
- `hasMany`: `children` (Site), `warehouses` (Warehouse from Inventory plugin)

#### Warehouse
Managed by the Inventory plugin. Warehouses are storage locations within sites.

#### Staff
Staff members with hierarchical relationships for approval workflows.

#### GLAccount
Chart of accounts entries at site level for financial tracking.

## Backend Interface

### Companies Controller

The Companies controller provides full CRUD operations for managing companies through the OctoberCMS backend.

**Features:**
- List view with searching, sorting, and pagination
- Form view with tabbed interface
- Relation management for addresses, sites, and child companies
- File upload for company logos

**Behaviors:**
- `FormController`: Handles create, update, and preview operations
- `ListController`: Manages the company list view
- `RelationController`: Manages related records (addresses, sites, children)

**Tabs:**
1. **Primary Tab**: Basic company information (code, name, parent company, logo, primary address)
2. **Addresses Tab**: Manage company addresses with inline create/edit/delete
3. **Sites Tab**: Manage company sites
4. **Child Companies Tab**: View and manage child companies in the hierarchy

### Navigation

The Organization menu appears in the backend navigation with the following structure:
- **Companies**: Main company management interface
- **Sites**: Site management interface

## Database Structure

### Companies Table (`omsb_organization_companies`)
- `id`: Primary key
- `code`: Unique company identifier
- `name`: Company name
- `logo`: Company logo file path (optional)
- `parent_id`: Foreign key to parent company (nullable)
- `address_id`: Foreign key to primary address (nullable)
- `created_at`, `updated_at`, `deleted_at`: Timestamps

### Addresses Table (`omsb_organization_addresses`)
- `id`: Primary key
- `company_id`: Foreign key to company (nullable)
- `address_street`: Street address
- `address_city`: City name
- `address_state`: State/province (default: Sarawak)
- `address_postcode`: Postal code
- `address_country`: Country (default: Malaysia)
- `region`: Optional region identifier
- `created_at`, `updated_at`, `deleted_at`: Timestamps

## Usage Examples

### Creating a Company with Hierarchy

1. Navigate to Organization > Companies
2. Click "New Company"
3. Enter company code (e.g., "HQ") and name
4. Optionally select a parent company to create hierarchy
5. Upload a logo if desired
6. Save the company
7. Switch to "Addresses" tab to add company addresses
8. Once addresses are added, return to the primary tab and select a primary address
9. Switch to "Sites" tab to add company sites

### Managing Addresses

1. Open an existing company for editing
2. Navigate to the "Addresses" tab
3. Click "Create" to add a new address
4. Fill in address details (street, city, state, postcode, country)
5. Save the address
6. Return to the primary tab to select this as the primary address if desired

### Viewing Company Hierarchy

1. Open a parent company for editing
2. Navigate to the "Child Companies" tab
3. View all companies that have this company as their parent
4. Note: Child companies are managed through their own forms by setting the parent_id

## Technical Implementation Details

### Form Field Configuration
Form fields are defined in `models/company/fields.yaml` with:
- Primary fields on the main tab
- Secondary tabs for related data (addresses, sites, children)
- Conditional display logic (e.g., address selection only appears after company is saved)

### Relation Configuration
Relations are configured in `controllers/companies/config_relation.yaml`:
- **Addresses**: Full CRUD with create/delete buttons
- **Sites**: Full CRUD with create/delete buttons
- **Children**: View-only with add/remove capabilities

### Validation Rules
Validation is handled at the model level:
- Company code must be unique
- Company name must be at least 3 characters
- Address fields are required (except region)
- Duplicate addresses are prevented by unique constraint

## Integration Points

### With Inventory Plugin
- Sites reference warehouses from the Inventory plugin
- Warehouse model: `Omsb\Inventory\Models\Warehouse`

### With Other OMSB Plugins
- Organization provides the foundational structure for other plugins
- Staff hierarchy used for approval workflows (Workflow plugin)
- Site structure used for inventory management (Inventory plugin)
- Company and site data used for procurement (Procurement plugin)

## Development Guidelines

When extending this plugin:
1. Follow OctoberCMS conventions for models, controllers, and views
2. Use validation traits for model validation
3. Use soft deletes for all models to maintain data integrity
4. Document relationships clearly in model comments
5. Use proper namespacing for cross-plugin references
6. Maintain backward compatibility when modifying database structure

## Future Enhancements

Potential areas for expansion:
- Staff management interface
- GL Account configuration
- Advanced reporting on organizational hierarchy
- Bulk import/export of companies and addresses
- Company contact management
- Document attachment support
