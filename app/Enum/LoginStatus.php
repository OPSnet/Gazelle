<?php

namespace Gazelle\Enum;

enum LoginStatus {
    case ok;
    case badCredentials;
    case unconfirmed;
}
