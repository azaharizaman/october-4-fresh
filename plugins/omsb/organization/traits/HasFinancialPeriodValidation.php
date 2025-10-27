<?php namespace Omsb\Organization\Traits;

use Carbon\Carbon;
use ValidationException;
use Omsb\Organization\Models\FinancialPeriod;

/**
 * Financial Period Validation Trait
 * 
 * Enforces financial period posting rules on models with transaction dates.
 * Use this trait on any model that:
 * - Has financial impact (GL posting, budget transactions, invoices, etc.)
 * - Contains a transaction_date or document_date field
 * - Needs to respect accounting period close rules
 * 
 * Usage:
 * ```php
 * class BudgetTransaction extends Model
 * {
 *     use \Omsb\Organization\Traits\HasFinancialPeriodValidation;
 *     
 *     protected $financialDateField = 'transaction_date'; // default
 *     protected $requireOpenPeriod = true; // default
 * }
 * ```
 * 
 * @property string $financialDateField Field name containing transaction date
 * @property bool $requireOpenPeriod Whether to require open period for posting
 */
trait HasFinancialPeriodValidation
{
    /**
     * Boot the trait
     */
    public static function bootHasFinancialPeriodValidation()
    {
        // Validate period before saving
        static::saving(function ($model) {
            $model->validateFinancialPeriod();
        });

        // Also validate before updating
        static::updating(function ($model) {
            $model->validateFinancialPeriod();
        });
    }

    /**
     * Get the date field to validate against
     */
    protected function getFinancialDateField(): string
    {
        return $this->financialDateField ?? 'transaction_date';
    }

    /**
     * Check if open period is required
     */
    protected function requiresOpenPeriod(): bool
    {
        return $this->requireOpenPeriod ?? true;
    }

    /**
     * Validate transaction date against financial period
     * 
     * @throws ValidationException
     */
    public function validateFinancialPeriod(): void
    {
        $dateField = $this->getFinancialDateField();
        
        // Skip if no date field or date not set
        if (!isset($this->{$dateField})) {
            return;
        }

        $transactionDate = $this->{$dateField};
        if ($transactionDate instanceof \DateTime) {
            $transactionDate = Carbon::instance($transactionDate);
        } elseif (is_string($transactionDate)) {
            $transactionDate = Carbon::parse($transactionDate);
        }

        // Find the period that contains this date
        $period = FinancialPeriod::where('start_date', '<=', $transactionDate)
            ->where('end_date', '>=', $transactionDate)
            ->first();

        if (!$period) {
            throw new ValidationException([
                $dateField => 'No financial period exists for date: ' . $transactionDate->format('Y-m-d')
            ]);
        }

        // Check if period allows posting
        if ($this->requiresOpenPeriod() && !$period->allowsPosting()) {
            throw new ValidationException([
                $dateField => sprintf(
                    'Financial period %s is %s. Cannot post transactions.',
                    $period->period_name,
                    $period->status
                )
            ]);
        }

        // Check backdating rules
        if ($transactionDate->lt($period->start_date) && !$period->allowsBackdating()) {
            throw new ValidationException([
                $dateField => 'Backdated transactions not allowed for this period'
            ]);
        }

        // Store period reference if model has financial_period_id field
        if ($this->hasAttribute('financial_period_id')) {
            $this->financial_period_id = $period->id;
        }
    }

    /**
     * Get the financial period for this transaction
     */
    public function getFinancialPeriodAttribute(): ?FinancialPeriod
    {
        $dateField = $this->getFinancialDateField();
        
        if (!isset($this->{$dateField})) {
            return null;
        }

        $transactionDate = $this->{$dateField};
        if ($transactionDate instanceof \DateTime) {
            $transactionDate = Carbon::instance($transactionDate);
        } elseif (is_string($transactionDate)) {
            $transactionDate = Carbon::parse($transactionDate);
        }

        return FinancialPeriod::where('start_date', '<=', $transactionDate)
            ->where('end_date', '>=', $transactionDate)
            ->first();
    }

    /**
     * Check if transaction can be edited based on period status
     */
    public function canEditBasedOnPeriod(): bool
    {
        $period = $this->financial_period;
        
        if (!$period) {
            return true; // No period constraint
        }

        return in_array($period->status, ['draft', 'open']);
    }

    /**
     * Check if transaction can be deleted based on period status
     */
    public function canDeleteBasedOnPeriod(): bool
    {
        $period = $this->financial_period;
        
        if (!$period) {
            return true; // No period constraint
        }

        return in_array($period->status, ['draft', 'open']);
    }

    /**
     * Relationship to financial period (if FK exists)
     */
    public function financial_period()
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }
}
