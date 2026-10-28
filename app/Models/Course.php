<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasMediaUrls;

    protected $guarded = ['id'];

    protected $casts = [
        'is_published' => 'boolean',
        'is_free' => 'boolean',
        'features' => 'array',
    ];

    /** Access plans (period + price + discount), in the admin's order. */
    public function plans(): HasMany
    {
        return $this->hasMany(CoursePlan::class)->orderBy('position')->orderBy('id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position')->orderBy('id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('position')->orderBy('id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The course outline: modules in order, each with its lessons and quizzes
     * merged by position, plus items that are not in a module (under null).
     *
     * @return \Illuminate\Support\Collection<int, array{module: ?CourseModule, items: \Illuminate\Support\Collection}>
     */
    public function curriculum(bool $publishedOnly = true)
    {
        $lessons = $this->lessons()->when($publishedOnly, fn ($q) => $q->where('is_published', true))->get();
        $quizzes = $this->quizzes()->when($publishedOnly, fn ($q) => $q->where('is_published', true))->withCount('questions')->get();
        $items = $lessons->concat($quizzes)->sortBy(fn ($item) => sprintf('%08d-%d-%08d', $item->position, $item instanceof Quiz ? 1 : 0, $item->id));

        $outline = $this->modules()->get()->map(fn (CourseModule $module) => [
            'module' => $module,
            'items' => $items->where('course_module_id', $module->id)->values(),
        ]);

        $loose = $items->whereNull('course_module_id')->values();
        if ($loose->isNotEmpty()) {
            $outline->push(['module' => null, 'items' => $loose]);
        }

        return $outline->values();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Free course: students enrol with an account, no payment. */
    public function isFree(): bool
    {
        return (bool) $this->is_free;
    }

    /** Can be bought online (not free, and has at least one priced plan). */
    public function isPurchasable(): bool
    {
        return $this->is_published && ! $this->isFree() && $this->plans->isNotEmpty();
    }

    /** The plan with the lowest price today. */
    public function cheapestPlan(): ?CoursePlan
    {
        return $this->plans->sortBy(fn (CoursePlan $plan) => $plan->currentPrice())->first();
    }

    /** Biggest discount running today, for a "-30%" badge (0 = none). */
    public function bestDiscount(): int
    {
        return (int) $this->plans->filter(fn (CoursePlan $plan) => $plan->discountActive())->max('discount_percent');
    }

    /** "Free", "$49.00", or "from $20.00" when there are several plans. */
    public function formattedPrice(): string
    {
        if ($this->isFree()) {
            return __('account.free');
        }
        $plan = $this->cheapestPlan();
        if (! $plan) {
            return '';
        }

        return $this->plans->count() > 1
            ? __('account.from_price', ['price' => $plan->formattedPrice()])
            : $plan->formattedPrice();
    }

    /**
     * The "what the course covers" lines as groups. A line starting with ✅ or
     * ending with "៖" / ":" is a heading; a line starting with "-" or "•" is an
     * item under the heading above it. Emoji, dashes and invisible characters
     * are removed. Without headings everything lands in one untitled group.
     *
     * @return list<array{title: ?string, items: list<string>}>
     */
    public function featureGroups(): array
    {
        $groups = [];

        foreach ($this->features ?? [] as $line) {
            $line = trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', ' ', (string) $line));
            if ($line === '') {
                continue;
            }

            $isItem = (bool) preg_match('/^[-–—•·*]\s*/u', $line);
            $marked = (bool) preg_match('/^(✅|✔️?|☑️?)/u', $line);
            $text = trim(preg_replace('/^(?:[-–—•·*]|✅|✔️?|☑️?)\s*/u', '', $line));
            $isHeading = ! $isItem && ($marked || preg_match('/[:៖]$/u', $text));

            if ($isHeading) {
                $groups[] = ['title' => trim(preg_replace('/[:៖]$/u', '', $text)), 'items' => []];
            } else {
                // "- item" lines go under the last heading; plain lines collect in an untitled group.
                $last = array_key_last($groups);
                if ($last === null || (! $isItem && $groups[$last]['title'] !== null)) {
                    $groups[] = ['title' => null, 'items' => []];
                    $last = array_key_last($groups);
                }
                $groups[$last]['items'][] = $text;
            }
        }

        return $groups;
    }

    /** "1 year access", "30 days access", "Lifetime access" (translated) for a number of days. */
    public static function accessLabelFor(?int $days): string
    {
        return match (true) {
            ! $days => __('account.access_lifetime'),
            $days % 365 === 0 => trans_choice('account.access_years', $days / 365, ['count' => $days / 365]),
            $days % 30 === 0 => trans_choice('account.access_months', $days / 30, ['count' => $days / 30]),
            default => trans_choice('account.access_days', $days, ['count' => $days]),
        };
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    public function url(): string
    {
        return route('courses.show', ['course' => $this->slug]);
    }
}
