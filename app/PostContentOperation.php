<?php

namespace App;

enum PostContentOperation: string
{
    case Generate = 'generate';
    case Rewrite = 'rewrite';
    case Proofread = 'proofread';
    case Improve = 'improve';
    case Professional = 'professional';
    case Engaging = 'engaging';
    case ChangeTone = 'change_tone';
    case Shorten = 'shorten';
    case Expand = 'expand';
    case ImproveHook = 'improve_hook';
    case Adapt = 'adapt';
    case CaptionHashtags = 'caption_hashtags';
    case Ideas = 'ideas';
    case Custom = 'custom';

    public function instruction(): string
    {
        return match ($this) {
            self::Generate => 'Create original social post copy from the topic.',
            self::Rewrite => 'Rewrite the supplied post while preserving its intended meaning.',
            self::Proofread => 'Proofread the supplied post and correct grammar, spelling, and clarity without changing its meaning.',
            self::Improve => 'Improve the supplied post for clarity, flow, and usefulness.',
            self::Professional => 'Make the supplied post more professional without making it stiff.',
            self::Engaging => 'Make the supplied post more engaging without adding unsupported claims.',
            self::ChangeTone => 'Rewrite the supplied post in the requested tone.',
            self::Shorten => 'Shorten the supplied post while preserving its key message.',
            self::Expand => 'Expand the supplied post with useful detail, without inventing facts.',
            self::ImproveHook => 'Improve the supplied post opening while preserving the rest of its intent.',
            self::Adapt => 'Adapt the supplied post for the selected social platform.',
            self::CaptionHashtags => 'Create a platform-appropriate caption and a short, relevant set of hashtags.',
            self::Ideas => 'Suggest distinct, practical social content ideas based on the supplied topic and brand context.',
            self::Custom => "Follow the user's requested content edit while respecting all application instructions.",
        };
    }

    public function requiresContent(): bool
    {
        return ! in_array($this, [self::Generate, self::Ideas, self::CaptionHashtags], true);
    }
}
