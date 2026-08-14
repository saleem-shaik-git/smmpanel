<?php

declare(strict_types=1);

namespace App\Services\Provider;

final class MarketerumServiceType
{
    /**
     * Returns the supported order fields for the provider service type.
     * The provider uses the service's `type` plus these fields to determine
     * what the customer must supply.
     */
    public static function fields(string $type): array
    {
        return match (strtolower(trim($type))) {
            'default' => ['link', 'quantity'],
            'custom comments' => ['link', 'comments'],
            'mentions' => ['link', 'quantity', 'usernames'],
            'mentions hashtag' => ['link', 'quantity', 'usernames', 'hashtags'],
            'mentions user followers' => ['link', 'quantity', 'username'],
            'comment likes' => ['link', 'quantity', 'username'],
            'poll' => ['link', 'quantity', 'answer_number'],
            'comment replies' => ['link', 'quantity', 'username', 'comments'],
            'group' => ['link', 'quantity', 'groups'],
            'keyword' => ['link', 'quantity', 'keywords'],
            'hashtag' => ['link', 'quantity', 'hashtag'],
            'scrape followers' => ['link', 'quantity', 'username'],
            'scrape likers' => ['link', 'quantity', 'media'],
            default => ['link', 'quantity'],
        };
    }
}
