<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create/update/delete of an audited model (blueprint.pdf §21).
 * Only Eloquent model events reach it: bulk query updates and pivot syncs
 * must go through a model or call AuditLog::record() themselves.
 */
class AuditObserver
{
    private const IGNORED_ATTRIBUTES = ['created_at', 'updated_at', 'remember_token'];

    private const MASKED_ATTRIBUTES = ['password'];

    public function created(Model $model): void
    {
        AuditLog::record('Membuat', $model, [], $this->auditable($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->auditable($model->getChanges());

        if ($changes === []) {
            return;
        }

        AuditLog::record(
            array_key_exists('status', $changes) ? 'Mengubah status' : 'Mengubah',
            $model,
            $this->auditable(array_intersect_key($model->getRawOriginal(), $changes)),
            $changes,
        );
    }

    public function deleted(Model $model): void
    {
        AuditLog::record('Menghapus', $model, $this->auditable($model->getAttributes()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditable(array $attributes): array
    {
        $attributes = array_diff_key($attributes, array_flip(self::IGNORED_ATTRIBUTES));

        foreach (array_intersect(self::MASKED_ATTRIBUTES, array_keys($attributes)) as $key) {
            $attributes[$key] = '[disembunyikan]';
        }

        return $attributes;
    }
}
