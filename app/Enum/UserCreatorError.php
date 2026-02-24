<?php

namespace Gazelle\Enum;

enum UserCreatorError {
    case duplicate;
    case email;
    case invitation;
    case password;
    case username;
}
