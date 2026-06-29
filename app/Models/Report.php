<?php
/**
 * FILE: backend/app/Models/Report.php
 * STATUS: DIUBAH (tambah kolom category)
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $table = 'reports';

    protected $fillable = ['reporter_id', 'reportable_type', 'reportable_id', 'category', 'reason', 'status', 'handled_by', 'handled_note'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
