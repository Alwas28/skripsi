<?php

namespace App\Models;

use App\Enums\ExamKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class ExamType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'code' => ExamKind::class,
        ];
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
