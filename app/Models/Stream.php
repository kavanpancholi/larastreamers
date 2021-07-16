<?php

namespace App\Models;

use App\Services\Youtube\StreamData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;
use Spatie\IcalendarGenerator\Components\Event;

class Stream extends Model implements Feedable
{
    use HasFactory;

    protected $fillable = [
        'channel_title',
        'youtube_id',
        'title',
        'description',
        'thumbnail_url',
        'scheduled_start_time',
        'actual_start_time',
        'actual_end_time',
        'hidden_at',
        'status',
        'tweeted_at',
        'language_code',
        'submitted_by_email',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'scheduled_start_time' => 'datetime',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'hidden_at' => 'datetime',
        'tweeted_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    public static function getFeedItems(): Collection
    {
        return static::query()->upcoming()->get();
    }

    public function hasBeenTweeted(): bool
    {
        return ! is_null($this->tweeted_at);
    }

    public function markAsTweeted(): self
    {
        $this->update(['tweeted_at' => now()]);

        return $this;
    }

    public function isLive(): bool
    {
        return $this->status === StreamData::STATUS_LIVE;
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StreamData::STATUS_LIVE,
            StreamData::STATUS_UPCOMING,
        ]);
    }

    public function scopeFromLatestToOldest(Builder $query): Builder
    {
        return $query->orderByDesc('scheduled_start_time');
    }

    public function scopeFromOldestToLatest(Builder $query): Builder
    {
        return $query->orderBy('scheduled_start_time');
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->where('status', StreamData::STATUS_FINISHED);
    }

    public function scopeNotOlderThanAYear(Builder $query): Builder
    {
        return $query->where(
            'scheduled_start_time',
            '>=',
            now()->subYear()->startOfYear()
        );
    }

    public function toFeedItem(): FeedItem
    {
        return FeedItem::create()
            ->id($this->id)
            ->title($this->title)
            ->summary($this->description)
            ->updated($this->updated_at)
            ->link($this->url())
            ->author($this->channel_title); //TODO: implement
    }

    public function url(): string
    {
        return "https://www.youtube.com/watch?v={$this->youtube_id}";
    }

    public function toCalendarItem(): Event
    {
        return Event::create()
            ->uniqueIdentifier($this->youtube_id)
            ->name($this->title)
            ->description(implode(PHP_EOL, [
                $this->title,
                $this->channel_title,
                $this->url(),
                Str::of($this->description)
                    ->whenNotEmpty(fn(Stringable $description) => $description->prepend(str_repeat('-', 15).PHP_EOL)),
            ]))
            ->startsAt($this->scheduled_start_time)
            ->endsAt($this->scheduled_start_time->clone()->addHour())
            ->createdAt($this->created_at);
    }

    public function language(): HasOne
    {
        return $this->hasOne(Language::class, 'code', 'language_code');
    }

    public function toWebcalLink(): string
    {
        $url = parse_url(route('calendar.ics.stream', $this));

        return "webcal://{$url['host']}{$url['path']}";
    }

    public function approveUrl(): string
    {
        return URL::temporarySignedRoute(
            'stream.approve',
            now()->addMonth(),
            ['stream' => $this],
        );
    }

    public function rejectUrl(): string
    {
        return URL::temporarySignedRoute(
            'stream.reject',
            now()->addMonth(),
            ['stream' => $this],
        );
    }

    public function isApproved(): bool
    {
        return ! is_null($this->approved_at);
    }

    public function getDurationAttribute(): ?string
    {
        if (is_null($this->actual_end_time)) {
            return null;
        }

        $start_time = $this->actual_start_time ?? $this->scheduled_start_time;

        return $start_time->longAbsoluteDiffForHumans($this->actual_end_time, 2);
    }
}
