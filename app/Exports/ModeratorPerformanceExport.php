&lt;?php

namespace App\Exports;

use App\Models\ModeratorStatistic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ModeratorPerformanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
protected $period;
protected $dateRange;
protected $moderatorId;
protected $profileId;
protected $performanceLevel;

public function __construct($period, $dateRange, $moderatorId, $profileId, $performanceLevel)
{
$this->period = $period;
$this->dateRange = $dateRange;
$this->moderatorId = $moderatorId;
$this->profileId = $profileId;
$this->performanceLevel = $performanceLevel;
}

public function collection()
{
$startDate = $this->getStartDate();
$endDate = $this->dateRange['end'] ?? now();

$query = ModeratorStatistic::query()
->join('users', 'moderator_statistics.user_id', '=', 'users.id')
->where('users.type', 'moderator')
->where('moderator_statistics.stats_date', '>=', $startDate)
->where('moderator_statistics.stats_date', '<=', $endDate);

    if ($this->moderatorId) {
    $query->where('user_id', $this->moderatorId);
    }
    if ($this->profileId) {
    $query->where('profile_id', $this->profileId);
    }

    $stats = $query->select([
    'user_id',
    'users.name as moderator_name',
    'users.email',
    DB::raw('SUM(short_messages_count) as total_short_messages'),
    DB::raw('SUM(long_messages_count) as total_long_messages'),
    DB::raw('SUM(points_received) as total_points'),
    DB::raw('SUM(earnings) as total_earnings'),
    DB::raw('AVG(response_time) as avg_response_time')
    ])
    ->groupBy('user_id', 'users.name', 'users.email')
    ->get();

    if ($this->performanceLevel) {
    $stats = $stats->filter(function ($stat) {
    $totalMessages = $stat->total_short_messages + $stat->total_long_messages;
    $avgEarningsPerMessage = $totalMessages ? $stat->total_earnings / $totalMessages : 0;

    $performance = match(true) {
    $avgEarningsPerMessage >= 45 => 'Excellent',
    $avgEarningsPerMessage >= 35 => 'Good',
    $avgEarningsPerMessage >= 25 => 'Average',
    default => 'Poor'
    };

    return strtolower($performance) === strtolower($this->performanceLevel);
    });
    }

    return $stats;
    }

    public function headings(): array
    {
    return [
    'Moderator Name',
    'Email',
    'Total Messages',
    'Short Messages',
    'Long Messages',
    'Average Response Time (min)',
    'Points Earned',
    'Total Earnings (€)',
    'Performance Level'
    ];
    }

    public function map($row): array
    {
    $totalMessages = $row->total_short_messages + $row->total_long_messages;
    $avgEarningsPerMessage = $totalMessages ? $row->total_earnings / $totalMessages : 0;

    $performance = match(true) {
    $avgEarningsPerMessage >= 45 => 'Excellent',
    $avgEarningsPerMessage >= 35 => 'Good',
    $avgEarningsPerMessage >= 25 => 'Average',
    default => 'Poor'
    };

    return [
    $row->moderator_name,
    $row->email,
    $totalMessages,
    $row->total_short_messages,
    $row->total_long_messages,
    round($row->avg_response_time / 60, 1), // Convert to minutes
    $row->total_points,
    number_format($row->total_earnings, 2),
    $performance
    ];
    }

    public function styles(Worksheet $sheet)
    {
    return [
    1 => ['font' => ['bold' => true]],
    'A1:I1' => [
    'fill' => [
    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'E5E7EB']
    ]
    ]
    ];
    }

    private function getStartDate()
    {
    if ($this->dateRange && isset($this->dateRange['start'])) {
    return Carbon::parse($this->dateRange['start']);
    }

    return match($this->period) {
    'today' => now()->startOfDay(),
    'yesterday' => now()->subDay()->startOfDay(),
    'week' => now()->startOfWeek(),
    'month' => now()->startOfMonth(),
    default => now()->startOfWeek()
    };
    }
    }