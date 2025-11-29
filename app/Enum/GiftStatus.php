<?php

namespace Gazelle\Enum;

enum GiftStatus: string {
    case success          = 'success';
    case badParameter     = 'bad parameters';
    case eventClosed      = 'the event has finished';
    case invalidParameter = 'invalid parameters';
    case linkExpired      = 'the link has expired';
    case tooEarly         = 'too early, come back tomorrow';
}
