# Automation & CI/CD - Feeder Plugin

## Overview

This document outlines automation opportunities, CI/CD pipeline configuration, and DevOps best practices for the Feeder plugin.

## Current State

**Status:** ❌ **NO AUTOMATION**

- No CI/CD pipelines
- No automated testing
- No code quality checks
- No automated deployments
- All testing is manual

## Proposed Automation Strategy

### 1. Continuous Integration (CI)

Automatically run tests, linting, and code quality checks on every commit and pull request.

### 2. Continuous Deployment (CD)

Automatically deploy to staging/production after successful CI pipeline.

### 3. Scheduled Tasks

Automate recurring maintenance tasks (archival, cleanup, reports).

## GitHub Actions Workflows

### Workflow 1: Test & Quality Check

**.github/workflows/test.yml:**

```yaml
name: Test & Quality

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
    name: Test Suite
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: october_test
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s
    
    strategy:
      matrix:
        php: [8.2, 8.3]
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, gd, mysql, curl, json
          coverage: xdebug
      
      - name: Get composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT
      
      - name: Cache dependencies
        uses: actions/cache@v3
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-interaction
      
      - name: Run PHPUnit Tests
        run: vendor/bin/phpunit --coverage-clover coverage.xml
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: october_test
          DB_USERNAME: root
          DB_PASSWORD: root
      
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
          flags: unittests
          name: codecov-umbrella
      
      - name: Generate coverage badge
        uses: codecov/codecov-action@v3
  
  lint:
    name: Code Linting
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mbstring
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run PHP CS Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
      
      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --level=5
  
  security:
    name: Security Scan
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Run security checker
        uses: symfonycorp/security-checker-action@v4
```

### Workflow 2: Deploy to Staging

**.github/workflows/deploy-staging.yml:**

```yaml
name: Deploy to Staging

on:
  push:
    branches: [develop]

jobs:
  deploy:
    name: Deploy to Staging Environment
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy via SSH
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: ${{ secrets.STAGING_USER }}
          key: ${{ secrets.STAGING_SSH_KEY }}
          script: |
            cd /var/www/october
            git pull origin develop
            composer install --no-dev --optimize-autoloader
            php artisan october:migrate
            php artisan cache:clear
            php artisan config:cache
            php artisan route:cache
      
      - name: Notify Slack
        uses: 8398a7/action-slack@v3
        with:
          status: ${{ job.status }}
          text: 'Deployed to staging environment'
          webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

### Workflow 3: Deploy to Production

**.github/workflows/deploy-production.yml:**

```yaml
name: Deploy to Production

on:
  release:
    types: [published]

jobs:
  deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    environment: production
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Run Tests
        run: |
          composer install
          vendor/bin/phpunit
      
      - name: Deploy to Production
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /var/www/october
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan october:migrate --force
            php artisan cache:clear
            php artisan config:cache
            php artisan route:cache
            
      - name: Create Sentry Release
        uses: getsentry/action-release@v1
        env:
          SENTRY_AUTH_TOKEN: ${{ secrets.SENTRY_AUTH_TOKEN }}
          SENTRY_ORG: ${{ secrets.SENTRY_ORG }}
          SENTRY_PROJECT: ${{ secrets.SENTRY_PROJECT }}
        with:
          environment: production
      
      - name: Notify Team
        uses: 8398a7/action-slack@v3
        with:
          status: ${{ job.status }}
          text: '🚀 Deployed to production!'
          webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

### Workflow 4: Code Quality

**.github/workflows/quality.yml:**

```yaml
name: Code Quality

on: [push, pull_request]

jobs:
  phpmd:
    name: PHPMD
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run PHPMD
        run: vendor/bin/phpmd models,classes text cleancode,codesize,controversial,design,naming,unusedcode
  
  phpcs:
    name: PHP_CodeSniffer
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run PHPCS
        run: vendor/bin/phpcs --standard=PSR12 models/ classes/
  
  sonarcloud:
    name: SonarCloud Analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
        with:
          fetch-depth: 0
      - name: SonarCloud Scan
        uses: SonarSource/sonarcloud-github-action@master
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
```

## Automated Tasks

### Task 1: Feed Archival (Daily)

Archive feeds older than 1 year to reduce database size.

**Cron Schedule:** Daily at 2:00 AM

```bash
# In server crontab
0 2 * * * cd /var/www/october && php artisan feed:archive --days=365
```

**Laravel Scheduler:**

```php
// console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('feed:archive --days=365')
        ->daily()
        ->at('02:00');
}
```

### Task 2: Cleanup Orphaned Feeds (Weekly)

Remove feeds referencing deleted models.

**Cron Schedule:** Weekly on Sunday at 3:00 AM

```bash
0 3 * * 0 cd /var/www/october && php artisan feed:cleanup --orphaned
```

**Laravel Scheduler:**

```php
$schedule->command('feed:cleanup --orphaned')
    ->weekly()
    ->sundays()
    ->at('03:00');
```

### Task 3: Activity Reports (Monthly)

Generate and email monthly activity reports.

**Cron Schedule:** First day of month at 9:00 AM

```bash
0 9 1 * * cd /var/www/october && php artisan feed:report --email=admin@example.com
```

**Laravel Scheduler:**

```php
$schedule->command('feed:report --email=admin@example.com')
    ->monthlyOn(1, '09:00');
```

## Database Maintenance

### Automated Backups

```yaml
# .github/workflows/backup.yml
name: Database Backup

on:
  schedule:
    - cron: '0 0 * * *'  # Daily at midnight

jobs:
  backup:
    runs-on: ubuntu-latest
    steps:
      - name: Backup Database
        run: |
          mysqldump -h ${{ secrets.DB_HOST }} \
                    -u ${{ secrets.DB_USER }} \
                    -p${{ secrets.DB_PASSWORD }} \
                    october_db \
                    --tables omsb_feeder_feeds \
                    | gzip > feeds_backup_$(date +%Y%m%d).sql.gz
      
      - name: Upload to S3
        uses: aws-actions/configure-aws-credentials@v1
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: us-east-1
      
      - name: Copy to S3
        run: aws s3 cp feeds_backup_*.sql.gz s3://backups/feeder/
```

## Performance Monitoring

### New Relic Integration

```php
// Plugin.php
public function boot()
{
    if (extension_loaded('newrelic')) {
        // Track feed creation performance
        Feed::created(function ($feed) {
            newrelic_record_custom_event('FeedCreated', [
                'action_type' => $feed->action_type,
                'feedable_type' => $feed->feedable_type,
            ]);
        });
    }
}
```

### Sentry Error Tracking

```php
// Plugin.php
public function boot()
{
    if (config('services.sentry.enabled')) {
        Feed::creating(function ($feed) {
            try {
                // Validate before create
                $this->validate();
            } catch (\Exception $e) {
                app('sentry')->captureException($e);
                throw $e;
            }
        });
    }
}
```

## Docker Support

### Dockerfile

```dockerfile
FROM octobercms/october:latest

# Install additional PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy plugin files
COPY --chown=www-data:www-data . /var/www/html/plugins/omsb/feeder

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

WORKDIR /var/www/html
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  october:
    build: .
    ports:
      - "80:80"
    environment:
      - DB_HOST=mysql
      - DB_DATABASE=october
      - DB_USERNAME=october
      - DB_PASSWORD=secret
    depends_on:
      - mysql
    volumes:
      - ./plugins/omsb/feeder:/var/www/html/plugins/omsb/feeder
  
  mysql:
    image: mysql:8.0
    environment:
      - MYSQL_DATABASE=october
      - MYSQL_USER=october
      - MYSQL_PASSWORD=secret
      - MYSQL_ROOT_PASSWORD=root
    volumes:
      - mysql_data:/var/lib/mysql
  
  redis:
    image: redis:alpine

volumes:
  mysql_data:
```

## Pre-commit Hooks

### Setup Git Hooks

```bash
# .git/hooks/pre-commit
#!/bin/bash

echo "Running pre-commit checks..."

# Run PHP linter
find plugins/omsb/feeder -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
if [ $? -ne 1 ]; then
    echo "PHP syntax errors found. Commit aborted."
    exit 1
fi

# Run PHPUnit tests
vendor/bin/phpunit plugins/omsb/feeder/tests
if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi

# Run PHP CS Fixer
vendor/bin/php-cs-fixer fix plugins/omsb/feeder --dry-run
if [ $? -ne 0 ]; then
    echo "Code style issues found. Run php-cs-fixer fix to auto-fix."
    exit 1
fi

echo "All pre-commit checks passed!"
exit 0
```

## Deployment Checklist

### Pre-Deployment

- [ ] All tests passing
- [ ] Code review approved
- [ ] Database migrations reviewed
- [ ] Configuration changes documented
- [ ] Backup created
- [ ] Rollback plan prepared

### During Deployment

- [ ] Enable maintenance mode
- [ ] Pull latest code
- [ ] Run migrations
- [ ] Clear caches
- [ ] Run post-deployment tests
- [ ] Disable maintenance mode

### Post-Deployment

- [ ] Verify key functionality
- [ ] Monitor error rates
- [ ] Check performance metrics
- [ ] Notify team
- [ ] Update release notes

## Monitoring & Alerts

### Health Check Endpoint

```php
// routes.php
Route::get('/health/feeder', function() {
    try {
        $feedCount = Feed::count();
        $latestFeed = Feed::latest()->first();
        
        return response()->json([
            'status' => 'healthy',
            'total_feeds' => $feedCount,
            'latest_feed_age' => $latestFeed->created_at->diffForHumans(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
        ], 500);
    }
});
```

### Alert Configuration

```yaml
# prometheus/alerts.yml
groups:
  - name: feeder
    rules:
      - alert: HighFeedCreationRate
        expr: rate(feed_created_total[5m]) > 100
        for: 10m
        annotations:
          summary: "High feed creation rate detected"
      
      - alert: FeedDatabaseSize
        expr: mysql_table_rows{table="omsb_feeder_feeds"} > 10000000
        annotations:
          summary: "Feed table exceeds 10M rows"
      
      - alert: FeedCreationFailure
        expr: rate(feed_creation_errors_total[5m]) > 10
        annotations:
          summary: "High feed creation failure rate"
```

## Documentation Automation

### Auto-generate API Docs

```bash
# Generate API documentation from code
vendor/bin/phpdoc -d plugins/omsb/feeder -t docs/api
```

### Auto-update CHANGELOG

```yaml
# .github/workflows/changelog.yml
name: Update Changelog

on:
  release:
    types: [published]

jobs:
  changelog:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Generate Changelog
        run: |
          npx standard-version
          git push --follow-tags origin main
```

## CI/CD Best Practices

### 1. Fast Feedback

- Run linting and fast tests first
- Parallelize test execution
- Cache dependencies
- Use build matrices for multiple PHP versions

### 2. Security

- Scan dependencies for vulnerabilities
- Use secrets management
- Restrict deployment access
- Enable 2FA for deployments

### 3. Reliability

- Run tests in isolated environments
- Use database migrations
- Implement health checks
- Have rollback procedures

### 4. Observability

- Log all deployments
- Track deployment frequency
- Monitor error rates post-deployment
- Set up alerts for anomalies

## Effort Estimate

| Task | Effort | Priority |
|------|--------|----------|
| Setup GitHub Actions CI | 4-6 hours | High |
| Configure deployment pipelines | 6-8 hours | High |
| Implement automated tests | 3-5 days | Critical |
| Setup monitoring & alerts | 4-6 hours | Medium |
| Configure scheduled tasks | 2-3 hours | Medium |
| Docker containerization | 3-4 hours | Low |
| Documentation automation | 2-3 hours | Low |
| **Total** | **4-6 days** | **-** |

## Success Metrics

| Metric | Target |
|--------|--------|
| Deployment frequency | 2-3x per week |
| Lead time for changes | <1 day |
| Mean time to recovery | <30 minutes |
| Change failure rate | <5% |
| Test coverage | >80% |
| Build time | <10 minutes |

## Conclusion

Implementing this automation strategy will:
- Improve code quality
- Reduce deployment risks
- Enable faster iterations
- Increase team productivity
- Provide better visibility

**Recommendation:** Start with test automation and basic CI, then gradually add deployment automation and monitoring.

---

**Previous:** [← Improvements](09_improvements.md) | **End of Documentation**
