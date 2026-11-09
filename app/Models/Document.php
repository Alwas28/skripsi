<?php

namespace App\Models;

use App\Enums\DocumentStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['thesis_id', 'stage', 'name'])]
class Document extends Model
{
    use HasFactory;

    /**
     * The document the PA and Kaprodi review with the title.
     */
    public const TITLE_PROPOSAL_NAME = 'Proposal Awal';

    protected function casts(): array
    {
        return [
            'stage' => DocumentStage::class,
        ];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Store the file as the new active version. Earlier versions and their
     * files are kept as history.
     */
    public function addVersion(UploadedFile $file, User $uploader): DocumentVersion
    {
        $path = $file->store("documents/{$this->thesis_id}", 'local');

        return $this->recordVersion($path, $file->getClientOriginalName(), $file->getSize(), $uploader);
    }

    /**
     * Store system-generated content (e.g. Berita Acara) as the new version.
     */
    public function addGeneratedVersion(string $contents, string $fileName, User $uploader): DocumentVersion
    {
        $path = "documents/{$this->thesis_id}/".Str::uuid().'-'.$fileName;
        Storage::disk('local')->put($path, $contents);

        return $this->recordVersion($path, $fileName, strlen($contents), $uploader);
    }

    private function recordVersion(string $path, string $originalName, int $size, User $uploader): DocumentVersion
    {
        return DB::transaction(function () use ($path, $originalName, $size, $uploader) {
            $this->versions()->where('is_active', true)->get()->each->update(['is_active' => false]);

            $version = $this->versions()->create([
                'version_number' => (int) $this->versions()->max('version_number') + 1,
                'file_path' => $path,
                'original_name' => $originalName,
                'size' => $size,
                'is_active' => true,
                'uploaded_by' => $uploader->id,
            ]);

            if ($this->stage === DocumentStage::TitleSubmission && $this->name === self::TITLE_PROPOSAL_NAME) {
                $this->thesis->title?->update(['proposal_path' => $path]);
            }

            return $version;
        });
    }
}
