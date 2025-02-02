<?php

namespace App\Actions;

use App\Facades\YouTube;
use App\Models\Channel;
use App\Models\Stream;

class ImportVideoAction
{
    public function handle(
        string $youTubeId,
        string $languageCode = 'en',
        $approved = false,
        ?string $submittedByEmail = null,
    ): Stream {
        $video = YouTube::video($youTubeId);

        return Stream::updateOrCreate(['youtube_id' => $video->videoId], [
            'channel_id' => Channel::firstWhere('platform_id', $video->channelId)->id ?? null,
            'title' => $video->title,
            'description' => $video->description,
            'thumbnail_url' => $video->thumbnailUrl,
            'scheduled_start_time' => $video->plannedStart,
            'language_code' => $languageCode,
            'status' => $video->status,
            'approved_at' => $approved ? now() : null,
            'submitted_by_email' => $submittedByEmail,
        ]);
    }
}
