<?php

namespace Gazelle\Enum;

enum AuthnMechanism {
    case none;
    case session;
    case apikey;
    case passkey;
}
