# Race Condition Fix in DocumentNumberPattern

## Problem Statement

The `generateNumber()` method in the `DocumentNumberPattern` model had a critical race condition that could result in duplicate document numbers when multiple concurrent requests attempted to generate numbers simultaneously.

### Race Condition Scenario

**Before the fix:**

```
Time    | Request 1                      | Request 2
--------|--------------------------------|--------------------------------
T1      | Read next_number = 5           |
T2      |                                | Read next_number = 5 (same!)
T3      | Generate "DOC-00005"           |
T4      |                                | Generate "DOC-00005" (duplicate!)
T5      | Set next_number = 6            |
T6      | Save to database               |
T7      |                                | Set next_number = 6
T8      |                                | Save to database (overwrites!)
```

**Result:** Both requests generate the same document number "DOC-00005", violating uniqueness constraints.

## Solution

The fix implements **database-level pessimistic locking** using Laravel's `lockForUpdate()` method within a database transaction. This ensures that only one request can read and modify the `next_number` field at a time.

### Implementation Details

#### 1. Database Transaction Wrapper

All operations are wrapped in a database transaction to ensure atomicity:

```php
return \DB::transaction(function () use ($variables) {
    // All operations here are atomic
});
```

#### 2. Pessimistic Row-Level Locking

Before reading the `next_number`, we acquire an exclusive lock on the pattern row:

```php
$pattern = self::where('id', $this->id)->lockForUpdate()->first();
```

This executes a SQL query like:
```sql
SELECT * FROM omsb_registrar_document_number_patterns 
WHERE id = ? 
FOR UPDATE
```

The `FOR UPDATE` clause tells the database to:
- Acquire an exclusive lock on this row
- Block other transactions trying to lock the same row
- Hold the lock until the transaction commits or rolls back

#### 3. Atomic Read-Modify-Write

Within the locked transaction:
1. Read the current `next_number`
2. Check if reset is needed (yearly/monthly)
3. Generate the document number
4. Increment `next_number`
5. Save the changes
6. Return the generated number

All these steps happen atomically - either all succeed or all fail (rollback).

### After the Fix

```
Time    | Request 1                      | Request 2
--------|--------------------------------|--------------------------------
T1      | Begin transaction              |
T2      | Acquire lock on pattern row    |
T3      | Read next_number = 5           |
T4      |                                | Begin transaction
T5      |                                | Try to lock pattern row (WAIT)
T6      | Generate "DOC-00005"           |
T7      | Set next_number = 6            |
T8      | Save to database               |
T9      | Commit & release lock          |
T10     |                                | Lock acquired!
T11     |                                | Read next_number = 6
T12     |                                | Generate "DOC-00006"
T13     |                                | Set next_number = 7
T14     |                                | Save to database
T15     |                                | Commit & release lock
```

**Result:** Each request gets a unique, sequential document number. Request 2 waits for Request 1 to complete before proceeding.

## Code Changes

### Modified: `DocumentNumberPattern::generateNumber()`

```php
public function generateNumber(array $variables = []): string
{
    return \DB::transaction(function () use ($variables) {
        // Acquire pessimistic lock on this row to prevent race conditions
        $pattern = self::where('id', $this->id)->lockForUpdate()->first();
        
        // Check if reset is needed (pass the locked instance)
        $pattern->checkAndResetIfNeeded();
        
        // Get the next number from the locked instance
        $number = $pattern->next_number;
        
        // ... rest of number generation logic ...
        
        // Atomically increment next_number
        $pattern->next_number = $number + 1;
        $pattern->save();
        
        // Update current instance with new values
        $this->next_number = $pattern->next_number;
        $this->current_year = $pattern->current_year;
        $this->current_month = $pattern->current_month;
        
        return $documentNumber;
    });
}
```

### Modified: `DocumentNumberPattern::checkAndResetIfNeeded()`

Removed intermediate `save()` calls since the method now operates within a transaction where the parent method handles saving:

```php
protected function checkAndResetIfNeeded(): void
{
    $now = Carbon::now();
    
    if ($this->reset_interval === 'yearly') {
        $currentYear = $now->year;
        if ($this->current_year !== $currentYear) {
            $this->next_number = 1;
            $this->current_year = $currentYear;
            // No save() - handled by parent transaction
        }
    }
    // ... similar for monthly reset ...
}
```

## Benefits

1. **Guaranteed Uniqueness**: No duplicate document numbers, even under heavy concurrent load
2. **Sequential Integrity**: Document numbers remain strictly sequential
3. **Multi-Server Safe**: Works correctly across multiple application servers/processes
4. **Database-Level Protection**: More reliable than application-level locks (which don't work across processes)
5. **Automatic Rollback**: If any error occurs during number generation, the transaction rolls back, ensuring consistency
6. **No External Dependencies**: Uses built-in database locking mechanisms

## Performance Considerations

### Lock Wait Time

If many concurrent requests try to generate numbers:
- Each request will wait for the previous one to complete
- Wait time is typically very short (milliseconds) since number generation is fast
- Database will queue waiting transactions

### Deadlock Prevention

The implementation avoids deadlocks because:
- Each transaction only locks a single row
- Locks are acquired in a consistent order (always by pattern ID)
- Transactions are short-lived

### Scalability

For high-volume scenarios:
- Consider using separate patterns per site/department to distribute load
- Monitor database lock wait times
- Use database connection pooling

## Testing

Comprehensive tests have been added in `tests/models/DocumentNumberPatternTest.php`:

1. **testBasicNumberGeneration**: Verifies basic functionality
2. **testConcurrentNumberGeneration**: Generates 20 numbers rapidly and verifies uniqueness
3. **testYearlyReset**: Tests yearly reset logic
4. **testMonthlyReset**: Tests monthly reset logic
5. **testPrefixAndSuffix**: Tests custom prefix/suffix
6. **testCustomVariables**: Tests variable substitution
7. **testLockForUpdatePreventsRaceCondition**: Simulates concurrent access with fresh instances

### Manual Testing

To test under real concurrent load:

```bash
# Using Apache Bench
ab -n 100 -c 10 http://yourapp/api/generate-number?pattern_id=1

# Using hey (modern alternative)
hey -n 100 -c 10 http://yourapp/api/generate-number?pattern_id=1
```

Verify:
- All 100 generated numbers are unique
- Numbers are sequential (1, 2, 3, ... 100)
- `next_number` in database equals 101

## Database Compatibility

The `lockForUpdate()` method is supported by:
- ✅ MySQL/MariaDB (uses `SELECT ... FOR UPDATE`)
- ✅ PostgreSQL (uses `SELECT ... FOR UPDATE`)
- ✅ SQL Server (uses `SELECT ... WITH (UPDLOCK, ROWLOCK)`)
- ❌ SQLite (no row-level locking, uses table-level locks - may have performance impact)

For SQLite (typically used only in development/testing), the fix still works but with table-level locking instead of row-level locking.

## Backwards Compatibility

The changes are **fully backwards compatible**:
- Method signature remains the same
- Return type unchanged (still returns `string`)
- All existing code calling `generateNumber()` works without modification
- The `IssuedDocumentNumber::issueNumber()` method works as before

## Migration Notes

No database migrations required. The fix only modifies application code.

## Related Documentation

- [Laravel Database Transactions](https://laravel.com/docs/11.x/database#database-transactions)
- [Laravel Pessimistic Locking](https://laravel.com/docs/11.x/queries#pessimistic-locking)
- [October CMS Database](https://docs.octobercms.com/4.x/extend/database/basics.html)

## Summary

This fix eliminates the race condition in document number generation by using database-level pessimistic locking within transactions. It ensures that document numbers are always unique and sequential, even under heavy concurrent load, without requiring any external dependencies or infrastructure changes.
