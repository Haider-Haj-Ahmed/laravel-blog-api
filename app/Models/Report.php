<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ACTION_TAKEN = 'action_taken';

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'reviewed_at',
        'reviewed_by',
        'admin_notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (Report $report) {
            if ($report->isDirty('status') && $report->status !== self::STATUS_PENDING) {
                if ($report->reviewed_by === null) {
                    throw new \LogicException('reviewed_by must be set explicitly when changing a report to a non-pending status.');
                }

                if ($report->reviewed_at === null) {
                    $report->reviewed_at = now();
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
