<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    protected $fillable = [
        'freelancer_id', 'discipline_id', 'assigned_by_id',
        'status', 'grade_value', 'grade_scale', 'comment',
    ];

    const STATUS_DEBT   = 'DEBT';
    const STATUS_CLOSED = 'CLOSED';

    const GRADE_SCALES = [
        'EXAM'      => 'Экзамен (5, 4, 3, 2, Незачёт)',
        'PASS_FAIL' => 'Зачёт / Незачёт',
    ];

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(DebtStatusLog::class)->orderByDesc('changed_at');
    }

    public function retakes()
    {
        return $this->belongsToMany(Retake::class, 'retake_debts');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_DEBT;
    }

    public function statusLabel(): string
    {
        return $this->status === self::STATUS_DEBT ? 'Задолженность' : 'Закрыта';
    }

    public function gradeLabel(): string
    {
        if ($this->grade_value === null) return '—';

        if ($this->grade_scale === 'PASS_FAIL') {
            return $this->grade_value == 1 ? 'Зачёт' : 'Незачёт';
        }

        if ($this->grade_scale === 'EXAM') {
            return match((int)$this->grade_value) {
                5 => '5 — Отлично',
                4 => '4 — Хорошо',
                3 => '3 — Удовлетворительно',
                2 => '2 — Неудовлетворительно',
                0 => 'Незачёт',
                default => (string)$this->grade_value,
            };
        }

        return (string)$this->grade_value;
    }

    public function close(User $jobgiver, ?float $grade = null, ?string $scale = null): void
    {
        $old = $this->status;

        $this->update([
            'status'      => self::STATUS_CLOSED,
            'grade_value' => $grade,
            'grade_scale' => $scale,
        ]);

        DebtStatusLog::create([
            'debt_id'       => $this->id,
            'old_status'    => $old,
            'new_status'    => self::STATUS_CLOSED,
            'changed_by_id' => $jobgiver->id,
        ]);

        Notification::send(
            $this->freelancer_id,
            Notification::TYPE_DEBT_CLOSED,
            'Задолженность закрыта',
            "Ваша задолженность по дисциплине «{$this->discipline->name}» была закрыта.",
            ['related_debt_id' => $this->id]
        );
    }
}
