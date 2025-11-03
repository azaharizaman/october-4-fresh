# OC-Polaris

**Enterprise Resource Planning System for One Medicare Sdn Bhd**

OC-Polaris is a comprehensive business management application built on OctoberCMS 4.x and Laravel 12, designed specifically for One Medicare Sdn Bhd's operational needs. The system provides integrated modules for procurement, inventory management, organizational structure, workflow automation, and financial tracking.

## System Overview

This is a modular OctoberCMS application with custom plugins focused on backend business operations rather than content management. The application leverages OctoberCMS's powerful backend administration panel to build complex business workflows.

### Technology Stack

- **Framework**: OctoberCMS 4.x
- **Foundation**: Laravel 12
- **PHP Version**: 8.2+
- **Database**: MySQL/MariaDB
- **Frontend**: TailwindCSS (themes)
- **Testing**: PHPUnit

## Core Features

### 📦 **Procurement Management**
- Purchase Request workflow
- Purchase Order processing
- Vendor management with shareholder tracking
- Goods Receipt Notes (GRN)
- Delivery Orders for non-inventory items
- Multi-level approval workflows
- Budget tracking and reporting

### 🏢 **Organization Management**
- Multi-site hierarchical structure
- Staff management with approval hierarchies
- Site-based data access control
- GL account definitions per site
- Warehouse assignment to sites

### 📊 **Inventory Management**
- Multi-warehouse stock control
- Double-entry inventory ledger system
- Material Received Notes (MRN)
- Material Request Issuance (MRI)
- Stock adjustments and transfers
- Physical count management
- Multi-UOM support with conversions
- Month-end closing and valuation (FIFO/LIFO/Average Cost)

### 🔄 **Workflow Engine**
- Configurable document workflows
- Status transition rules
- Approval hierarchy enforcement
- Amount-based approval limits
- Rejection handling with comments

### 📋 **Document Registrar**
- Custom document numbering patterns
- Auto-increment with yearly/continuous reset
- Site and document type prefixes
- Audit trail for issued numbers

### 📱 **Activity Tracking (Feeder)**
- Centralized activity logging
- User action tracking across all modules
- Audit trail with morphTo relationships

## Project Structure

```
├── plugins/omsb/           # Custom business plugins
│   ├── procurement/        # Procurement operations
│   ├── inventory/          # Warehouse & stock management
│   ├── organization/       # Sites, staff, GL accounts
│   ├── workflow/           # Approval workflows
│   ├── registrar/          # Document numbering
│   └── feeder/             # Activity tracking
├── modules/                # OctoberCMS core modules
├── themes/                 # Frontend themes (optional)
├── config/                 # Application configuration
└── docs/                   # Additional documentation
```

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL/MariaDB database
- Web server (Apache/Nginx)

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd oc-polaris
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

5. **Run database migrations**
   ```bash
   php artisan october:migrate
   ```

6. **Create admin user**
   ```bash
   php artisan october:install
   ```

## Development

### Testing

Run the test suite:
```bash
composer test
# or
phpunit
```

### Code Linting

Check code standards:
```bash
composer lint
# or
phpcs
```

### Plugin Development

All custom business logic resides in `plugins/omsb/*`. Each plugin follows OctoberCMS conventions:

- **Controllers**: Request/response handling (lean)
- **Services**: Business logic implementation
- **Models**: Eloquent models with validation
- **Traits**: Reusable model behaviors
- **Tests**: PHPUnit tests extending PluginTestCase

Refer to `.github/copilot-instructions.md` for detailed architectural guidelines.

## Documentation

- **[Architecture Changelog](ARCHITECTURE_CHANGELOG.md)** - Major architectural changes and business logic updates
- **[Financial Period Implementation](FINANCIAL_PERIOD_IMPLEMENTATION.md)** - Period management system details
- **[Period Management Summary](PERIOD_MANAGEMENT_SUMMARY.md)** - Summary of period management features
- **Plugin READMEs** - Each plugin contains comprehensive documentation in its directory

## Key Architectural Patterns

- **Service Layer**: Business logic extracted to service classes
- **Event-Driven**: Listeners and events for decoupled components
- **Morphable Relationships**: Flexible associations using morphTo
- **Hierarchical Approvals**: Multi-level staff approval workflows
- **Double-Entry Ledger**: Inventory movements tracked with paired entries
- **Multi-UOM Support**: Conversion tracking between units of measure

## Coding Standards

This project follows:
- [PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)
- [PSR-2 Coding Style](https://www.php-fig.org/psr/psr-2/)
- [PSR-1 Basic Standards](https://www.php-fig.org/psr/psr-1/)
- OctoberCMS [Development Guidelines](https://octobercms.com/help/guidelines/developer)
- PHP 8.2+ modern standards with type declarations

## Contributing

When contributing to this project:

1. Follow the existing code structure and conventions
2. Write tests for new features
3. Update relevant documentation (plugin READMEs, architecture changelog)
4. Keep controllers lean - delegate to services
5. Document database migrations with business rationale
6. Follow the naming conventions for foreign keys and constraints

## License

Proprietary software for One Medicare Sdn Bhd. All rights reserved.

Built on top of OctoberCMS - see [LICENSE.md](LICENSE.md) for October CMS license details.

## Support

For development support and architectural guidance, refer to:
- [OctoberCMS Documentation](https://docs.octobercms.com/4.x/)
- [Laravel Documentation](https://laravel.com/docs)
- Project-specific guidelines in `.github/copilot-instructions.md`
