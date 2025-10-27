# Copilot Instructions for oc-polaris

## Project Overview
- This project is a modular OctoberCMS application, extended with custom plugins and themes for the One Medicare Sdn Bhd.
- Core structure: `modules/` (core features), `plugins/omsb/*` (domain-specific plugins), `themes/` (contain css and site structure definition which is less important since most of the user will be accessing the backend side of OctoberCMS and that part look and feel is defined by the default OctoberCMS Backend theme), and `config/` (environment/configuration).
- Built on top of OctoberCMS latest version that utilized Laravel 12 with PHP 8.2 and above supported. All custom codebase (other than config) resides within `/plugins/omsb` folder and strictly adhere to OctoberCMS conventions for plugins structure. Though OctoberCMS is a CMS first, it however includes a customizable and extendable backend administration panel that can be used to build complex business applications, and that is what this project is all about.
- `/plugins/october/test` is a playground plugin created by the creator of OctoberCMS for testing and demo purposes. It is not part of the production codebase, but can be referenced for learning, code samples, and experimentation as most of the way the code is written following OctoberCMS conventions are included here.

## Documentation Standards

### **Architecture Documentation**
- **Major Changes**: All significant architectural changes are documented in `ARCHITECTURE_CHANGELOG.md`
- **Plugin Documentation**: Each plugin maintains a comprehensive `README.md` with business logic, models, and integration points
- **Business Logic Changes**: When business logic changes affect multiple plugins or core workflows, update both plugin READMEs and the architecture changelog
- **Migration Documentation**: Database schema changes are documented in migration files with clear comments explaining business rationale

### **When to Update Documentation**
1. **Architecture Changes**: Any modification affecting multiple plugins
2. **Business Logic Changes**: New workflows, approval rules, or process modifications
3. **Database Schema Changes**: Table/column additions or structural modifications
4. **Integration Changes**: New cross-plugin relationships or external system connections
5. **Major Bug Fixes**: Fixes that change expected behavior or business rules

### **Documentation Locations**
- `ARCHITECTURE_CHANGELOG.md`: Major architectural changes and their business impact
- `plugins/omsb/*/README.md`: Plugin-specific functionality and business logic
- `.github/copilot-instructions.md`: This file - architectural patterns and development guidelines
- Migration files: Database changes with business context in comments

## Key Architectural Patterns
- **Modules**: Core features (e.g., `backend`, `cms`, `editor`, `media`, `system`) are in `modules/`. Each module is self-contained with its own controllers, models, assets, and tests. Modules is just like plugins but provided by OctoberCMS core team and served the core functionalities of OctoberCMS itself. For complex backend views, partials and UI/UX along with JS and Ajax implementation can be referred to in these modules when required. Some of the way OctoberCMS handles these UI implementation not even surfaced in the documentation, thus referring to these core modules is very helpful.
- **Plugins**: Domain-specific logic is in `plugins/omsb/*`. Each plugin follows standardized structure:
  ```
  controllers/     # Lean controllers, request/response handling only (https://docs.octobercms.com/4.x/extend/system/controllers.html)
  services/        # Business logic classes (e.g., AdjustmentService)
  models/          # Eloquent models with validation traits
  traits/          # Reusable model traits
  behaviors/       # OctoberCMS repeatable controller behaviors or model behaviors (https://docs.octobercms.com/4.x/extend/system/behaviors.html)
  updates/         # Database migration files
  helpers/         # Utility/helper functions (e.g., StaffHelper, WorkflowHelper)
  classes/         # Jobs, events, listeners, abstractions, etc each within its own subfolder
  tests/           # PHPUnit tests extending PluginTestCase
  ```
  Each plugins may have one or more models and controllers depending on the complexity of the domain it is trying to solve. Always refer to OctoberCMS plugin development documentation for more details (https://docs.octobercms.com/4.x/extend/system/plugins.html). Each model defined in its own class file and often (if scaffolded using `php artisan october:plugin` command) will also comes with a set of columns and field definition that is being used to generate the backend forms and list views automatically by OctoberCMS. These definitions are located in `models/fields.yaml` and `models/columns.yaml` respectively. These YAML files can be further customized to include more fields or columns as required by the business domain. A model can also have many coulmns and fields definition that serve different purposes throughout the application (e.g., a compact view for dropdown selection, a detailed view for full form, etc) and these can be defined in separate YAML files and referenced in the controller as required.

  Each models that implement CRUD operations will often have a corresponding controller that handle the request/response cycle and interact with the model to perform the required operations. Controllers should be kept lean, delegating business logic to service classes located in `services/` folder. This separation of concerns ensures maintainability and testability of the codebase. Use of listeners and events is also encouraged for decoupling components and promoting extensibility. Controllers also responsible in determining the UI/UX experience by defining which views, partials, and assets to be loaded for each backend page. OctoberCMS also provides a powerful javascript framework that can be utilized to enhance the user experience in the backend. This includes the use of AJAX handlers, widgets, and other interactive elements that can be defined within the controller and associated views either through its Data Attributes API or Javascript API. Full documentatuion for these implementation can be found in OctoberCMS documentation (https://docs.octobercms.com/4.x/ajax/attributes-api.html) and https://docs.octobercms.com/4.x/ajax/javascript-api.html. To tie this api to the PHP codebase, define it as a PHP function the page, partial or layout PHP section, or inside CMS components. Handler names should use the onSomething pattern, for example, onName. All handlers support the use of updating partials as part of the AJAX request (https://docs.octobercms.com/4.x/ajax/handlers.html#ajax-handlers).

- **Views**: Backend views use Twig templates located in `views/` within each plugin's controller (https://docs.octobercms.com/4.x/extend/system/views.html).
- **Themes**: Frontend themes (e.g., `thebakerdev-zenii`) use TailwindCSS and Laravel Mix for asset management. See each theme's `README.md` for build instructions. Themes are non essential for backend-focused applications but can be customized for frontend needs.
- **Config**: All environment and service configuration is in `config/`. OctoberCMS also allows custom config files per plugin located in each plugin's `config/` folder. this can be benificial for putting some domain specific configuration that is only relevant to that particular plugin that does not change that often or at all. For example, defining some constant values that is used throughout the plugin codebase or enum values. While its possible to store these constant values in the database via a settings model, however if these values are not expected to change that often or at all, its better to define them in the config file for better performance and simplicity. Plugin can access its own config file via the `Config` facade or the `config()` helper function by using the plugin namespace as the prefix. For example, if there is a config file located in `plugins/omsb/procurement/config/settings.php`, it can be accessed via `Config::get('omsb.procurement.settings.some_key')` or `config('omsb.procurement.settings.some_key')` (https://docs.octobercms.com/4.x/extend/settings/file-settings.html#accessing-configuration-values).
**Settings**: While file based settings or config settings is normally change by technical admin or maintainer of this application, backend user with some administrative role may need to change some settings via the backend UI. For this purpose, plugins may have settings pages defined in `settings/` folder. These settings can be accessed via the OctoberCMS backend settings area and are stored in the database. For such settings that need to be change by user easily from the Settings page, it must be implemented by using Settings Model `System\Classes\SettingsModel` for easy management (https://docs.octobercms.com/4.x/extend/settings/model-settings.html).
**Dashboards and Reports**: October CMS features a flexible dashboard system capable of hosting multiple dashboards with configurable access. Each dashboard can contain multiple widgets to display various data types. October CMS comes with several widget types, including graph, table and others. The default widgets are sufficiently flexible to cover most common reporting scenarios, such as displaying sales or traffic information. In addition, developers can create new widget types for scenarios where the default widgets do not meet their needs (https://docs.octobercms.com/4.x/extend/dashboards/dash-controller.html).

Report widgets can be used on the backend dashboard and in other backend report containers. Report widgets must be registered in the plugin registration file.

The report widget classes reside inside the reportwidgets directory of a plugin. As any other plugin class, generic widget controllers should belong to the plugin namespace. Similarly to all backend widgets, report widgets use partials and a special directory layout. Example directory layout:
```
├── reportwidgets
|   ├── trafficsources
|   |   └── partials
|   |       └── _widget.php  ← Partial File
|   └── TrafficSources.php  ← Widget Class
```

## Developer Workflows
- **Install dependencies**: Use `composer install` for PHP and `npm install` in theme directories for JS/CSS.
- **Build assets**: For themes, run `npm run dev` or `npm run watch` in the theme directory (e.g., `themes/thebakerdev-zenii`).
- **Testing**:
  - Core and plugin tests: Run `phpunit` in the project root or in a plugin directory with a `phpunit.xml`.
  - Plugin tests use `PluginTestCase` and may require plugin registration/bootstrapping in `setUp()`.
  - To change the test DB engine, set `useConfigForTesting` in `config/database.php` or override with `config/testing/database.php`.
- **Database migrations**: Use `php artisan october:migrate` (core) and `php artisan plugin:refresh Vendor.Plugin` (will destroy db data so not to be run on production).
- **Migration Foreign Key Compatibility**: **CRITICAL** - When creating foreign key constraints, ensure the foreign key column type exactly matches the referenced column type. Common mismatches to avoid:
  - OctoberCMS `backend_users.id` is `INT UNSIGNED` (not `BIGINT UNSIGNED`)
  - Use `$table->unsignedInteger('user_id')` when referencing `backend_users.id`
  - Use `$table->foreignId()` only when referencing tables with `BIGINT UNSIGNED` primary keys (created with `$table->id()`)
  - **Always verify target table structure before creating foreign keys** to prevent "incompatible foreign key assignment" errors
  - Example of correct foreign key for backend users:
    ```php
    $table->unsignedInteger('user_id')->nullable();
    $table->foreign('user_id')->references('id')->on('backend_users')->nullOnDelete();
    ```

## Project-Specific Conventions 
- **Service Layer**: Business logic is extracted into service classes (e.g., `AdjustmentService`, `PurchaseRequestService`) that handle complex operations and maintain separation of concerns.
- **Model Architecture**: Models use OctoberCMS models traits (https://docs.octobercms.com/4.x/extend/database/traits.html) and follow PHP 8.2 standards with return type declarations. Many models include morphTo relationships for flexible associations.
- **Nullable Field Handling**: **CRITICAL** - When a migration field is defined as `nullable()`, the corresponding model **MUST** include that field in the `$nullable` property to prevent "Incorrect integer value" errors. HTML forms send empty strings (`''`) but databases expect `NULL` for nullable foreign keys.
  - Example: If migration has `$table->foreignId('parent_id')->nullable()`, model must have:
    ```php
    protected $nullable = ['parent_id'];
    ```
  - Apply to ALL nullable foreign keys: `parent_id`, `company_id`, `site_id`, `user_id`, etc.
  - Also include nullable decimals, dates, and other fields that can receive empty strings from forms
- **Plugin structure**: Always use OctoberCMS plugin conventions. OMSB plugins follow modern PHP 8.2 standards with comprehensive PHPDoc blocks.
- **Testing**: Extend `PluginTestCase` for plugin tests. Register/boot plugins in `setUp()` if testing with dependencies.
- **Asset management**: Themes use TailwindCSS and Laravel Mix. Edit `tailwind.config.js` for theme customization.
- **Event-driven extensions**: Editor extensions are registered via the `editor.extension.register` event (see `modules/editor/README.md`).
- **Naming**: OMSB plugins are namespaced under `Omsb\` and follow clear domain separation (Inventory, Procurement, Organization, etc.).

## Integration Points
- **Laravel Foundation**: OctoberCMS is built on Laravel 12; use Laravel features where appropriate, but follow OctoberCMS conventions for structure.
- **Vue Components**: Editor and some modules use Vue for client-side features (see `modules/editor/vuecomponents/`).
- **External assets**: Themes may use external icon sets, illustrations from [unDraw](https://undraw.co/), and Tailwind plugins.
- **Data/Audit Trailing and Tracking**: The Feeder plugin provides activity tracking via morphTo relationships - models can have feeds that track user actions (e.g., `$user->first_name created this $model->title`).
- **Cross-plugin dependencies**: Many plugins reference each other (e.g., Workflow integrates with Organization for staff management, Inventory connects to Procurement for item definitions and manages warehouses within organizational sites).

## Domain-Specific Plugin Details

### Organization
Core plugin managing companies, sites, and staff with multi-hierarchy structure. Hierarchical staff structure enables multi-level approval workflows where staff with creator permission can create records and staff with approver permission can approve records created by staff under them in the hierarchy. This permission is also identified by transaction type, or in this application is called a Document. A creator or approver may have one or multiple documents that he/she can create and/or approve. Every creator and approver staff must be assigned to a site or sites and each one will have a ceiling of how much a transaction value they can create or approve. Organization plugin manages the organizational structure where each site may have warehouses managed by the Inventory plugin. Sites with inventory operations have their warehouses managed within the Inventory plugin scope, with each site potentially having one designated as the receiving warehouse where all Purchase Order line items are received when fulfilled. This plugin also determines the level of data access based on the site assignment of the logged in staff, thus affecting the depth of report generation, dropdown selection, and data listing throughout the system.

**Key Models:**
- `Site`: Represents physical locations that may have warehouses managed by Inventory plugin; manages GL account definitions for financial integration
- `Staff`: User accounts with hierarchical relationships for approval workflows
- `GLAccount`: Chart of accounts entries at site level for financial tracking

**Note:** Warehouses are now managed by the Inventory plugin but maintain relationships to organizational sites.

### Procurement
Core operations plugin managing the purchase lifecycle from requisition to payment. **Owns the master catalog of all Purchaseable Items** (`PurchaseableItem` model) - the single source of truth for everything that can be purchased.

**Operational Workflow:**
- All purchases are handled by Purchasing Dept staff (at HQ and sites)
- HQ purchasing staff can create Purchase Orders for HQ and/or multiple sites under their hierarchy (each PO line specifies target site)
- When vendor fulfills a PO:
  - **Inventory items** (`is_inventory_item = true`) → Goods Receipt Note created at site's receiving warehouse
  - **Non-inventory items** (`is_inventory_item = false`) → Delivery Order created (final document, no warehouse receipt)
- Inventory items can **only** be received at sites with ≥1 active warehouse

**PurchaseableItem Model Structure:**
- `is_inventory_item` (boolean): Determines fulfillment routing (GRN vs. DO)
  - **Immutable rule**: Cannot be changed if item has positive combined QoH across all warehouses
  - Use case: Prevents reclassification of actively stocked items that would break ledger integrity
- `item_type` (required enum): Asset classification (`consumable`, `equipment`, `spare_part`, `asset`, etc.)
  - Used for reporting, GL mapping, and categorization
  - Independent of `is_inventory_item` flag (e.g., "spare parts" can be inventory or non-inventory)
- `item_category_id`: References `ItemCategory` (defined within Procurement plugin as metadata)
- `gl_account_id`: For non-inventory items, maps to GL account in Organization plugin's site-level chart of accounts
  - Used for automatic expense/asset journal entries on Delivery Order completion
- Other fields: `code`, `name`, `description`, `unit_of_measure`, `barcode`, etc.

**Key Relationships:**
- `hasMany` → `Omsb\Inventory\Models\WarehouseItem`: One purchaseable item can have multiple warehouse-level SKUs
- `belongsTo` → `ItemCategory`: Item categorization for reporting/filtering
- `belongsTo` → `GlAccount` (via Organization plugin): Non-inventory GL mapping

**Purchase Order Line Item Logic:**
- Each PO line specifies:
  - `purchaseable_item_id`: References master item catalog
  - `site_id`: Target site for delivery
  - `quantity`, `unit_price`, `total_amount`
- When PO is approved and fulfilled:
  - System checks `PurchaseableItem->is_inventory_item`
  - If `true`: Routes to Goods Receipt Note → creates `InventoryLedger` entries at site's receiving warehouse
  - If `false`: Routes to Delivery Order → records expense/asset journal entry using `gl_account_id`

**Integrations:**
- Organization plugin: Site/warehouse structure, staff hierarchy for approval workflows, GL account definitions
- Workflow plugin: Document workflows (Purchase Request, Purchase Order, Vendor Quotation, Goods Receipt Note, Delivery Order) with status transition rules and approver roles
- Feeder plugin: Activity tracking for all procurement documents

**Reports:**
- Purchase Request Report
- Purchase Order Report
- Vendor Performance Report
- Procurement Budget Report

### Inventory
Core operations plugin managing **inventory-type Purchaseable Items only** (items where `is_inventory_item = true`) **and all warehouse operations**. **References Procurement's `PurchaseableItem` model** for item definitions but maintains its own inventory-specific data and manages all warehouse entities.

**Key Principle:** *All inventory items are purchaseable items, but not all purchaseable items are inventory items.*

**Warehouse Management:**
The Inventory plugin now owns and manages all warehouse entities, including:
- `Warehouse`: Storage locations within organizational sites
- **Receiving Warehouse Logic**: Each site with multiple active warehouses must have one designated as the receiving warehouse (configurable)
- **Multi-UOM Support**: Warehouses can handle multiple Units of Measure per item with proper conversion tracking

**Two-Level Item Structure:**
1. **Master Items** (from `PurchaseableItem` in Procurement): Define "what can be stocked" (code, name, category, unit)
2. **Warehouse Items (SKUs)**: Define "where and how much" - warehouse-level stock records with:
   - `purchaseable_item_id`: References master item
   - `warehouse_id`: References specific warehouse (managed by Inventory plugin)
   - `quantity_on_hand`: Current stock level
   - `barcode`, `serial_number` (if applicable)
   - **Multi-UOM capabilities**: Support for multiple UOMs per warehouse item
   - **Uniqueness constraint**: One `PurchaseableItem` can only be referenced once per warehouse (no duplicates)

**InventoryLedger System:**
- Double-entry qty tracking: Every increase matched with decrease across warehouses/in-transit locations
- Example: Goods Receipt from vendor
  - **Increase**: Receiving warehouse QoH (+10 units)
  - **Decrease**: In-Transit virtual warehouse (-10 units)
- Each ledger entry records:
  - `warehouse_item_id`: Affected SKU
  - `document_type`, `document_id`: Source transaction (morphTo relationship)
  - `quantity_change`: +/- value
  - `balance_after`: Running balance
  - `cost_per_unit`: For valuation (FIFO/LIFO/Average Cost)
  - `timestamp`, `user_id`: Audit trail

**Fulfillment Integration with Procurement:**
- Goods Receipt Note creation:
  - Triggered when PO line item has `purchaseable_item.is_inventory_item = true`
  - Must be created at site with ≥1 active warehouse
  - Creates `WarehouseItem` record if first receipt for that item at warehouse
  - Generates paired `InventoryLedger` entries (increase receiving warehouse, decrease in-transit)
- Delivery Order (non-inventory):
  - Bypasses Inventory plugin entirely
  - Handled by Procurement plugin for GL recording

**Multi-UOM System:**
- **Master UOM**: Defined at purchaseable item level (HQ's preferred UOM)
- **Warehouse UOMs**: Each warehouse can use multiple UOMs per item with conversion factors
- **UOM Conversion Rules**: Validated conversion paths between UOMs (e.g., 1 Box = 24 Each)
- **Transaction Tracking**: All movements recorded in both transaction UOM and default UOM
- **Physical Counting**: Supports counting in multiple UOMs with automatic conversion

**Month-End Processes:**
- Auto-generates inventory valuation report based on selected costing method (FIFO/LIFO/Average)
- Records closing balances as next month's opening balances
- Locks ledger entries for closed periods

**Integrations:**
- Procurement plugin: References `PurchaseableItem` for item master data, receives goods via GRN
- Organization plugin: Site/staff structure for locations and hierarchy; warehouses managed by Inventory but belong to organizational sites
- Workflow plugin: Document status transitions (MRN, MRI, Stock Adjustment, Stock Transfer, Physical Count)
- Feeder plugin: Activity tracking for inventory documents

**Operations Handled:**
- Warehouse management (storage locations within sites)
- Material Received Notes (MRN) - stock entry points
- Material Request Issuance (MRI) - stock exit points  
- Stock adjustment (qty corrections)
- Stock transfer (between warehouses)
- Physical counts (inventory counting)
- Stock reservation/allocation
- Multi-UOM conversions and tracking
- Inventory period management and month-end closing

**Reports:**
- Inventory Movement Report
- Inventory Valuation Report  
- Physical Count Report
- Item Usage Report
- Warehouse Performance Report

### Registrar
Document numbering patterns and registration management. Each document type can have its own numbering pattern defined in the Registrar plugin.

**Default Pattern:** `SITECODE-DOCUMENTCODE-YYYY-#####` (e.g., `HQ-PR-2024-00001`)

**Features:**
- Custom prefix/suffix per document type
- Running number reset options: yearly or continuous
- Auto-increments year part when reset interval is yearly
- Tracks all issued document numbers to avoid duplication (even for cancelled/deleted documents)
- Soft-delete: Deleted documents remain in DB for auditing but are hidden from interface

**Document Statuses:**
- Draft (editable/deletable)
- Submitted
- Reviewed
- Approved
- Rejected
- Cancelled
- Completed

**Status Transition Rules:**
- Some transitions require approval from staff with approver permission
- Some transitions can be performed by creator
- Status transition logic defined in Workflow plugin
- Transition history recorded with timestamp and staff details
- Maximum approval days before fallback to Draft (overdue handling)
- Only Draft status documents can be edited or deleted

**Integration:**
- Works with Workflow plugin for status transition definitions
- Used by all document-based plugins (Procurement, Inventory, etc.)

### Workflow
Keeps track of every workflow definition and status transition rules. Each document type can have its own workflow definition with multiple statuses and transition rules.

**Key Concepts:**
- **Workflow Definition**: Unique workflow code referencing document type, statuses, and transition paths
- **Status Transition**: Changes document from status A to status B via in-transition status
- **Approver Roles**: Each transition can have specific approver roles defined
- **Approval Hierarchy**: Documents can only be approved by staff in hierarchy above creator

**Approval Flow Example (Purchase Request):**
1. Staff creates PR (Draft status)
2. Submits PR → Manager approves (Submitted → Reviewed)
3. Department Head approves (Reviewed → Approved)
4. Finance Manager final approval (Approved → Completed)
- Each level can have amount-based rules (e.g., manager approves up to $10K, dept head up to $50K)

**Rejection Handling:**
- Rejected documents revert to Draft or previous status (configurable)
- Rejection requires reason comment from approver
- Approval actions do not require comments

**Constraints:**
- Document must be in only one active workflow at any time
- Only staff with assigned approver role can approve that document type
- Staff must be in hierarchy above creator to approve

**Integration:**
- Used by Procurement, Inventory, and other document-driven plugins
- References Organization plugin for staff hierarchy

### PettyCash
-- KIV --

### Feeder
Activity tracking plugin that logs all user actions across the system. Uses morphTo relationships for flexible model associations.

**Feed Item Structure:**
- `user_id`: Staff who performed action
- `action_type`: create, update, delete, approve, reject, etc.
- `feedable_type`, `feedable_id`: morphTo relationship to target model
- `timestamp`: When action occurred
- `additional_data`: JSON field for action-specific details

**Features:**
- Centralized activity logging (removes need for per-plugin logging)
- Filterable by user, action type, target model, date range
- Sidebar view component for displaying recent activities
- Customizable feed display per model type

**Usage Example:**
When user creates Purchase Request:
```php
Feed::create([
    'user_id' => BackendAuth::getUser()->id,
    'action_type' => 'create',
    'feedable_type' => PurchaseRequest::class,
    'feedable_id' => $pr->id,
    'additional_data' => ['total_amount' => $pr->total_amount]
]);
```

**Integration:**
- Used by all OMSB plugins for activity tracking
- Provides unified audit trail across modules

## Common Patterns

### Cross-Plugin Item References
- **Never duplicate item master data**: Inventory models reference Procurement's `PurchaseableItem` via `belongsTo`
- **Standard relationship pattern**:
  ```php
  public $belongsTo = [
      'purchaseable_item' => [\Omsb\Procurement\Models\PurchaseableItem::class]
  ];
  ```
- **Warehouse-level uniqueness**: Each warehouse can reference a `PurchaseableItem` only once (enforced by unique index on `warehouse_id + purchaseable_item_id`)

### Document Fulfillment Routing
When a Purchase Order is fulfilled, check `PurchaseableItem->is_inventory_item`:
- `true` → Create Goods Receipt Note (Inventory plugin)
  - Generates `InventoryLedger` entries at receiving warehouse
  - Updates `WarehouseItem` QoH
- `false` → Create Delivery Order (Procurement plugin)
  - Records expense/asset journal entry using `gl_account_id`
  - No warehouse impact

### Morphable Relationships
Many models use `morphTo` for flexible associations:
```php
public $morphTo = [
    'feedable' => []  // Can attach to any model
];
```
Common uses: Feeder (activity tracking), InventoryLedger (document references), Workflow (status transitions)

### Backend User Integration
Extensive use of `BackendAuth::getUser()` for user context in business logic:
```php
$currentUser = BackendAuth::getUser();
$canApprove = $currentUser->canApproveDocument($documentType, $amount);
```

### Validation Traits
Models consistently use `\October\Rain\Database\Traits\Validation`:
```php
use \October\Rain\Database\Traits\Validation;

public $rules = [
    'is_inventory_item' => 'required|boolean',
    'item_type' => 'required|in:consumable,equipment,spare_part,asset'
];
```

## Critical Business Rules

1. **Purchaseable Item Inventory Flag Immutability:**
   - `is_inventory_item` cannot be changed if combined QoH > 0 across all warehouses
   - Validation must check `WarehouseItem::where('purchaseable_item_id', $id)->sum('quantity_on_hand')`
   - Prevents reclassification that would break ledger integrity

2. **Warehouse Receiving Logic:**
   - Sites with >1 active warehouse must have designated receiving warehouse
   - If not set, system uses first warehouse from query (non-deterministic - should warn admin)
   - PO line items auto-route to site's receiving warehouse on fulfillment

3. **Inventory Ledger Double-Entry:**
   - Every QoH increase must have matching decrease elsewhere
   - In-transit virtual warehouse acts as buffer for goods in transit
   - Ledger entries are immutable once created (audit trail)

4. **Approval Hierarchy Enforcement:**
   - Approver must be in Organization hierarchy above creator
   - Approval ceiling (amount limits) enforced per staff/document type
   - Workflow transitions validated against staff permissions before execution

5. **Document Status Constraints:**
   - Only Draft documents can be edited/deleted
   - Status transitions must follow Workflow definitions
   - Rejected documents require reason comments
   - Overdue approvals auto-revert to Draft after configured days

## References
- See `README.md` in root, themes, and plugins for more details
- Plugin architecture overview in `plugins/omsb/README.md` with detailed documentation status
- For OctoberCMS plugin development: https://docs.octobercms.com/4.x/extend/system/plugins.html
- For OctoberCMS conventions: https://octobercms.com/help/guidelines/developer
- For OctoberCMS quality guidelines: https://octobercms.com/help/guidelines/quality
- For OctoberCMS API reference: https://docs.octobercms.com/4.x/element/form-fields.html
- For Laravel Mix/Tailwind, see theme `README.md` and `tailwind.config.js`

---

**When in doubt, follow the structure and conventions of existing modules/plugins.**